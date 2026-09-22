<?php

namespace App\Providers;

use App\Models\Submission;
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
    }
}
