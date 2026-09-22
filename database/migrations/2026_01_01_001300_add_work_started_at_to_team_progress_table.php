<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waktu mulai mengerjakan dihitung sejak siswa membuka halaman ronde.
     *
     * Dipakai untuk batas waktu per siswa: selama ronde masih dibuka, siswa
     * bebas mengerjakan sampai waktunya habis (dihitung dari dia masuk),
     * bukan dari saat guru membuka ronde.
     */
    public function up(): void
    {
        Schema::table('team_progress', function (Blueprint $table) {
            $table->timestamp('work_started_at')->nullable()->after('unlocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_progress', function (Blueprint $table) {
            $table->dropColumn('work_started_at');
        });
    }
};
