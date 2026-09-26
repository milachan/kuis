<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuatan dan pengelolaan kelompok siswa.
 */
class TeamService
{
    public function __construct(
        protected MissionService $missions,
    ) {}

    /**
     * Buat kelompok baru di dalam sebuah sesi beserta anggotanya,
     * lalu siapkan progres misi (misi 1 terbuka).
     *
     * @param  array<int, string>  $members
     */
    public function create(GameSession $session, string $name, array $members): Team
    {
        return DB::transaction(function () use ($session, $name, $members) {
            $team = Team::query()->create([
                'game_session_id' => $session->id,
                'name' => trim($name),
                'started_at' => now(),
            ]);

            $this->syncMembers($team, $members);

            $this->missions->initializeProgress($team);

            // Kelompok yang bergabung di tengah permainan (guru membuka lobi)
            // langsung mendapat semua ronde yang sedang terbuka supaya bisa ikut
            // bermain bersama kelas, bukan hanya berhasil masuk lalu terkunci.
            $this->missions->openActiveRounds($team, $session);

            return $team;
        });
    }

    /**
     * Ganti daftar anggota kelompok.
     *
     * Hanya anggota yang BENAR-BENAR berubah yang disentuh: nama yang sudah ada
     * dipertahankan (ID-nya tidak berubah), yang hilang dihapus, dan yang baru
     * ditambahkan. Versi lama menghapus semua lalu membuat ulang setiap kali
     * siswa masuk, sehingga ID anggota selalu berubah dan baris lama menumpuk
     * di database.
     *
     * @param  array<int, string>  $members
     */
    public function syncMembers(Team $team, array $members): void
    {
        $clean = collect($members)
            ->map(fn ($m) => trim((string) $m))
            ->filter(fn ($m) => $m !== '')
            ->unique()
            ->map(fn ($m) => Str::limit($m, 100, ''))
            ->values();

        $sekarang = $team->members()->pluck('name')->all();

        // Hapus hanya nama yang memang tidak ada lagi di daftar baru.
        $team->members()->whereNotIn('name', $clean->all())->delete();

        // Tambah hanya nama yang belum ada (case-insensitive).
        $sudahAda = collect($sekarang)->map(fn ($n) => mb_strtolower($n))->all();

        foreach ($clean as $memberName) {
            if (in_array(mb_strtolower($memberName), $sudahAda, true)) {
                continue;
            }

            TeamMember::query()->create([
                'team_id' => $team->id,
                'name' => $memberName,
            ]);
        }
    }

    /**
     * Reset progres kelompok: hapus kiriman, kembalikan ke misi 1, XP nol.
     */
    public function resetProgress(Team $team, SubmissionService $submissions): void
    {
        DB::transaction(function () use ($team, $submissions) {
            $submissions->deleteAllForTeam($team);

            $team->progress()->delete();
            $team->update([
                'xp' => 0,
                'started_at' => now(),
                'completed_at' => null,
            ]);

            $this->missions->initializeProgress($team);
        });
    }

    /**
     * Hapus kelompok beserta seluruh data terkait.
     */
    public function delete(Team $team, SubmissionService $submissions): void
    {
        DB::transaction(function () use ($team, $submissions) {
            $submissions->deleteAllForTeam($team);
            $team->progress()->delete();
            $team->members()->delete();
            $team->delete();
        });
    }
}
