<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\MissionCode;
use App\Models\Team;
use App\Models\TeamProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Semua logika misi: inisialisasi progres, kunci/buka misi, dan kode rahasia.
 */
class MissionService
{
    /**
     * Siapkan baris progres untuk kelompok baru.
     * Misi pertama otomatis AVAILABLE, sisanya LOCKED.
     */
    public function initializeProgress(Team $team): void
    {
        $missions = Mission::query()->active()->ordered()->get();

        if ($missions->isEmpty()) {
            return;
        }

        $firstMissionId = $missions->first()->id;

        DB::transaction(function () use ($team, $missions, $firstMissionId) {
            foreach ($missions as $mission) {
                $isFirst = $mission->id === $firstMissionId;

                TeamProgress::query()->updateOrCreate(
                    ['team_id' => $team->id, 'mission_id' => $mission->id],
                    [
                        'status' => $isFirst
                            ? TeamProgress::STATUS_AVAILABLE
                            : TeamProgress::STATUS_LOCKED,
                        'unlocked_at' => $isFirst ? now() : null,
                    ]
                );
            }
        });
    }

    /**
     * Ambil semua progres kelompok, diurutkan sesuai urutan misi.
     *
     * @return Collection<int, TeamProgress>
     */
    public function progressList(Team $team): Collection
    {
        // Pastikan progres lengkap walau misi baru ditambahkan setelah sesi dibuat.
        $this->syncMissingProgress($team);

        return TeamProgress::query()
            ->with('mission')
            ->where('team_id', $team->id)
            ->get()
            ->sortBy(fn (TeamProgress $p) => $p->mission->order ?? 0)
            ->values();
    }

    /**
     * Tambahkan baris progres untuk misi aktif yang belum ada di kelompok ini.
     */
    protected function syncMissingProgress(Team $team): void
    {
        $existing = TeamProgress::query()
            ->where('team_id', $team->id)
            ->pluck('mission_id')
            ->all();

        $missing = Mission::query()
            ->active()
            ->ordered()
            ->whereNotIn('id', $existing)
            ->get();

        foreach ($missing as $mission) {
            TeamProgress::query()->create([
                'team_id' => $team->id,
                'mission_id' => $mission->id,
                'status' => TeamProgress::STATUS_LOCKED,
            ]);
        }
    }

    /**
     * Tandai misi sebagai SEDANG DIKERJAKAN saat siswa membukanya.
     */
    public function markInProgress(TeamProgress $progress): void
    {
        if ($progress->status === TeamProgress::STATUS_AVAILABLE) {
            $progress->update(['status' => TeamProgress::STATUS_IN_PROGRESS]);
        }
    }

    /**
     * Buka misi berikutnya setelah misi ini lulus validasi.
     * Jika misi ini sudah yang terakhir, kelompok ditandai selesai.
     */
    public function unlockNext(TeamProgress $current): void
    {
        $nextMission = $current->mission->nextMission();

        if (! $nextMission) {
            // Semua misi selesai.
            $team = $current->team;
            if ($team && ! $team->completed_at) {
                $team->update(['completed_at' => now()]);
            }

            return;
        }

        $next = TeamProgress::query()->firstOrCreate(
            ['team_id' => $current->team_id, 'mission_id' => $nextMission->id],
            ['status' => TeamProgress::STATUS_LOCKED]
        );

        if ($next->status === TeamProgress::STATUS_LOCKED) {
            $next->update([
                'status' => TeamProgress::STATUS_AVAILABLE,
                'unlocked_at' => now(),
            ]);
        }
    }

    /**
     * Buka satu misi secara manual oleh guru.
     */
    public function unlockManually(Team $team, Mission $mission): void
    {
        $progress = TeamProgress::query()->firstOrCreate(
            ['team_id' => $team->id, 'mission_id' => $mission->id],
            ['status' => TeamProgress::STATUS_LOCKED]
        );

        if ($progress->status === TeamProgress::STATUS_LOCKED) {
            $progress->update([
                'status' => TeamProgress::STATUS_AVAILABLE,
                'unlocked_at' => now(),
            ]);
        }
    }

    /**
     * Kunci kembali sebuah misi (kebalikan unlock manual).
     */
    public function lockManually(Team $team, Mission $mission): void
    {
        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        if ($progress && ! $progress->isCompleted()) {
            $progress->update([
                'status' => TeamProgress::STATUS_LOCKED,
                'unlocked_at' => null,
            ]);
        }
    }

    /**
     * Cek kode rahasia untuk sebuah misi dalam sebuah sesi.
     * Mengembalikan true jika cocok.
     */
    public function verifyCode(GameSession $session, Mission $mission, string $input): bool
    {
        $code = MissionCode::query()
            ->where('game_session_id', $session->id)
            ->where('mission_id', $mission->id)
            ->first();

        if (! $code) {
            return false;
        }

        return $code->matches($input);
    }

    /**
     * Buat kode rahasia default untuk semua misi dalam sebuah sesi
     * (dipakai saat guru membuat sesi baru).
     */
    public function generateCodes(GameSession $session): void
    {
        $missions = Mission::query()->active()->ordered()->get();

        // Kode default yang mudah diingat guru; dapat diubah saat membuat sesi.
        $defaults = [
            1 => 'FORMAT',
            2 => 'CLIP',
            3 => 'SNIP',
            4 => 'REPORT',
            5 => 'DIGITAL',
        ];

        foreach ($missions as $mission) {
            MissionCode::query()->updateOrCreate(
                [
                    'game_session_id' => $session->id,
                    'mission_id' => $mission->id,
                ],
                [
                    'code' => $defaults[$mission->order]
                        ?? strtoupper(Str::random(6)),
                ]
            );
        }
    }

    /**
     * Simpan/ubah kode rahasia satu misi dalam satu sesi.
     */
    public function setCode(GameSession $session, Mission $mission, string $code): void
    {
        MissionCode::query()->updateOrCreate(
            [
                'game_session_id' => $session->id,
                'mission_id' => $mission->id,
            ],
            ['code' => strtoupper(trim($code))]
        );
    }

    /**
     * Ambil peta kode rahasia per misi untuk halaman guru.
     *
     * @return Collection<int, string>
     */
    public function codesForSession(GameSession $session): Collection
    {
        return MissionCode::query()
            ->where('game_session_id', $session->id)
            ->pluck('code', 'mission_id');
    }
}
