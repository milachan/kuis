<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil permainan game arcade pada sebuah kiriman (per kelompok per misi).
     *
     * Soal game memakai pilihan ganda berkunci, jadi penilaiannya pasti
     * (tidak perlu AI). Angka-angka ini dipakai guru untuk melihat seberapa
     * jauh anak memahami materi lewat permainan.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedInteger('game_score')->default(0)->after('own_words_bonus');
            $table->unsignedSmallInteger('game_correct')->default(0)->after('game_score');
            $table->unsignedSmallInteger('game_wrong')->default(0)->after('game_correct');
            $table->timestamp('game_played_at')->nullable()->after('game_wrong');
            // Daftar jawaban salah, agar guru tahu bagian mana yang lemah.
            $table->json('game_missed')->nullable()->after('game_played_at');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'game_score',
                'game_correct',
                'game_wrong',
                'game_played_at',
                'game_missed',
            ]);
        });
    }
};
