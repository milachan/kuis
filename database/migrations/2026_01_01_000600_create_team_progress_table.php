<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Progres tiap kelompok pada tiap misi (status, XP, jumlah petunjuk).
     */
    public function up(): void
    {
        Schema::create('team_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            // locked | available | in_progress | waiting_validation | completed
            $table->string('status')->default('locked');
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('hints_used')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'mission_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_progress');
    }
};
