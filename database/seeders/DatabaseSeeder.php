<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Isi data awal: akun guru, materi Bab Sistem Komputer (7 ronde), dan sesi demo.
     */
    public function run(): void
    {
        $this->seedTeacher();

        // Materi penuh Bab Sistem Komputer: 7 ronde + sesi demo.
        $this->call(Bab3MateriSeeder::class);

        // Buat file praktik awal untuk siswa (butuh ekstensi PHP "zip").
        $this->call(PracticeFileSeeder::class);
    }

    /**
     * Akun guru/admin default.
     *
     * KEAMANAN: akun guru tidak boleh memakai password yang bisa ditebak siswa.
     * Kredensial dibaca dari .env (TEACHER_EMAIL / TEACHER_PASSWORD). Bila
     * password tidak diisi, dibuat password acak kuat dan DICETAK sekali di
     * layar — supaya tidak pernah ada akun guru dengan password bawaan.
     */
    protected function seedTeacher(): void
    {
        $email = (string) (env('TEACHER_EMAIL') ?: 'admin@example.com');

        $password = (string) env('TEACHER_PASSWORD');
        $dibuatAcak = false;

        if ($password === '') {
            // Password acak yang mudah dibaca tapi tetap kuat.
            $password = Str::password(16);
            $dibuatAcak = true;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) (env('TEACHER_NAME') ?: 'Guru TIK'),
                'password' => Hash::make($password),
                'role' => User::ROLE_TEACHER,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Akun guru siap: '.$email);

        if ($dibuatAcak) {
            $this->command?->warn(
                'Password guru dibuat ACAK dan hanya ditampilkan sekali ini: '.$password
            );
            $this->command?->warn(
                'Simpan password ini, atau setel TEACHER_PASSWORD di .env lalu jalankan ulang seeder.'
            );
        }
    }
}
