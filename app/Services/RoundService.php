<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Team;
use App\Models\TeamProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mode permainan ala kuis untuk layar proyektor.
 *
 * Alur: guru menyiapkan kelompok -> menekan "Mulai Ronde 1" -> misi ronde itu
 * dibuka untuk SEMUA kelompok sekaligus dan timer ronde berjalan -> kelompok
 * berlomba mengumpulkan skor -> guru menutup ronde.
 */
class RoundService
{
    public function __construct(
        protected MissionService $missions,
    ) {}

    /**
     * Total ronde = jumlah misi aktif (satu misi = satu ronde).
     */
    public function totalRounds(): int
    {
        return Mission::query()->active()->count();
    }

    /**
     * Daftar misi aktif menurut urutan, dipakai sebagai daftar ronde.
     *
     * @return Collection<int, Mission>
     */
    public function missions(): Collection
    {
        return Mission::query()->active()->ordered()->get();
    }

    /**
     * Misi untuk nomor ronde tertentu.
     */
    public function missionForRound(int $round): ?Mission
    {
        return $this->missions()
            ->first(fn (Mission $mission) => $mission->order === $round);
    }

    /**
     * Nomor ronde berikutnya yang harus dibuka.
     *
     * Dipakai tombol tunggal "Ronde Berikutnya": guru tidak perlu tahu
     * ronde berapa yang sedang berjalan, sistem yang menentukannya.
     * Mengembalikan null bila semua ronde sudah dijalankan.
     */
    public function nextRoundNumber(GameSession $session): ?int
    {
        $total = $this->totalRounds();

        if ($total === 0) {
            return null;
        }

        // Ronde yang sudah pernah dibuka (status bukan idle) dianggap lewat.
        $sudahJalan = (int) $session->current_round;

        if ($session->round_status === GameSession::ROUND_RUNNING) {
            // Ronde sedang berjalan: berikutnya adalah ronde setelah ini.
            $berikutnya = $sudahJalan + 1;
        } else {
            // Belum mulai / sudah diakhiri: lanjutkan dari ronde terakhir + 1.
            $berikutnya = $sudahJalan + 1;
        }

        return $berikutnya <= $total ? $berikutnya : null;
    }

    /**
     * Buka ronde mana saja — termasuk ronde yang sudah lewat (mundur).
     *
     * Dipakai guru untuk memilih ronde langsung dari daftar ronde, bukan
     * hanya maju satu per satu. Berbeda dari `start()`, semua ronde SELAIN
     * ronde yang dibuka akan ditutup, sehingga guru bisa kembali ke ronde
     * sebelumnya walau sudah maju ke ronde berikutnya.
     *
     * XP dan jawaban yang sudah terkumpul TIDAK dihapus, jadi mengulang
     * ronde tidak merusak skor murid.
     */
    public function openRound(GameSession $session, int $round, ?int $durationMinutes = null): GameSession
    {
        $mission = $this->missionForRound($round);

        if (! $mission) {
            return $session;
        }

        DB::transaction(function () use ($session, $mission, $round, $durationMinutes) {
            // Tutup semua ronde lain (baik sebelum maupun sesudah ronde ini),
            // supaya hanya SATU ronde yang bisa dikerjakan pada satu waktu.
            $this->closeOtherRounds($session, $round);

            $session->update([
                'current_round' => $round,
                'round_status' => GameSession::ROUND_RUNNING,
                'round_started_at' => now(),
                'round_duration_minutes' => $durationMinutes ?? $session->round_duration_minutes,
                'lobby_locked' => true,
            ]);

            // Buka misi ronde pilihan untuk semua kelompok.
            foreach ($session->teams as $team) {
                $this->missions->unlockManually($team, $mission);
            }
        });

        return $session->refresh();
    }

    /**
     * Tutup SEMUA misi kecuali ronde yang sedang dibuka.
     *
     * Misi yang sudah selesai / menunggu validasi dibiarkan, karena
     * jawabannya sudah masuk dan XP-nya harus tetap aman. Yang ditutup
     * hanya misi yang masih bisa dikerjakan.
     */
    protected function closeOtherRounds(GameSession $session, int $currentRound): void
    {
        $missionLain = Mission::query()
            ->active()
            ->where('order', '!=', $currentRound)
            ->pluck('id')
            ->all();

        if ($missionLain === []) {
            return;
        }

        TeamProgress::query()
            ->whereIn('team_id', $session->teams()->select('id'))
            ->whereIn('mission_id', $missionLain)
            ->where('late_entry', false)
            ->whereIn('status', [
                TeamProgress::STATUS_AVAILABLE,
                TeamProgress::STATUS_IN_PROGRESS,
            ])
            ->update([
                'status' => TeamProgress::STATUS_LOCKED,
                'work_started_at' => null,
            ]);
    }

    /**
     * Nomor ronde yang sedang dibuka, atau null bila belum ada.
     */
    public function currentRoundNumber(GameSession $session): ?int
    {
        return $session->current_round > 0 ? (int) $session->current_round : null;
    }

