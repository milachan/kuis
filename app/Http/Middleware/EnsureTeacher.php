<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya guru/admin yang boleh mengakses dashboard guru.
 * Siswa yang mencoba masuk akan diarahkan ke halaman siswa dengan pesan jelas.
 */
class EnsureTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login')
                ->with('error', 'Silakan masuk terlebih dahulu.');
        }

        if (! $request->user()->isTeacher()) {
            abort(403, 'Kamu tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
