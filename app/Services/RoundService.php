<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Team;
use App\Models\TeamProgress;
use Illuminate\Support\Carbon;
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
     * Buka BEBERAPA ronde sekaligus.
     *
     * Guru boleh mencentang ronde 1, 3, dan 5: ketiganya terbuka bersamaan dan
     * kelompok bebas memilih mau mengerjakan yang mana (kelompok tidak lagi
     * dipaksa menunggu satu ronde selesai). Ronde yang TIDAK dipilih otomatis
     * ditutup, sehingga guru juga bisa memakai cara ini untuk mundur ke ronde
     * sebelumnya.
     *
     * `current_round` diisi ronde paling besar sebagai ronde terdepan: dipakai
     * tombol "Ronde Berikutnya" dan penanda di layar proyektor.
     *
     * XP dan jawaban yang sudah terkumpul TIDAK dihapus.
     *
     * @param  array<int, int>  $rounds
     */
    public function openRounds(GameSession $session, array $rounds, ?int $durationMinutes = null): GameSession
    {
        $nomor = collect($rounds)
            ->map(fn ($r) => (int) $r)
            ->unique()
            ->filter(fn (int $r) => $r > 0)
            ->sort()
            ->values()
            ->all();

        $dipilih = collect($nomor)
            ->map(fn (int $r) => $this->missionForRound($r))
            ->filter()
            ->values();

        if ($dipilih->isEmpty()) {
            return $session;
        }

        $nomor = $dipilih->map(fn (Mission $m) => (int) $m->order)->sort()->values()->all();
        $mulaiJam = $this->clockStartTime($session);

        DB::transaction(function () use ($session, $dipilih, $nomor, $durationMinutes, $mulaiJam) {
            // Tutup semua ronde di luar pilihan (baik yang sebelum maupun
            // sesudahnya) supaya "yang terbuka" persis sama dengan pilihan guru.
            $this->closeRoundsExcept($session, $nomor);

            $session->update([
                'current_round' => max($nomor),
                'open_rounds' => $nomor,
                'round_status' => GameSession::ROUND_RUNNING,
                'round_started_at' => now(),
                // Jam kelas mulai berjalan saat permainan benar-benar dimulai,
                // bukan saat sesi dibuat (lihat clockStartTime()).
                'start_time' => $mulaiJam,
                'round_duration_minutes' => $durationMinutes ?? $session->round_duration_minutes,
                // Kunci lobby agar kelompok baru tidak masuk di tengah ronde.
                'lobby_locked' => true,
            ]);

            // Buka semua misi terpilih untuk SEMUA kelompok di sesi ini.
            // Ronde yang dipilih guru BUKAN "pendatang baru" ($lateEntry = false),
            // supaya saat guru mengganti pilihan, ronde yang dilepas benar-benar
            // ditutup kembali.
            foreach ($session->teams as $team) {
                foreach ($dipilih as $mission) {
                    $this->missions->unlockManually($team, $mission, false);
                }
            }
        });

        return $session->refresh();
    }

    /**
     * Waktu mulai jam kelas yang harus dipakai saat ronde dibuka.
     *
     * Sesi baru dibuat dengan `start_time` kosong supaya jam kelas TIDAK
     * berjalan selama guru menyiapkan kelas. Jam baru dinyalakan di sini:
     *
     * - belum pernah dinyalakan -> mulai dari sekarang;
     * - jamnya sudah habis tetapi guru masih membuka ronde -> dinyalakan ulang,
     *   karena kelas jelas masih berjalan dan tanpa ini semua kiriman akan
     *   ditolak tanpa sebab yang terlihat guru.
     */
    protected function clockStartTime(GameSession $session): ?Carbon
    {
        // Durasi 0 = tanpa batas waktu: tidak ada jam yang perlu dinyalakan.
        if ($session->duration_minutes <= 0) {
            return $session->start_time;
        }

        if ($session->start_time === null || $session->isTimeUp()) {
            return now();
        }

        return $session->start_time;
    }

    /**
     * Tambah jatah waktu kelas.
     *
     * Batas waktu baru dihitung dari yang paling akhir antara sisa waktu yang
     * masih ada dan waktu sekarang, sehingga menekan tombol ini selalu berarti
     * "kelas punya tambahan N menit lagi" — termasuk bila jamnya sudah telanjur
     * habis (kelas tidak langsung terkunci lagi).
     */
    public function extendTime(GameSession $session, int $minutes = 15): GameSession
    {
        if ($session->duration_minutes <= 0) {
            return $session; // Tanpa batas waktu: tidak ada yang bisa ditambah.
        }

        $batas = $session->deadline();
        $dasar = ($batas === null || $batas->isPast()) ? now() : $batas;

        $session->update([
            'start_time' => $dasar->copy()->addMinutes($minutes)->subMinutes($session->duration_minutes),
        ]);

        return $session->refresh();
    }

    /**
     * Matikan batas waktu kelas (durasi 0 = tanpa batas).
     *
     * Dipakai ketika guru masih ingin melanjutkan permainan tetapi tidak mau
     * ada kiriman yang ditolak karena jam kelas habis.
     */
    public function removeTimeLimit(GameSession $session): GameSession
    {
        $session->update([
            'duration_minutes' => 0,
            'start_time' => null,
        ]);

        return $session->refresh();
    }

    /**
     * Buka satu ronde saja (ronde lain ditutup).
     *
     * Dipakai guru untuk mundur ke ronde sebelumnya atau melompat ke ronde
     * tertentu dari daftar ronde.
     */
    public function openRound(GameSession $session, int $round, ?int $durationMinutes = null): GameSession
    {
        return $this->openRounds($session, [$round], $durationMinutes);
    }

    /**
     * Tutup SEMUA ronde yang sedang terbuka.
     *
     * Dipakai ketika guru ingin menghentikan pengerjaan tanpa membuka ronde
     * lain (mis. kelas sudah selesai lebih cepat).
     */
    public function closeAllRounds(GameSession $session): GameSession
    {
        DB::transaction(function () use ($session) {
            $this->closeRoundsExcept($session, []);

            $session->update([
                'open_rounds' => [],
                'round_status' => GameSession::ROUND_ENDED,
                'round_started_at' => null,
            ]);
        });

        return $session->refresh();
    }

    /**
     * Tutup semua misi KECUALI ronde yang disebut di $keep.
     *
     * Misi yang sudah selesai / menunggu validasi dibiarkan, karena
     * jawabannya sudah masuk dan XP-nya harus tetap aman. Yang ditutup hanya
     * misi yang masih bisa dikerjakan.
     *
     * Kekecualian: kelompok yang masuk terlambat (`late_entry`) tidak dikunci,
     * supaya murid yang baru bergabung tidak kehilangan kesempatan memulai.
     *
     * @param  array<int, int>  $keep  Nomor ronde yang tetap dibuka.
     */
    protected function closeRoundsExcept(GameSession $session, array $keep): void
    {
        $missionLain = Mission::query()
            ->active()
            ->when($keep !== [], fn ($query) => $query->whereNotIn('order', $keep))
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
     *
     * Ini "mode satu ronde": ronde lain yang sedang terbuka ikut ditutup,
     * sehingga guru bisa kembali fokus ke satu ronde saja.
     */
    public function start(GameSession $session, int $round, ?int $durationMinutes = null): GameSession
    {
        return $this->openRounds($session, [$round], $durationMinutes);
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
            'open_rounds' => [],
            'round_status' => GameSession::ROUND_IDLE,
            'round_started_at' => null,
            'lobby_locked' => false,
        ]);

        return $session->refresh();
    }

    /**
     * Daftar ronde yang sedang terbuka, lengkap dengan judul misinya.
     *
     * Dipakai layar proyektor dan halaman sesi guru supaya keduanya menampilkan
     * daftar yang sama ketika guru membuka beberapa ronde sekaligus.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openRoundInfo(GameSession $session): array
    {
        $peta = $this->missions()
            ->keyBy(fn (Mission $mission) => (int) $mission->order);

        return array_map(function (int $order) use ($peta) {
            /** @var Mission|null $mission */
            $mission = $peta[$order] ?? null;

            return [
                'order' => $order,
                'title' => $mission?->title,
                'is_game' => $mission ? $mission->hasGame() : false,
            ];
        }, $session->openRoundNumbers());
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
