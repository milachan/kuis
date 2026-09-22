<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Misi berbasis materi penuh: satu misi bisa memuat beberapa pertanyaan
     * uraian sekaligus, sehingga AI punya banyak bahan untuk dinilai dan
     * murid tidak perlu mengunggah file bukti.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            // Daftar pertanyaan uraian: [{"pertanyaan": "...", "petunjuk": "..."}]
            $table->json('questions')->nullable()->after('reflection_question');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('questions');
        });
    }
};