    /**
     * Mulai sebuah ronde: buka misinya untuk semua kelompok dan nyalakan timer.
     */
    public function start(GameSession $session, int $round, ?int $durationMinutes = null): GameSession
    {
        $mission = $this->missionForRound($round);

        if (! $mission) {
            return $session;
        }

        DB::transaction(function () use ($session, $mission, $round, $durationMinutes) {
            // Tutup ronde sebelumnya: misi ronde lama tidak boleh dikirim lagi,
            // supaya siswa tidak bisa mengerjakan ronde lama diam-diam.
            $this->closePreviousRounds($session, $round);

            $session->update([
                'current_round' => $round,
                'round_status' => GameSession::ROUND_RUNNING,
                'round_started_at' => now(),
                'round_duration_minutes' => $durationMinutes ?? $session->round_duration_minutes,
                // Kunci lobby agar kelompok baru tidak masuk di tengah ronde.
                'lobby_locked' => true,
            ]);

            // Buka misi ronde ini untuk SEMUA kelompok di sesi.
            foreach ($session->teams as $team) {
                $this->missions->unlockManually($team, $mission);
            }
        });

        return $session->refresh();
    }

    /**
     * Tutup misi ronde-ronde lama (nomor lebih kecil dari ronde saat ini).
     *
     * Misi yang sudah selesai / menunggu validasi TIDAK diubah, karena
     * jawabannya sudah masuk dan tetap tersimpan. Yang ditutup hanya misi
     * yang masih bisa dikerjakan, agar tidak bisa dikirim setelah ronde lewat.
     *
     * Kekecualian: kelompok yang masuk terlambat (`late_entry`) tetap boleh
     * mengerjakan misi ronde 1, supaya murid yang baru bergabung setelah
     * permainan berjalan tidak kehilangan kesempatan memulai.
     */
    protected function closePreviousRounds(GameSession $session, int $currentRound): void
    {
        if ($currentRound <= 1) {
            return;
        }

        $missionLama = Mission::query()
            ->active()
            ->where('order', '<', $currentRound)
            ->pluck('id')
            ->all();

        if ($missionLama === []) {
            return;
        }

        TeamProgress::query()
            ->whereIn('team_id', $session->teams()->select('id'))
            ->whereIn('mission_id', $missionLama)
            // Pendatang baru tidak dikunci: mereka baru mulai dari ronde 1.
            ->where('late_entry', false)
            ->whereIn('status', [
                TeamProgress::STATUS_AVAILABLE,
                TeamProgress::STATUS_IN_PROGRESS,
            ])
            ->update([
                'status' => TeamProgress::STATUS_LOCKED,
                'work_started_at' => null,
            ]);
    }

    /**
     * Akhiri ronde yang sedang berjalan.
     */
    public function end(GameSession $session): GameSession
    {
        $session->update([
            'round_status' => GameSession::ROUND_ENDED,
            'lobby_locked' => false,
        ]);

        return $session->refresh();
    }

    /**
     * Kembalikan sesi ke kondisi siap main (lobi terbuka, ronde nol).
     */
    public function reset(GameSession $session): GameSession
    {
        $session->update([
            'current_round' => 0,
            'round_status' => GameSession::ROUND_IDLE,
            'round_started_at' => null,
            'lobby_locked' => false,
        ]);

        return $session->refresh();
    }

    /**
     * Buka lobi agar kelompok baru bisa bergabung lagi.
     */
    public function openLobby(GameSession $session): GameSession
    {
        $session->update(['lobby_locked' => false]);

        return $session->refresh();
    }

    /**
     * Kunci lobi secara manual.
     */
    public function closeLobby(GameSession $session): GameSession
    {
        $session->update(['lobby_locked' => true]);

        return $session->refresh();
    }

    /**
     * Apakah kelompok baru boleh bergabung sekarang.
     */
    public function lobbyIsOpen(GameSession $session): bool
    {
        return ! $session->lobby_locked && ! $session->isEnded();
    }

    /**
     * Data papan skor untuk layar proyektor, diurutkan XP tertinggi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function leaderboard(GameSession $session): array
    {
        $total = max(1, $this->totalRounds());

        return $session->teams()
            ->withCount('members')
            // Jumlahkan petunjuk lewat query agregat, bukan satu query per kelompok.
            ->withSum('progress as hints_sum', 'hints_used')
            ->with(['progress.mission'])
            ->get()
            ->map(function (Team $team) use ($total) {
                $completed = $team->progress
                    ->where('status', TeamProgress::STATUS_COMPLETED)
                    ->count();

                // Misi yang sedang berjalan: menunggu validasi / dikerjakan.
                $inProgress = $team->progress->first(
                    fn (TeamProgress $p) => in_array($p->status, [
                        TeamProgress::STATUS_IN_PROGRESS,
                        TeamProgress::STATUS_WAITING_VALIDATION,
                    ], true)
                );

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'xp' => (int) $team->xp,
                    'members' => $team->members_count,
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => (int) round(($completed / $total) * 100),
                    'current_mission' => $inProgress?->mission?->title,
                    'finished' => $team->completed_at !== null,
                    'hints' => (int) $team->hints_sum,
                ];
            })
            ->sortByDesc('xp')
            ->values()
            ->all();
    }
}
