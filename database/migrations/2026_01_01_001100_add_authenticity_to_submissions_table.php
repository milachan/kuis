<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indikator keaslian jawaban.
     *
     * AI memberi skor keaslian (1-5) sebagai SINYAL, bukan bukti:
     * 5 = sangat khas tulisan murid sendiri, 1 = sangat mirip hasil salinan AI.
     * Guru tetap yang memutuskan; sistem tidak menuduh atau memotong nilai.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // 1-5, null bila AI tidak menilai keaslian.
            $table->unsignedTinyInteger('authenticity_score')->nullable()->after('ai_overridden');
            $table->text('authenticity_note')->nullable()->after('authenticity_score');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['authenticity_score', 'authenticity_note']);
        });
    }
};
