<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus fitur Kode Rahasia: tabel `mission_codes` dan kolom
     * `missions.code_prompt` tidak dipakai lagi (kelas tunggal, diawasi langsung).
     */
    public function up(): void
    {
        Schema::dropIfExists('mission_codes');

        if (Schema::hasColumn('missions', 'code_prompt')) {
            Schema::table('missions', function (Blueprint $table) {
                $table->dropColumn('code_prompt');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('missions', 'code_prompt')) {
            Schema::table('missions', function (Blueprint $table) {
                $table->text('code_prompt')->nullable()->after('instructions');
            });
        }

        if (! Schema::hasTable('mission_codes')) {
            Schema::create('mission_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
                $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
                $table->string('code');
                $table->timestamps();

                $table->unique(['game_session_id', 'mission_id']);
            });
        }
    }
};
