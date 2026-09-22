<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mode permainan ala kuis: guru membuka ronde satu per satu dari layar
     * proyektor, dan tiap ronde punya timer sendiri.
     */
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // 0 = belum mulai ronde apa pun.
            $table->unsignedInteger('current_round')->default(0)->after('status');
            // Ronde yang sedang berjalan: idle | running | ended
            $table->string('round_status')->default('idle')->after('current_round');
            $table->timestamp('round_started_at')->nullable()->after('round_status');
            $table->unsignedInteger('round_duration_minutes')->default(10)->after('round_started_at');
            // Sementara ronde berjalan, kelompok baru tidak boleh bergabung.
            $table->boolean('lobby_locked')->default(false)->after('round_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'current_round',
                'round_status',
                'round_started_at',
                'round_duration_minutes',
                'lobby_locked',
            ]);
        });
    }
};
