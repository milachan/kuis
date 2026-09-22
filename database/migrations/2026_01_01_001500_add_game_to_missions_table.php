<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jenis game arcade untuk sebuah misi (ronde).
     *
     * Setiap ronde bisa memakai game berbeda. Soal materi menjadi MEKANIK
     * game: menjawab benar memberi tenaga/skor, menjawab salah mengurangi
     * nyawa. Jadi anak belajar sambil bermain.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            // snake | breakout | flappy | null (null = soal tulis biasa)
            $table->string('game_type')->nullable()->after('slug');

            // Bank soal cepat untuk mekanik game:
            // [{"pertanyaan": "...", "pilihan": ["a","b","c"], "jawaban": 0}]
            $table->json('game_questions')->nullable()->after('questions');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['game_type', 'game_questions']);
        });
    }
};
