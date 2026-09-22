<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kiriman tugas siswa: jawaban refleksi, bukti screenshot, dan file tambahan (PDF).
     */
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->text('answer')->nullable();           // jawaban refleksi / ringkasan
            $table->string('evidence_path')->nullable();  // screenshot bukti
            $table->string('file_path')->nullable();      // file tambahan (mis. PDF hasil ekspor)
            // waiting_validation | approved | revision
            $table->string('status')->default('waiting_validation');
            $table->text('teacher_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['team_id', 'mission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
