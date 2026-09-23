<?php

namespace App\Providers;

use App\Models\Submission;
use App\Models\Team;
use App\Services\MissionService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Semua view guru memerlukan jumlah tugas yang menunggu validasi
        // (dipakai untuk badge di navigasi).
        View::composer('layouts.teacher', function ($view) {
            $view->with(
                'pendingValidationCount',
                Submission::query()->where('status', Submission::STATUS_WAITING)->count()
            );
        });

        // Semua halaman siswa memerlukan daftar ronde yang sedang terbuka untuk
        // bilah "Pindah Ronde" di header. Dihitung sekali per halaman, dari
        // kelompok yang sudah dioper controller ke view.
        View::composer('layouts.student', function ($view) {
            $team = $view->getData()['team'] ?? null;

            $view->with(
                'roundNav',
                $team instanceof Team
                    ? app(MissionService::class)->roundChoices($team)
                    : collect()
            );
        });
    }
}
