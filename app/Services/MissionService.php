<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Team;
use App\Models\TeamProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Semua logika misi: inisialisasi progres, kunci/buka misi.
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

        // Bila permainan sudah berjalan saat kelompok ini dibuat, misi pertama
        // ditandai sebagai pendatang baru agar tidak ikut dikunci ketika guru
        // berpindah ke ronde berikutnya.
        $session = $team->gameSession;
        $isLateEntry = $session !== null && (int) $session->current_round > 1;

        DB::transaction(function () use ($team, $missions, $firstMissionId, $isLateEntry) {
            foreach ($missions as $mission) {
                $isFirst = $mission->id === $firstMissionId;

                TeamProgress::query()->updateOrCreate(
                    ['team_id' => $team->id, 'mission_id' => $mission->id],
                    [
                        'status' => $isFirst
                            ? TeamProgress::STATUS_AVAILABLE
                            : TeamProgress::STATUS_LOCKED,
                        'unlocked_at' => $isFirst ? now() : null,
                        'late_entry' => $isFirst && $isLateEntry,
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

        // Hanya misi AKTIF yang ditampilkan. Misi lama yang sudah dinonaktifkan
        // (mis. materi bab sebelumnya) tetap tersimpan di database untuk laporan
        // guru, tapi tidak lagi muncul sebagai kartu di dashboard siswa.
        return TeamProgress::query()
            ->with('mission')
            ->where('team_id', $team->id)
            ->whereHas('mission', fn ($query) => $query->active())
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
     * Daftar ronde yang sedang TERBUKA untuk sebuah kelompok.
     *
     * Kelompok sering bingung harus ke mana setelah satu ronde selesai: kartu
     * misi bercampur dengan ronde yang masih terkunci, dan halaman ronde tidak
     * punya tombol pindah sama sekali. Method ini mengumpulkan SATU daftar ronde
     * yang benar-benar bisa dikerjakan sekarang, supaya halaman mana pun bisa
     * menampilkan pemilih ronde yang sama.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function roundChoices(Team $team): Collection
    {
        $session = $team->gameSession;

        if (! $session) {
            return collect();
        }

        $orders = $session->openRoundNumbers();

        if ($orders === []) {
            return collect();
        }

        $missions = Mission::query()
            ->active()
            ->ordered()
            ->whereIn('order', $orders)
            ->get();

        if ($missions->isEmpty()) {
            return collect();
        }

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->whereIn('mission_id', $missions->pluck('id'))
            ->get()
            ->keyBy('mission_id');

        return $missions->map(function (Mission $mission) use ($progress) {
            /** @var TeamProgress|null $row */
            $row = $progress->get($mission->id);

            return [
                'id' => $mission->id,
                'order' => (int) $mission->order,
                'title' => $mission->title,
                'url' => route('student.mission.show', $mission),
                'is_game' => $mission->hasGame(),
                'is_locked' => $row === null || $row->isLocked(),
                'is_done' => $row !== null && $row->isCompleted(),
                'is_submitted' => $row !== null
                    && $row->status === TeamProgress::STATUS_WAITING_VALIDATION,
            ];
        })->values();
    }

    /**
     * Tandai misi sebagai SEDANG DIKERJAKAN saat siswa membukanya.
     *
     * Sekaligus mencatat waktu mulai kerja siswa (hanya sekali). Waktu ini
     * menjadi titik awal batas waktu per siswa: siswa bebas mengerjakan
     * selama waktunya belum habis, dihitung sejak dia membuka halaman ronde,
     * bukan sejak guru membuka ronde.
     */
    public function markInProgress(TeamProgress $progress): void
    {
        $changes = [];

        if ($progress->status === TeamProgress::STATUS_AVAILABLE) {
            $changes['status'] = TeamProgress::STATUS_IN_PROGRESS;
        }

        if ($progress->work_started_at === null) {
            $changes['work_started_at'] = now();
        }

        if ($changes !== []) {
            $progress->update($changes);
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
     *
     * Bila misi ini baru dibuka SETELAH sesi melewati rondenya (murid masuk
     * terlambat), misi ditandai `late_entry` supaya tidak ikut dikunci saat
     * guru berpindah ronde.
     *
     * @param  bool|null  $lateEntry  Paksa penanda pendatang baru. Dipakai guru
     *                                saat membuka banyak ronde sekaligus: ronde
     *                                yang memang dipilih guru BUKAN pendatang
     *                                baru, jadi tidak boleh kebal dari penguncian.
     */
    public function unlockManually(Team $team, Mission $mission, ?bool $lateEntry = null): void
    {
        $progress = TeamProgress::query()->firstOrCreate(
            ['team_id' => $team->id, 'mission_id' => $mission->id],
            ['status' => TeamProgress::STATUS_LOCKED]
        );

        // Tentukan apakah ini pendatang baru: misi dibukakan setelah ronde
        // misi tersebut sudah lewat dari ronde aktif sesi.
        $session = $team->gameSession;
        $otomatis = $lateEntry === null
            && $session !== null
            && $session->current_round > 0
            && $mission->order < $session->current_round;

        if ($progress->status === TeamProgress::STATUS_LOCKED) {
            $progress->update([
                'status' => TeamProgress::STATUS_AVAILABLE,
                'unlocked_at' => now(),
                // Timer kerja siswa baru mulai saat dia membuka halamannya.
                'work_started_at' => null,
                'late_entry' => $lateEntry ?? $otomatis,
            ]);

            return;
        }

        // Misi sudah terbuka sebelumnya: penanda pendatang baru hanya dinaikkan
        // bila ditentukan otomatis, bukan saat guru sendiri memilih ronde ini.
        if ($otomatis && ! $progress->late_entry) {
            $progress->update(['late_entry' => true]);
        }
    }

    /**
     * Buka SEMUA ronde yang sedang terbuka untuk sebuah kelompok.
     *
     * Dipakai kelompok yang baru bergabung di tengah permainan (guru sudah
     * membuka lobi). Tanpa ini mereka bisa masuk, tetapi mentok di halaman
     * "misi masih terkunci" karena ronde aktif sudah dibuka sebelum mereka ada.
     *
     * Bila guru membuka beberapa ronde sekaligus, semuanya ikut dibuka supaya
     * kelompok baru bisa memilih ronde yang sama dengan teman sekelasnya.
     */
    public function openActiveRounds(Team $team, GameSession $session): void
    {
        $orders = $session->openRoundNumbers();

        if ($orders === []) {
            return;
        }

        $missions = Mission::query()
            ->active()
            ->whereIn('order', $orders)
            ->get();

        foreach ($missions as $mission) {
            $this->unlockManually($team, $mission);
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
}
