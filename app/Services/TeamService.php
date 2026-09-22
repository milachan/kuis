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

            return $team;
        });
    }

    /**
     * Ganti daftar anggota kelompok.
     *
     * @param  array<int, string>  $members
     */
    public function syncMembers(Team $team, array $members): void
    {
        $clean = collect($members)
            ->map(fn ($m) => trim((string) $m))
            ->filter(fn ($m) => $m !== '')
            ->unique()
            ->values();

        $team->members()->delete();

        foreach ($clean as $memberName) {
            TeamMember::query()->create([
                'team_id' => $team->id,
                'name' => Str::limit($memberName, 100, ''),
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
