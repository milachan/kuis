<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Isi data awal: akun guru, materi Bab 3 (10 ronde), dan sesi demo.
     */
    public function run(): void
    {
        $this->seedTeacher();

        // Materi penuh Bab 3: 10 ronde + sesi demo + kode rahasia.
        $this->call(Bab3MateriSeeder::class);

        // Buat file praktik awal untuk siswa (butuh ekstensi PHP "zip").
        $this->call(PracticeFileSeeder::class);
    }

    /**
     * Akun guru/admin default.
     */
    protected function seedTeacher(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Guru TIK',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TEACHER,
                'email_verified_at' => now(),
            ]
        );
    }
}
