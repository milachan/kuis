<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda "masuk terlambat" untuk sebuah misi.
     *
     * Bernilai true bila misi ini dibukakan untuk kelompok SETELAH ronde itu
     * sudah berjalan. Kelompok pendatang baru tetap boleh mengerjakan misi
     * tersebut, walau guru sudah berpindah ke ronde berikutnya.
     *
     * Ditandai secara eksplisit (bukan menebak dari waktu) supaya tidak
     * bergantung pada ketepatan jam server.
     */
    public function up(): void
    {
        Schema::table('team_progress', function (Blueprint $table) {
            $table->boolean('late_entry')->default(false)->after('work_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_progress', function (Blueprint $table) {
            $table->dropColumn('late_entry');
        });
    }
};
