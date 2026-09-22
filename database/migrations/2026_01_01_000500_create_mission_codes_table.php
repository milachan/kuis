<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode rahasia tiap misi, dibuat per sesi agar siswa tidak mudah menyontek.
     * Kode ini TIDAK pernah dikirim ke halaman siswa.
     */
    public function up(): void
    {
        Schema::create('mission_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->string('code');
            $table->timestamps();

            $table->unique(['game_session_id', 'mission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_codes');
    }
};
