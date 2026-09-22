<?php

use App\Http\Middleware\EnsureStudentTeam;
use App\Http\Middleware\EnsureTeacher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware untuk dipakai di routes.
        $middleware->alias([
            'teacher' => EnsureTeacher::class,
            'student' => EnsureStudentTeam::class,
        ]);

        // Redirect tamu yang belum login ke halaman login guru.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Semua error ditampilkan dalam Bahasa Indonesia sederhana.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $messages = [
                403 => 'Kamu tidak memiliki akses ke halaman ini.',
                404 => 'Halaman atau data yang kamu cari tidak ditemukan.',
                419 => 'Sesi kamu sudah berakhir. Silakan muat ulang halaman.',
                429 => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
                500 => 'Terjadi kesalahan pada server. Silakan coba lagi.',
            ];

            $status = $e->getStatusCode();

            // Halaman error khusus untuk 403 dan 404, sisanya pesan umum.
            if (in_array($status, [403, 404], true)) {
                return response()->view('errors.custom', [
                    // Middleware web tidak jalan pada route yang tidak cocok, jadi
                    // bag $errors tidak ikut ter-share. Set eksplisit seperti yang
                    // dilakukan Handler::renderHttpException() agar partial flash
                    // tidak error saat halaman error dirender.
                    'errors' => new ViewErrorBag,
                    'status' => $status,
                    'message' => $messages[$status],
                ], $status);
            }

            return null;
        });
    })->create();
