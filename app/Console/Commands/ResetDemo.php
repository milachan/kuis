<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Services\SubmissionService;
use App\Services\TeamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reset data sesi demo agar guru dapat mencoba ulang dari awal.
 */
class ResetDemo extends Command
{
    protected $signature = 'tik:reset-demo
                            {--all : Hapus juga seluruh sesi non-demo dan bukti siswa}
                            {--files : Hapus file bukti di storage (default: hanya saat --all)}';

    protected $description = 'Reset sesi demo TIK Mission (kelompok, bukti, dan progres)';

    public function handle(TeamService $teams, SubmissionService $submissions): int
    {
        $demoCode = config('tikmission.demo_session_code');

        if ($this->option('all')) {
            return $this->resetEverything($teams, $submissions);
        }

        $session = GameSession::query()->where('code', $demoCode)->first();

        if (! $session) {
            $this->error('Sesi demo dengan kode "'.$demoCode.'" tidak ditemukan.');
            $this->line('Jalankan "php artisan db:seed" untuk membuat ulang data demo.');

            return self::FAILURE;
        }

        $teamCount = $session->teams()->count();

        DB::transaction(function () use ($session, $teams, $submissions) {
            foreach ($session->teams as $team) {
                $teams->delete($team, $submissions);
            }
        });

        // Aktifkan kembali sesi demo dan setel ulang timer.
        $session->update([
            'status' => GameSession::STATUS_ACTIVE,
            'start_time' => now(),
            'end_time' => null,
        ]);

        $this->info('Sesi demo direset.');
        $this->line('  Kode sesi        : '.$session->code);
        $this->line('  Kelompok dihapus : '.$teamCount);
        $this->line('  Timer            : '.$session->duration_minutes.' menit (mulai ulang)');
        $this->line('  Kode rahasia     : tetap tersimpan');

        return self::SUCCESS;
    }

    /**
     * Hapus semua sesi dan bukti (reset total).
     */
    protected function resetEverything(TeamService $teams, SubmissionService $submissions): int
    {
        $sessionCount = GameSession::query()->count();

        if (! $this->confirm('Hapus SEMUA sesi, kelompok, dan bukti siswa? Tindakan ini tidak dapat dibatalkan.')) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($teams, $submissions) {
            foreach (Team::query()->get() as $team) {
                $teams->delete($team, $submissions);
            }

            TeamProgress::query()->delete();
            Submission::query()->delete();

            GameSession::query()->each(function (GameSession $session) {
                $session->missionCodes()->delete();
                $session->delete();
            });
        });

        // Bersihkan folder bukti (opsional dengan --files).
        if ($this->option('files')) {
            Storage::disk('public')->deleteDirectory(config('tikmission.evidence_folder'));
            Storage::disk('public')->makeDirectory(config('tikmission.evidence_folder'));
            $this->line('Folder bukti dibersihkan.');
        }

        $this->info('Semua data dihapus ('.$sessionCount.' sesi).');
        $this->line('Jalankan "php artisan db:seed" untuk membuat data demo baru.');

        return self::SUCCESS;
    }
}
