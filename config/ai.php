<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penilaian Otomatis AI (DeepSeek)
    |--------------------------------------------------------------------------
    |
    | Jawaban refleksi siswa dinilai otomatis oleh model AI saat dikirim,
    | lalu skornya (0-100) dikonversi menjadi XP misi. Isi API key lewat
    | .env -> AI_API_KEY. Bila key kosong, fitur AI otomatis dilewati dan
    | aplikasi tetap berjalan normal (kiriman menunggu validasi guru).
    |
    */

    // Aktif/matikan seluruh fitur penilaian AI.
    'enabled' => env('AI_REVIEW_ENABLED', true),

    // Kunci rahasia. Ambil dari https://platform.deepseek.com (atau OpenRouter).
    'api_key' => env('AI_API_KEY'),

    // Endpoint gaya OpenAI-compatible. Default: DeepSeek.
    'base_url' => env('AI_BASE_URL', 'https://api.deepseek.com'),

    // Nama model. deepseek-chat = DeepSeek-V3 (cepat & murah, cukup untuk menilai jawaban).
    'model' => env('AI_MODEL', 'deepseek-chat'),

    // Batas waktu permintaan ke server AI (detik). Dibuat pendek agar
    // siswa tidak menunggu lama saat mengirim tugas.
    'timeout' => (int) env('AI_TIMEOUT', 10),

    // Suhu rendah agar penilaian konsisten antar kelompok.
    'temperature' => (float) env('AI_TEMPERATURE', 0.2),

    // Batas panjang jawaban yang dikirim ke AI (karakter) agar hemat token.
    'max_answer_chars' => (int) env('AI_MAX_ANSWER_CHARS', 2000),

    // Bobot XP: berapa persen dari XP dasar misi yang diberikan
    // saat AI memberi skor 100. Sisa XP tetap menunggu validasi bukti guru.
    // Contoh: XP misi 100, bobot 70 -> skor AI 80 memberi 56 XP.
    'xp_weight_percent' => (int) env('AI_XP_WEIGHT_PERCENT', 70),

    // Skor minimum agar jawaban dianggap lulus oleh AI (untuk informasi guru).
    'passing_score' => (int) env('AI_PASSING_SCORE', 60),

];
