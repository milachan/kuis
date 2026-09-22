<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil penilaian otomatis AI (DeepSeek) atas jawaban refleksi siswa.
     * AI hanya membaca teks; bukti screenshot tetap diverifikasi guru.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // pending | scored | error | skipped
            $table->string('ai_status')->default('pending')->after('status');
            $table->unsignedTinyInteger('ai_score')->nullable()->after('ai_status'); // 0-100
            $table->text('ai_feedback')->nullable()->after('ai_score');
            $table->json('ai_details')->nullable()->after('ai_feedback');
            $table->string('ai_model')->nullable()->after('ai_details');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_model');
            // Guru boleh menimpa skor AI; nilai asli AI tetap disimpan.
            $table->boolean('ai_overridden')->default(false)->after('ai_reviewed_at');

            $table->index('ai_status');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['ai_status']);
            $table->dropColumn([
                'ai_status',
                'ai_score',
                'ai_feedback',
                'ai_details',
                'ai_model',
                'ai_reviewed_at',
                'ai_overridden',
            ]);
        });
    }
};
