<?php

namespace Database\Seeders;

use App\Models\GameSession;
use App\Models\Mission;
use Illuminate\Database\Seeder;

/**
 * Materi Bab 4 — Sistem Komputer (Kelas VIII MTs).
 *
 * Sumber: Buku Siswa Informatika KLS VIII, halaman 78-95.
 *
 * Format: 7 ronde, SETIAP RONDE PUNYA GAME ARCADE BERBEDA.
 * Soal materi menjadi MEKANIK permainan:
 *   - jawab benar -> tenaga/skor bertambah
 *   - jawab salah -> nyawa berkurang
 * Jadi anak belajar sambil bermain game klasik (ular, breakout, flappy).
 *
 * Setiap ronde juga menyediakan beberapa soal uraian pendek untuk
 * memperdalam pemahaman (dinilai AI).
 *
 * Tidak ada kewajiban mengunggah file.
 */
class Bab3MateriSeeder extends Seeder
{
    public function run(): void
    {
        $this->hapusMisiLama();
        $this->geserNomorMisiLama();

        $missions = [];

        foreach ($this->materi() as $data) {
            $missions[$data['slug']] = Mission::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        $this->selaraskanSesiDemo($missions);

        $this->command?->info('Materi Bab 4 selesai: '.count($missions).' ronde dengan game arcade.');
    }

    /**
     * Pindahkan nomor urut misi lama ke gugus 900-an agar tidak bentrok.
     */
    protected function geserNomorMisiLama(): void
    {
        $slugsBaru = array_column($this->materi(), 'slug');

        Mission::query()
            ->whereNotIn('slug', $slugsBaru)
            ->where('order', '<', 900)
            ->update(['is_active' => false]);

        $lama = Mission::query()
            ->whereNotIn('slug', $slugsBaru)
            ->orderByDesc('order')
            ->get();

        $nomor = 999;

        foreach ($lama as $mission) {
            $mission->update(['order' => $nomor]);
            $nomor--;
        }
    }

    /**
     * Hapus misi lama yang tidak dipakai dan tidak punya data siswa.
     */
    protected function hapusMisiLama(): void
    {
        $slugsBaru = array_column($this->materi(), 'slug');

        Mission::query()
            ->whereNotIn('slug', $slugsBaru)
            ->whereDoesntHave('progress')
            ->whereDoesntHave('submissions')
            ->delete();
    }

    /**
     * Sesi demo memakai 7 ronde baru.
     *
     * @param  array<string, Mission>  $missions
     */
    protected function selaraskanSesiDemo(array $missions): void
    {
        $session = GameSession::query()->updateOrCreate(
            ['code' => 'TIK8-DEMO'],
            [
                'name' => 'Sesi Demo — Bab 4 Sistem Komputer',
                'duration_minutes' => 0,
                'start_time' => now(),
                'end_time' => null,
                'status' => GameSession::STATUS_ACTIVE,
                'leaderboard_enabled' => true,
                'hints_enabled' => true,
                'is_demo' => true,
                'current_round' => 0,
                'round_status' => GameSession::ROUND_IDLE,
                'round_started_at' => null,
                'round_duration_minutes' => 5,
                'lobby_locked' => false,
            ]
        );

    }

    /**
     * Daftar 7 ronde.
     *
     * Setiap ronde: game_type + game_questions (soal cepat untuk mekanik game)
     * + questions (soal uraian untuk pendalaman, dinilai AI).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function materi(): array
    {
        return [

            // ============================ RONDE 1 · SNAKE ============================
            [
                'order' => 1,
                'slug' => 'komponen-sistem-komputer',
                'game_type' => 'snake',
                'title' => 'Ronde 1 — Ular Komponen Sistem Komputer',
                'difficulty' => 'Mudah',
                'xp' => 100,
                'story' => 'Ular pintar sedang kelaparan pengetahuan! Setiap jawaban benar membuatnya '
                    .'bertambah panjang. Jawaban salah membuat nyawanya berkurang.',
                'objective' => 'Memahami tiga komponen sistem komputer: perangkat keras (hardware), '
                    .'perangkat lunak (software), dan pengguna (brainware).',
                'instructions' => [
                    'Tekan "Mulai Bermain" di halaman game.',
                    'Baca soal tentang komponen komputer, lalu pilih jawaban yang benar.',
                    'Jawaban benar membuat ular bertambah panjang dan skor bertambah.',
                    'Jawaban salah membuat nyawa berkurang satu.',
                ],
                'hint_1' => 'Hardware bisa disentuh, software berupa program, brainware adalah manusia penggunanya.',
                'hint_2' => 'Contoh hardware: monitor, keyboard. Contoh software: Windows, Word.',
                'reflection_question' => null,
                'game_questions' => [
                    [
                        'pertanyaan' => 'Komponen komputer yang bisa disentuh disebut...',
                        'pilihan' => ['Hardware', 'Software', 'Brainware'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Program atau aplikasi yang dijalankan komputer disebut...',
                        'pilihan' => ['Hardware', 'Software', 'Brainware'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Manusia yang mengoperasikan komputer disebut...',
                        'pilihan' => ['Hardware', 'Software', 'Brainware'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Monitor, keyboard, dan mouse termasuk...',
                        'pilihan' => ['Hardware', 'Software', 'Brainware'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Windows dan Android adalah contoh...',
                        'pilihan' => ['Perangkat keras', 'Perangkat lunak', 'Pengguna'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Tanpa brainware, komputer tidak dapat...',
                        'pilihan' => ['Dirakit', 'Dijual', 'Dioperasikan'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Urutan komponen sistem komputer adalah...',
                        'pilihan' => ['Hardware, software, brainware', 'Software, hardware, brainware', 'Brainware, hardware, software'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Contoh perangkat lunak sistem operasi adalah...',
                        'pilihan' => ['Monitor', 'Windows', 'Keyboard'],
                        'jawaban' => 1,
                    ],
                ],
                'questions' => [
                    [
                        'jenis' => 'pendapat',
                        'pertanyaan' => 'Dari tiga komponen (hardware, software, brainware), mana yang menurutmu paling penting? Beri satu alasan.',
                        'petunjuk' => 'Tidak ada jawaban salah — jelaskan pendapatmu sendiri.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 2 · BREAKOUT ============================
            [
                'order' => 2,
                'slug' => 'perangkat-io',
                'game_type' => 'breakout',
                'title' => 'Ronde 2 — Pecahkan Bata Input & Output',
                'difficulty' => 'Mudah',
                'xp' => 110,
                'story' => 'Dinding bata menyimpan rahasia perangkat komputer. Jawab soal dengan benar '
                    .'untuk memecahkan batanya satu per satu!',
                'objective' => 'Membedakan perangkat masukan (input), keluaran (output), dan penyimpanan (storage).',
                'instructions' => [
                    'Gerakkan mouse di atas papan untuk memantulkan bola.',
                    'Jawab soal dengan benar agar bola memecahkan bata.',
                    'Jawaban salah membuat bola hilang satu.',
                ],
                'hint_1' => 'Masukan = alat yang mengirim data KE komputer. Keluaran = alat yang menampilkan HASIL.',
                'hint_2' => 'Keyboard dan mouse adalah masukan. Monitor dan speaker adalah keluaran.',
                'reflection_question' => null,
                'game_questions' => [
                    [
                        'pertanyaan' => 'Keyboard termasuk perangkat...',
                        'pilihan' => ['Masukan (input)', 'Keluaran (output)', 'Penyimpanan'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Monitor termasuk perangkat...',
                        'pilihan' => ['Masukan (input)', 'Keluaran (output)', 'Penyimpanan'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Mouse berfungsi untuk...',
                        'pilihan' => ['Menampilkan gambar', 'Menyimpan data', 'Menggerakkan kursor'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Printer menghasilkan keluaran berupa...',
                        'pilihan' => ['Cetakan di kertas', 'Suara', 'Gambar di layar'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Speaker termasuk perangkat keluaran berupa...',
                        'pilihan' => ['Gambar', 'Suara', 'Teks'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Scanner berfungsi untuk...',
                        'pilihan' => ['Memutar musik', 'Mencetak dokumen', 'Memasukkan data dari kertas ke komputer'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Flashdisk termasuk perangkat...',
                        'pilihan' => ['Penyimpanan', 'Keluaran', 'Masukan'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Mikrofon menangkap suara lalu mengirimkannya ke komputer. Mikrofon termasuk...',
                        'pilihan' => ['Keluaran', 'Masukan', 'Penyimpanan'],
                        'jawaban' => 1,
                    ],
                ],
                'questions' => [
                    [
                        'jenis' => 'materi',
                        'pertanyaan' => 'Sebutkan masing-masing dua contoh perangkat masukan dan keluaran.',
                        'petunjuk' => 'Cukup daftar singkat, tidak perlu penjelasan panjang.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 3 · FLAPPY ============================
            [
                'order' => 3,
                'slug' => 'cpu-pemrosesan',
                'game_type' => 'flappy',
                'title' => 'Ronde 3 — Terbang Melewati Rintangan Prosesor',
                'difficulty' => 'Sedang',
                'xp' => 120,
                'story' => 'Burung pintar harus terbang melewati pipa-pipa rintangan. Setiap jawaban benar '
                    .'memberinya tenaga untuk mengepak lebih kuat!',
                'objective' => 'Memahami tiga bagian prosesor: unit kontrol (Control Unit), unit aritmatika '
                    .'dan logika (ALU), serta register.',
                'instructions' => [
                    'Tekan spasi (atau sentuh papan) untuk membuat burung terbang.',
                    'Jawab soal dengan benar agar burung mendapat tenaga ekstra.',
                    'Jawaban salah membuat burung jatuh.',
                ],
                'hint_1' => 'Control Unit mengendalikan, ALU menghitung, Register menyimpan sementara.',
                'hint_2' => 'Prosesor sering disebut otak komputer.',
                'reflection_question' => null,
                'game_questions' => [
                    [
                        'pertanyaan' => 'Bagian prosesor yang melakukan perhitungan disebut...',
                        'pilihan' => ['ALU', 'Control Unit', 'Register'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Bagian prosesor yang mengatur urutan perintah disebut...',
                        'pilihan' => ['ALU', 'Control Unit', 'Register'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Prosesor sering disebut sebagai...',
                        'pilihan' => ['Memori', 'Layar', 'Otak komputer'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Singkatan CPU adalah...',
                        'pilihan' => ['Central Processing Unit', 'Computer Personal Unit', 'Control Program Utility'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Tempat penyimpanan sementara di dalam prosesor disebut...',
                        'pilihan' => ['Monitor', 'Register', 'Printer'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Perhitungan 2 + 3 dilakukan oleh bagian...',
                        'pilihan' => ['Control Unit', 'Hard disk', 'ALU'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Kecepatan prosesor diukur dengan satuan...',
                        'pilihan' => ['Gigahertz (GHz)', 'Kilogram (kg)', 'Liter (L)'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Semakin tinggi kecepatan prosesor, pekerjaan komputer menjadi...',
                        'pilihan' => ['Lebih lambat', 'Lebih cepat', 'Tidak berubah'],
                        'jawaban' => 1,
                    ],
                ],
                'questions' => [
                    [
                        'jenis' => 'pendapat',
                        'pertanyaan' => 'Jika prosesor diibaratkan tubuh manusia, bagian mana yang paling mirip otak? Bagikan pendapatmu.',
                        'petunjuk' => 'Jawab dengan pemikiranmu sendiri.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 4 · SNAKE ============================
            [
                'order' => 4,
                'slug' => 'penyimpanan-cloud',
                'game_type' => 'snake',
                'title' => 'Ronde 4 — Ular Penyimpanan & Awan',
                'difficulty' => 'Sedang',
                'xp' => 120,
                'story' => 'Ular data sedang mengumpulkan file di awan. Setiap jawaban benar menambah '
                    .'panjang tubuhnya — jangan sampai datanya hilang!',
                'objective' => 'Memahami penyimpanan awan (cloud computing) dan manfaatnya untuk keamanan '
                    .'serta kerja sama data.',
                'instructions' => [
                    'Gunakan tombol panah atau W A S D untuk menggerakkan ular.',
                    'Jawab soal tentang penyimpanan dengan benar untuk memanjangkan ular.',
                    'Jawaban salah mengurangi nyawa.',
                ],
                'hint_1' => 'Awan = menyimpan data di internet, bisa dibuka dari perangkat mana saja.',
                'hint_2' => 'Contoh layanan awan: Google Drive, OneDrive, Dropbox.',
                'reflection_question' => null,
                'game_questions' => [
                    [
                        'pertanyaan' => 'Menyimpan data di internet disebut...',
                        'pilihan' => ['Cloud computing', 'Hard disk', 'Flashdisk'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Contoh layanan penyimpanan awan adalah...',
                        'pilihan' => ['Monitor', 'Google Drive', 'Keyboard'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Keuntungan menyimpan data di awan adalah...',
                        'pilihan' => ['Tidak perlu internet', 'Harus selalu bawa flashdisk', 'Bisa diakses dari mana saja'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Penyimpanan yang ada di dalam komputer dan tidak mudah dibawa disebut...',
                        'pilihan' => ['Internal (hard disk)', 'Eksternal', 'Cloud'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Syarat utama mengakses data di cloud adalah...',
                        'pilihan' => ['Printer', 'Koneksi internet', 'Speaker'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Agar data penting tidak hilang saat perangkat rusak, sebaiknya...',
                        'pilihan' => ['Dibiarkan', 'Dihapus saja', 'Disimpan di dua tempat (backup)'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Kapasitas penyimpanan biasanya diukur dalam...',
                        'pilihan' => ['Gigabyte (GB)', 'Gigahertz (GHz)', 'Meter (m)'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Menyimpan salinan data cadangan disebut...',
                        'pilihan' => ['Browsing', 'Backup', 'Printing'],
                        'jawaban' => 1,
                    ],
                ],
                'questions' => [
                    [
                        'jenis' => 'saran',
                        'pertanyaan' => 'Hard disk temanmu rusak dan tugasnya hilang. Apa saranmu agar tidak terulang?',
                        'petunjuk' => 'Beri satu saran singkat.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 5 · BREAKOUT ============================
            [
                'order' => 5,
                'slug' => 'sistem-operasi',
                'game_type' => 'breakout',
                'title' => 'Ronde 5 — Pecahkan Bata Sistem Operasi',
                'difficulty' => 'Sedang',
                'xp' => 130,
                'story' => 'Tanpa sistem operasi, komputer hanyalah kumpulan besi. Pecahkan bata berisi '
                    .'soal tentang sang manajer komputer!',
                'objective' => 'Memahami fungsi sistem operasi dan contohnya pada komputer serta ponsel.',
                'instructions' => [
                    'Gerakkan mouse di atas papan untuk memantulkan bola.',
                    'Jawab soal tentang sistem operasi dengan benar.',
                ],
                'hint_1' => 'Sistem operasi mengontrol dan mengatur sumber daya komputer.',
                'hint_2' => 'Windows, Linux, MacOS untuk komputer. Android, iOS untuk ponsel.',
                'reflection_question' => null,
                'game_questions' => [
                    [
                        'pertanyaan' => 'Program yang mengatur seluruh kerja komputer disebut...',
                        'pilihan' => ['Sistem operasi', 'Keyboard', 'Monitor'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Contoh sistem operasi komputer adalah...',
                        'pilihan' => ['Microsoft Word', 'Windows', 'Google Chrome'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Contoh sistem operasi ponsel adalah...',
                        'pilihan' => ['Photoshop', 'Word', 'Android'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Sistem operasi bertugas mengatur...',
                        'pilihan' => ['Perangkat keras dan program', 'Hanya warna layar', 'Hanya suara'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Aplikasi dijalankan di atas...',
                        'pilihan' => ['Printer', 'Sistem operasi', 'Flashdisk'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Tanpa sistem operasi, komputer...',
                        'pilihan' => ['Tetap normal', 'Berjalan lebih cepat', 'Tidak dapat menjalankan aplikasi'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Contoh sistem operasi komputer bersumber terbuka (open source) adalah...',
                        'pilihan' => ['Linux', 'Windows', 'macOS'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Bagian sistem operasi yang menampilkan ikon dan jendela disebut...',
                        'pilihan' => ['Prosesor', 'Antarmuka (interface)', 'Memori'],
                        'jawaban' => 1,
                    ],
                ],
                'questions' => [
                    [
                        'jenis' => 'saran',
                        'pertanyaan' => 'Kalau kamu diminta menyarankan sistem operasi untuk lab sekolah baru, apa pilihanmu dan mengapa?',
                        'petunjuk' => 'Tidak ada jawaban salah — beri usulan beserta alasan.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 6 · FLAPPY ============================
            [
                'order' => 6,
                'slug' => 'aplikasi-pemrograman',
                'game_type' => 'flappy',
                'title' => 'Ronde 6 — Terbang di Dunia Pemrograman',
                'difficulty' => 'Sedang',
                'xp' => 130,
                'story' => 'Burung programmer harus terbang melewati rintangan kode. Jawaban benar '
                    .'memberinya tenaga untuk terus melaju!',
                'objective' => 'Membedakan perangkat lunak aplikasi dan perangkat lunak pemrograman '
                    .'beserta contohnya.',
                'instructions' => [
                    'Tekan spasi atau sentuh papan untuk terbang.',
                    'Jawab soal tentang aplikasi dan pemrograman dengan benar.',
                ],
                'hint_1' => 'Aplikasi untuk pengguna biasa, bahasa pemrograman untuk pembuat program.',
                'hint_2' => 'Scratch dan Python adalah bahasa pemrograman.',
                'reflection_question' => null,
                'game_questions' => [
                    [
                        'pertanyaan' => 'Python dan Scratch termasuk...',
                        'pilihan' => ['Bahasa pemrograman', 'Aplikasi perkantoran', 'Perangkat keras'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Microsoft Word termasuk perangkat lunak...',
                        'pilihan' => ['Bahasa pemrograman', 'Aplikasi pengolah kata', 'Sistem operasi'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Microsoft Excel digunakan untuk mengolah...',
                        'pilihan' => ['Suara', 'Gambar bergerak', 'Angka dan tabel'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Perangkat lunak untuk membuat presentasi adalah...',
                        'pilihan' => ['PowerPoint', 'Notepad', 'WinRAR'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Scratch cocok untuk belajar pemrograman karena...',
                        'pilihan' => ['Hanya untuk ahli', 'Berbasis blok yang mudah', 'Tidak bisa dijalankan'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Programmer menulis kode menggunakan...',
                        'pilihan' => ['Monitor', 'Kalkulator', 'Bahasa pemrograman'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Perbedaan aplikasi dan bahasa pemrograman adalah...',
                        'pilihan' => ['Aplikasi siap pakai, bahasa pemrograman untuk membuat program', 'Keduanya sama saja', 'Bahasa pemrograman siap pakai'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Aplikasi perkantoran biasanya berisi pengolah kata, angka, dan...',
                        'pilihan' => ['Prosesor', 'Presentasi', 'Keyboard'],
                        'jawaban' => 1,
                    ],
                ],
                'questions' => [
                    [
                        'jenis' => 'pendapat',
                        'pertanyaan' => 'Aplikasi apa yang paling sering kamu pakai di ponsel? Menurutmu, programmer membuatnya untuk menyelesaikan masalah apa?',
                        'petunjuk' => 'Jawab sesuai pengalamanmu sendiri.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 7 · SNAKE ============================
            [
                'order' => 7,
                'slug' => 'heksadesimal',
                'game_type' => 'snake',
                'title' => 'Ronde 7 — Ular Misteri Heksadesimal',
                'difficulty' => 'Sulit',
                'xp' => 150,
                'story' => 'Komputer menyimpan alamat memori dalam kode berisi angka dan huruf. '
                    .'Ular pintar harus memakan jawaban benar untuk membongkar misteri ini!',
                'objective' => 'Memahami sistem bilangan heksadesimal: 16 simbol dan nilai huruf A-F, '
                    .'serta penggunaannya pada alamat memori.',
                'instructions' => [
                    'Gunakan tombol panah atau W A S D untuk menggerakkan ular.',
                    'Ingat: A=10, B=11, C=12, D=13, E=14, F=15.',
                    'Jawab soal heksadesimal dengan benar untuk memanjangkan ular.',
                ],
                'hint_1' => 'Heksadesimal berarti berbasis 16, bukan 10 seperti angka biasa.',
                'hint_2' => 'Setelah angka 9, huruf A bernilai 10, sampai F bernilai 15.',
                'reflection_question' => null,
                'game_questions' => [
                    [
                        'pertanyaan' => 'Dalam heksadesimal, huruf F bernilai...',
                        'pilihan' => ['15', '16', '5'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Sistem bilangan heksadesimal berbasis...',
                        'pilihan' => ['10', '16', '2'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Dalam heksadesimal, huruf A bernilai...',
                        'pilihan' => ['1', '11', '10'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Bilangan heksadesimal 10 sama dengan desimal...',
                        'pilihan' => ['16', '10', '2'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Simbol yang dipakai heksadesimal adalah...',
                        'pilihan' => ['0-9 saja', '0-9 dan A-F', '0-1 saja'],
                        'jawaban' => 1,
                    ],
                    [
                        'pertanyaan' => 'Warna di komputer sering ditulis dengan kode heksadesimal, misalnya...',
                        'pilihan' => ['255Putih', 'Putih Sekali', '#FFFFFF'],
                        'jawaban' => 2,
                    ],
                    [
                        'pertanyaan' => 'Nilai desimal dari heksadesimal C adalah...',
                        'pilihan' => ['12', '13', '3'],
                        'jawaban' => 0,
                    ],
                    [
                        'pertanyaan' => 'Heksadesimal banyak dipakai karena dapat menuliskan bilangan biner dengan...',
                        'pilihan' => ['Lebih panjang', 'Lebih singkat', 'Tidak jelas'],
                        'jawaban' => 1,
                    ],
                ],
                'questions' => [
                    [
                        'jenis' => 'saran',
                        'pertanyaan' => 'Kalau kamu harus menjelaskan bilangan heksadesimal kepada teman dengan satu kalimat sederhana, apa kalimatmu?',
                        'petunjuk' => 'Tulis dengan bahasamu sendiri, seolah menjelaskan ke teman.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],
        ];
    }
}
