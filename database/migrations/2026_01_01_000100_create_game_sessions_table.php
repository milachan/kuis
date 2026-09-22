<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sesi permainan yang dibuat guru. Siswa masuk memakai `code`.
     */
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('duration_minutes')->default(0); // 0 = tanpa timer
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->string('status')->default('active'); // active | ended
            $table->boolean('leaderboard_enabled')->default(false);
            $table->boolean('hints_enabled')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
