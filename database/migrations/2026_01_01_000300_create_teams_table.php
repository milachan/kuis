<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelompok siswa di dalam sebuah sesi permainan.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->string('name');
            // Token acak yang disimpan di session browser untuk mempertahankan identitas kelompok.
            $table->string('token', 64)->unique();
            $table->unsignedInteger('xp')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Nama kelompok tidak boleh sama dalam satu sesi.
            $table->unique(['game_session_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
