<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Session;

/**
 * Identitas kelompok siswa disimpan di session server, bukan di cookie yang
 * bisa dibaca klien. Yang disimpan hanyalah ID kelompok; token di DB tetap
 * menjadi faktor kedua agar sesi tidak bisa dipalsukan dengan menebak ID.
 */
class StudentAuthService
{
    protected const SESSION_KEY = 'tik_student';

    /**
     * Simpan identitas kelompok yang sudah login.
     */
    public function login(Team $team): void
    {
        Session::put(self::SESSION_KEY, [
            'team_id' => $team->id,
            'token' => $team->token,
        ]);

        // Cegah session fixation.
        Session::regenerate();
    }

    /**
     * Ambil kelompok yang sedang login, atau null.
     */
    public function currentTeam(): ?Team
    {
        $data = Session::get(self::SESSION_KEY);

        if (! is_array($data) || empty($data['team_id']) || empty($data['token'])) {
            return null;
        }

        $team = Team::query()
            ->with(['gameSession', 'members'])
            ->find($data['team_id']);

        // Token harus cocok — mencegah pemalsuan ID kelompok.
        if (! $team || ! hash_equals($team->token, (string) $data['token'])) {
            $this->logout();

            return null;
        }

        return $team;
    }

    /**
     * Pastikan user sudah masuk. Dipakai middleware.
     */
    public function check(): bool
    {
        return $this->currentTeam() !== null;
    }

    /**
     * Keluar dari identitas kelompok.
     */
    public function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();
    }
}
