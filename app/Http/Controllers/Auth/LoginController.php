<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Login sederhana untuk guru/admin.
 */
class LoginController extends Controller
{
    /**
     * Tampilkan form login.
     */
    public function showLoginForm()
    {
        // Jika sudah login sebagai guru, langsung ke dashboard.
        if (Auth::check() && Auth::user()->isTeacher()) {
            return redirect()->route('teacher.dashboard');
        }

        return view('auth.login');
    }

    /**
     * Proses login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah. Silakan periksa kembali.',
            ]);
        }

        // Hanya guru yang boleh masuk dashboard.
        if (! Auth::user()->isTeacher()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini bukan akun guru.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('teacher.dashboard'))
            ->with('success', 'Selamat datang kembali, '.Auth::user()->name.'.');
    }

    /**
     * Logout guru.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Kamu telah keluar dari aplikasi.');
    }
}
