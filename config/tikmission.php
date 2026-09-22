<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Game TIK Mission
    |--------------------------------------------------------------------------
    |
    | Semua nilai yang bisa diatur guru/admin maupun pengembang ada di sini.
    |
    */

    // Ukuran maksimal file bukti dalam kilobyte. 10240 KB = 10 MB.
    'upload_max_kb' => (int) env('UPLOAD_MAX_KB', 10240),

    // Ekstensi file yang boleh diunggah sebagai bukti praktik.
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx'],

    // Ekstensi gambar saja (untuk preview di dashboard guru).
    'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],

    // Jumlah XP yang dikurangi setiap kali petunjuk dibuka.
    'hint_penalty_xp' => 10,

    // Bonus XP untuk kelompok yang menyelesaikan misi tanpa memakai petunjuk.
    'no_hint_bonus_xp' => 20,

    // Bonus XP jika misi diselesaikan sebelum tenggat timer sesi.
    'on_time_bonus_xp' => 10,

    // Bonus XP bila AI menilai jawaban memakai BAHASA SENDIRI siswa
    // (bukan hasil salinan), meskipun isi jawabannya kurang tepat.
    // Tujuannya menghargai usaha berpikir dan menulis sendiri.
    'own_words_bonus_xp' => 15,

    // Bobot XP dari GAME ARCADE (persen dari XP maksimum misi).
    // Sisanya (20%) menjadi bonus dari skor permainan.
    'game_xp_weight_percent' => 80,

    // Default XP per misi bila misi tidak mendefinisikan XP sendiri.
    'default_mission_xp' => 100,

    // Kode sesi demo bawaan. Dapat direset guru dari dashboard.
    'demo_session_code' => 'TIK8-DEMO',

    // Pilihan durasi timer (menit) yang tersedia di form sesi guru.
    'duration_options' => [0, 30, 45, 60, 90],

    // Folder relatif (di disk publik) tempat file praktik guru disimpan.
    // Contoh akhir: storage/app/public/practice
    'practice_folder' => 'practice',

    // Folder tempat bukti siswa disimpan (di disk publik).
    'evidence_folder' => 'evidence',

];
