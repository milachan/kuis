<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [Dihapus] Tabel kode rahasia per sesi.
     *
     * Fitur ini dibuang lewat migrasi
     * 2026_01_01_001800_drop_mission_codes_and_code_prompt.
     * Migrasi ini dipertahankan agar riwayat database tetap utuh.
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
