<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guru kini boleh membuka BEBERAPA ronde sekaligus (mis. ronde 1, 3, dan 5).
     *
     * `current_round` tetap dipakai sebagai ronde terdepan (untuk tombol
     * "Ronde Berikutnya" dan tampilan besar di proyektor), sedangkan kolom
     * `open_rounds` menyimpan seluruh ronde yang sedang terbuka.
     */
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // Daftar nomor ronde yang sedang terbuka, mis. [1, 3, 5].
            // Null = data lama sebelum fitur ini (dianggap satu ronde saja).
            $table->json('open_rounds')->nullable()->after('round_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn('open_rounds');
        });
    }
};
