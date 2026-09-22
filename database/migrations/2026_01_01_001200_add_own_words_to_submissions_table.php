<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda "bahasa sendiri" dari AI.
     *
     * Bernilai true bila siswa jelas menjawab dengan pikiran dan kata-katanya
     * sendiri, walau isinya kurang tepat. Dipakai untuk memberi BONUS XP usaha,
     * sehingga siswa yang berani menulis sendiri tetap dihargai.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->boolean('own_words')->nullable()->after('authenticity_note');
            // Berapa XP bonus usaha yang diberikan untuk kiriman ini.
            $table->unsignedSmallInteger('own_words_bonus')->default(0)->after('own_words');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['own_words', 'own_words_bonus']);
        });
    }
};
