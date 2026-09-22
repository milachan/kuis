<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar misi pembelajaran. Bersifat global (dipakai semua sesi),
     * kecuali kode rahasia yang disimpan per sesi di `mission_codes`.
     */
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('difficulty')->default('Mudah'); // Mudah | Sedang | Sulit
            $table->text('story');       // Cerita pembuka misi
            $table->text('objective');   // Tujuan pembelajaran
            $table->json('instructions'); // Langkah-langkah praktik
            $table->text('code_prompt')->nullable(); // Pertanyaan "masukkan kode rahasia"
            $table->text('hint_1')->nullable();
            $table->text('hint_2')->nullable();
            $table->text('reflection_question')->nullable(); // Pertanyaan refleksi singkat
            $table->unsignedInteger('xp')->default(100);
            $table->boolean('requires_pdf')->default(false); // Misi 4 wajib PDF
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
