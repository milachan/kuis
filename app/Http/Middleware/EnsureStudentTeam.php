<?php

namespace App\Http\Middleware;

use App\Services\StudentAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan siswa sudah masuk memakai kode sesi + nama kelompok.
 */
class EnsureStudentTeam
{
    public function __construct(
        protected StudentAuthService $studentAuth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->studentAuth->check()) {
            return redirect()->route('student.join')
                ->with('error', 'Silakan masuk memakai kode sesi terlebih dahulu.');
        }

        return $next($request);
    }
}
