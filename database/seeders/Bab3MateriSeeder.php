<?php

namespace Database\Seeders;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\MissionCode;
use Illuminate\Database\Seeder;

/**
 * Materi penuh Bab 3 — Teknologi Informasi dan Komunikasi (Kelas VIII).
 *
 * Sumber: Buku Siswa Informatika KLS VIII, halaman 63-88.
 * Dipecah menjadi 10 ronde pendek. Setiap ronde berisi 4 pertanyaan yang
 * dijawab SINGKAT (1-2 kalimat) supaya siswa tidak keberatan mengetik, dan
 * satu di antaranya menanyakan PENGALAMAN PRIBADI agar jawaban sulit
 * dijawab hanya dengan menyalin dari AI.
 *
 * Tidak ada kewajiban mengunggah file bukti.
 *
 * Subbab buku:
 *   A. Perangkat Lunak Aplikasi dan Fitur Aplikasi
 *   B. Pembuatan Laporan
 *   C. Merangkum Narasi dari Konten Digital
 *   D. Laboratorium Maya
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

        $this->command?->info('Materi Bab 3 selesai: '.count($missions).' misi (10 ronde).');
    }

    /**
     * Pindahkan nomor urut misi lama ke gugus 900-an agar tidak bentrok
     * dengan nomor 1-10 milik kurikulum baru.
     *
     * Misi lama yang masih punya progres milik kelompok (mis. sesi yang sudah
     * pernah dipakai) tidak dihapus supaya nilai siswa tidak hilang, tetapi
     * dikeluarkan dari daftar ronde aktif.
     */
    protected function geserNomorMisiLama(): void
    {
        $slugsBaru = array_column($this->materi(), 'slug');

        Mission::query()
            ->whereNotIn('slug', $slugsBaru)
            ->where('order', '<', 900)
            ->update(['is_active' => false]);

        // Nomor besar dipakai agar tidak bertabrakan; diurutkan mundur supaya
        // tidak ada bentrok sementara saat nomor baru ditulis.
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
     * Hapus misi lama yang tidak ada di kurikulum baru dan tidak punya data.
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
     * Sesi demo dibuat/disesuaikan agar memakai 10 misi baru + kode rahasia.
     *
     * @param  array<string, Mission>  $missions
     */
    protected function selaraskanSesiDemo(array $missions): void
    {
        $session = GameSession::query()->updateOrCreate(
            ['code' => 'TIK8-DEMO'],
            [
                'name' => 'Sesi Demo — Bab 3 TIK Kelas 8',
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

        // Bersihkan kode rahasia milik misi lama yang sudah tidak dipakai,
        // supaya guru tidak melihat kode "hantu" di halaman sesi.
        MissionCode::query()
            ->where('game_session_id', $session->id)
            ->whereNotIn('mission_id', collect($missions)->pluck('id')->all())
            ->delete();

        foreach ($this->kodeRahasia() as $slug => $code) {
            if (! isset($missions[$slug])) {
                continue;
            }

            MissionCode::query()->updateOrCreate(
                [
                    'game_session_id' => $session->id,
                    'mission_id' => $missions[$slug]->id,
                ],
                ['code' => $code]
            );
        }
    }

    /**
     * Kode rahasia tiap ronde (dicek ke database, tidak dikirim ke murid).
     *
     * @return array<string, string>
     */
    protected function kodeRahasia(): array
    {
        return [
            'perangkat-lunak-fitur' => 'SOFTWARE',
            'objek-aplikasi' => 'OBJEK',
            'format-file' => 'EKSTENSI',
            'save-vs-save-as' => 'SIMPAN',
            'mengelola-dokumen' => 'DOKUMEN',
            'membuat-laporan' => 'LAPORAN',
            'merangkum-konten' => 'RANGKUM',
            'laboratorium-maya' => 'VIRTUAL',
            'lab-maya-vs-fisik' => 'SIMULASI',
            'keamanan-digital' => 'AMAN',
        ];
    }

    /**
     * Daftar lengkap 10 misi.
     *
     * Tiap ronde: 3 soal materi (jawaban singkat) + 1 soal pengalaman pribadi.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function materi(): array
    {
        return [

            // ============================ RONDE 1 ============================
            [
                'order' => 1,
                'slug' => 'perangkat-lunak-fitur',
                'title' => 'Ronde 1 — Perangkat Lunak & Fitur Aplikasi',
                'difficulty' => 'Mudah',
                'xp' => 100,
                'story' => 'Kalian baru bergabung dengan tim intelijen digital. Sebelum masuk ke dokumen rahasia, '
                    .'kalian harus memahami dulu dengan alat apa dokumen itu dibuat.',
                'objective' => 'Memahami pengertian perangkat lunak aplikasi serta membedakan jenis aplikasi '
                    .'perkantoran (pengolah kata, angka, dan presentasi).',
                'instructions' => [
                    'Buka aplikasi pengolah kata di komputer kalian (mis. Microsoft Word atau LibreOffice Writer).',
                    'Amati menu yang tersedia: File, Home, Insert, Layout, Review, View.',
                    'Buka juga aplikasi pengolah angka dan pengolah presentasi, lalu bandingkan menunya.',
                    'Catat nama aplikasi yang kalian pakai, karena ditanyakan di soal 4.',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 1.',
                'hint_1' => 'Perangkat lunak aplikasi = program siap pakai untuk tugas tertentu.',
                'hint_2' => 'Pengolah kata untuk teks, pengolah angka untuk tabel, pengolah presentasi untuk slide.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Apa itu perangkat lunak aplikasi? Jawab satu kalimat.',
                        'petunjuk' => 'Cukup sebutkan: program siap pakai untuk membantu pengguna menyelesaikan tugas.',
                    ],
                    [
                        'pertanyaan' => 'Sebutkan tiga jenis aplikasi perkantoran dan satu contoh masing-masing.',
                        'petunjuk' => 'Cukup daftar singkat, tidak perlu penjelasan panjang.',
                    ],
                    [
                        'pertanyaan' => 'Mengapa aplikasi disebut "end-user"? Jawab satu kalimat.',
                        'petunjuk' => 'Kata kuncinya: dipakai langsung oleh pengguna akhir, bukan pembuatnya.',
                    ],
                    [
                        'pertanyaan' => 'Tulis nama aplikasi pengolah kata yang kalian pakai tadi, dan satu menu yang paling sering kalian buka. Mengapa menu itu?',
                        'petunjuk' => 'Jawab berdasarkan yang kalian lihat di komputermu sendiri, bukan dari internet.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 2 ============================
            [
                'order' => 2,
                'slug' => 'objek-aplikasi',
                'title' => 'Ronde 2 — Objek pada Aplikasi Pengolah Kata',
                'difficulty' => 'Sedang',
                'xp' => 100,
                'story' => 'Dokumen rahasia tersusun dari banyak objek. Tim yang memahami objeknya bisa membongkar '
                    .'dan menyusun ulang dokumen tanpa merusak satu pun bagian.',
                'objective' => 'Mengenal objek pada aplikasi pengolah kata: file, halaman, paragraf, baris, '
                    .'karakter, tabel, dan gambar.',
                'instructions' => [
                    'Buka dokumen latihan yang disediakan guru.',
                    'Klik pada beberapa bagian dokumen: judul, paragraf, tabel, gambar.',
                    'Klik kanan pada sebuah objek, lalu perhatikan menu yang muncul.',
                    'Buka menu Layout dan perhatikan pengaturan halamannya.',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 2.',
                'hint_1' => 'Urutan objek dari besar ke kecil: file → halaman → paragraf → baris → karakter.',
                'hint_2' => 'Tabel punya objek sendiri: tabel, baris tabel, kolom tabel, dan sel.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Urutkan dari yang paling besar: Karakter, File, Paragraf, Halaman.',
                        'petunjuk' => 'Cukup tulis urutannya saja, tidak perlu dijelaskan.',
                    ],
                    [
                        'pertanyaan' => 'Apa bedanya objek paragraf dan objek baris? Jawab singkat.',
                        'petunjuk' => 'Kata kunci: satu paragraf bisa terdiri dari beberapa baris.',
                    ],
                    [
                        'pertanyaan' => 'Sebutkan tiga hal yang bisa diatur pada objek karakter.',
                        'petunjuk' => 'Contoh: ukuran huruf, warna, jenis huruf, cetak tebal atau miring.',
                    ],
                    [
                        'pertanyaan' => 'Saat kalian klik kanan pada sebuah objek di dokumen tadi, menu apa yang muncul? Sebutkan satu bagian yang kamu ingat.',
                        'petunjuk' => 'Jawab dari apa yang benar-benar kamu lihat di layar, bukan menebak.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 3 ============================
            [
                'order' => 3,
                'slug' => 'format-file',
                'title' => 'Ronde 3 — Format File & Ekstensi',
                'difficulty' => 'Sedang',
                'xp' => 110,
                'story' => 'Satu kesalahan menyimpan file bisa membuat dokumen rahasia tidak terbaca di komputer lain. '
                    .'Tim harus hafal betul format file dan kegunaannya.',
                'objective' => 'Memahami format file (DOCX, XLSX, PPTX, PDF, TXT) dan memilih format yang tepat.',
                'instructions' => [
                    'Buat dokumen sederhana di aplikasi pengolah kata.',
                    'Coba simpan dengan format berbeda melalui File → Save As.',
                    'Perhatikan ekstensi yang muncul di akhir nama file.',
                    'Buka kembali file tersebut dan amati apakah tampilannya berubah.',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 3.',
                'hint_1' => 'Ekstensi adalah 3-4 huruf terakhir setelah titik pada nama file.',
                'hint_2' => 'PDF dibuat agar tampilan tidak berubah di komputer mana pun.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Apa itu ekstensi file? Jawab satu kalimat.',
                        'petunjuk' => 'Fungsinya: memberi tahu komputer jenis file dan aplikasi pembukanya.',
                    ],
                    [
                        'pertanyaan' => 'Sebutkan empat format file dan kegunaannya. Tulis singkat.',
                        'petunjuk' => 'Boleh dalam bentuk daftar, mis. DOCX = dokumen teks.',
                    ],
                    [
                        'pertanyaan' => 'Mengapa laporan tugas lebih baik dikirim sebagai PDF daripada DOCX? Sebutkan satu alasan.',
                        'petunjuk' => 'Pikirkan tampilan yang bisa berubah jika dibuka di komputer lain.',
                    ],
                    [
                        'pertanyaan' => 'Tulis nama file yang tadi kalian simpan beserta ekstensinya. Apakah tampilannya berubah setelah dibuka ulang?',
                        'petunjuk' => 'Pakai nama file asli yang kalian buat sendiri, bukan contoh.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 4 ============================
            [
                'order' => 4,
                'slug' => 'save-vs-save-as',
                'title' => 'Ronde 4 — Perintah Save vs Save As',
                'difficulty' => 'Sedang',
                'xp' => 110,
                'story' => 'Banyak agen pemula kehilangan dokumen aslinya karena salah menekan Save. Kalian harus '
                    .'memahami perbedaan keduanya sebelum menyentuh arsip utama.',
                'objective' => 'Membedakan fungsi Save dan Save As serta memahami risiko menimpa file asli.',
                'instructions' => [
                    'Buat dokumen baru, tulis satu kalimat, simpan dengan nama "latihan-1".',
                    'Ubah isinya, lalu tekan Ctrl + S (Save). Perhatikan nama file di bagian atas.',
                    'Ubah lagi, kali ini pakai Save As dengan nama "latihan-2".',
                    'Amati: sekarang ada berapa file di folder kalian?',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 4.',
                'hint_1' => 'Perhatikan nama file di judul jendela setelah menyimpan.',
                'hint_2' => 'Save As selalu meminta nama dan lokasi baru.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Apa perbedaan Save dan Save As? Jawab satu kalimat.',
                        'petunjuk' => 'Kata kunci: Save menimpa file yang sama, Save As membuat file baru.',
                    ],
                    [
                        'pertanyaan' => 'Apa risiko memakai Save pada dokumen asli milik guru? Jawab singkat.',
                        'petunjuk' => 'Pikirkan file aslinya yang tertimpa dan tidak bisa dikembalikan.',
                    ],
                    [
                        'pertanyaan' => 'Tulis langkah menyimpan dokumen guru tanpa merusak file aslinya.',
                        'petunjuk' => 'Cukup sebutkan perintah yang benar saat menyimpan.',
                    ],
                    [
                        'pertanyaan' => 'Tadi kamu membuat "latihan-1" dan "latihan-2". Setelah semua langkah, ada berapa file di folder kalian dan apa saja namanya?',
                        'petunjuk' => 'Jawab sesuai yang benar-benar kamu lihat di folder komputermu.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 5 ============================
            [
                'order' => 5,
                'slug' => 'mengelola-dokumen',
                'title' => 'Ronde 5 — Mengelola Dokumen: New, Open, Close, Print',
                'difficulty' => 'Mudah',
                'xp' => 100,
                'story' => 'Markas besar meminta setiap agen bisa mengelola arsip dengan rapi: membuka yang benar, '
                    .'menutup yang sudah selesai, dan mencetak tepat waktu.',
                'objective' => 'Memahami perintah dasar pengelolaan dokumen: New, Open, Close, Print, serta '
                    .'membedakan Close dan Exit.',
                'instructions' => [
                    'Buka menu File pada aplikasi pengolah kata.',
                    'Perhatikan perintah New, Open, Save, Save As, Close, dan Print.',
                    'Buka dua dokumen berbeda sekaligus, lalu tutup salah satu dengan Close.',
                    'Tutup semuanya, lalu bandingkan dengan menekan tombol X di pojok jendela.',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 5.',
                'hint_1' => 'Close menutup dokumen, Exit menutup seluruh aplikasi.',
                'hint_2' => 'New membuat dokumen kosong, Open membuka dokumen yang sudah ada.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Apa bedanya perintah New dan Open? Jawab singkat.',
                        'petunjuk' => 'Bandingkan: mulai dari nol vs melanjutkan pekerjaan lama.',
                    ],
                    [
                        'pertanyaan' => 'Apa bedanya Close dan Exit? Jawab satu kalimat.',
                        'petunjuk' => 'Perhatikan jumlah dokumen yang tertutup.',
                    ],
                    [
                        'pertanyaan' => 'Apa yang terjadi jika dokumen ditutup tanpa disimpan?',
                        'petunjuk' => 'Ceritakan tentang dialog peringatan yang muncul.',
                    ],
                    [
                        'pertanyaan' => 'Saat kalian menutup aplikasi tadi, apakah muncul peringatan menyimpan? Tulis kalimat peringatannya jika kamu ingat.',
                        'petunjuk' => 'Jawab dari pengalamanmu sendiri di komputer.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 6 ============================
            [
                'order' => 6,
                'slug' => 'membuat-laporan',
                'title' => 'Ronde 6 — Membuat Laporan yang Rapi',
                'difficulty' => 'Sulit',
                'xp' => 130,
                'story' => 'Semua data sudah terkumpul. Sekarang tim harus menyusunnya menjadi satu laporan resmi '
                    .'yang layak dibaca pimpinan.',
                'objective' => 'Menggabungkan teks, tabel, gambar, header, footer, dan nomor halaman menjadi '
                    .'laporan yang rapi.',
                'instructions' => [
                    'Buat dokumen baru, tulis judul laporan dan nama kelompok kalian.',
                    'Tambahkan satu paragraf penjelasan singkat.',
                    'Sisipkan tabel sederhana lewat Insert → Table.',
                    'Tambahkan Header berisi nama kelompok dan Footer berisi nama sekolah.',
                    'Tambahkan nomor halaman di bagian bawah dokumen.',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 6.',
                'hint_1' => 'Header dan Footer ada di menu Insert.',
                'hint_2' => 'Nomor halaman biasanya ada di Insert → Page Number.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Apa manfaat Header dan nomor halaman pada sebuah laporan? Sebutkan satu.',
                        'petunjuk' => 'Semua halaman jadi terurut dan jelas milik siapa.',
                    ],
                    [
                        'pertanyaan' => 'Sebutkan tiga hal yang perlu diatur agar tabel di laporan tetap rapi.',
                        'petunjuk' => 'Contoh: lebar kolom, garis tabel, posisi tabel.',
                    ],
                    [
                        'pertanyaan' => 'Apa itu technical writing? Jawab satu kalimat.',
                        'petunjuk' => 'Kata kunci: menulis informasi teknis secara jelas dan runtut.',
                    ],
                    [
                        'pertanyaan' => 'Tulis judul laporan yang kalian buat tadi, dan bagian mana yang paling sulit dikerjakan. Mengapa?',
                        'petunjuk' => 'Sebut judul asli buatan kelompokmu, bukan contoh.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 7 ============================
            [
                'order' => 7,
                'slug' => 'merangkum-konten',
                'title' => 'Ronde 7 — Merangkum Narasi dari Konten Digital',
                'difficulty' => 'Sedang',
                'xp' => 120,
                'story' => 'Sebuah bacaan digital panjang masuk ke meja tim. Tidak ada waktu membacanya seluruhnya, '
                    .'jadi kalian harus mahir merangkum.',
                'objective' => 'Merangkum narasi dari konten digital dan membaca data statistik sederhana.',
                'instructions' => [
                    'Baca satu bacaan digital yang disediakan guru.',
                    'Tandai gagasan utama setiap paragraf.',
                    'Catat angka penting bila ada (data statistik).',
                    'Susun ringkasan maksimal tiga kalimat.',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 7.',
                'hint_1' => 'Gagasan utama biasanya ada di kalimat pertama atau terakhir paragraf.',
                'hint_2' => 'Ringkasan yang baik memakai kalimat sendiri, bukan menyalin utuh.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Bagaimana caramu menemukan gagasan utama sebuah paragraf? Jawab singkat.',
                        'petunjuk' => 'Cukup sebutkan langkahnya, mis. melihat kalimat pertama.',
                    ],
                    [
                        'pertanyaan' => 'Apa bedanya merangkum dan menyalin? Jawab satu kalimat.',
                        'petunjuk' => 'Kata kunci: memakai kalimat sendiri.',
                    ],
                    [
                        'pertanyaan' => 'Mengapa sumber bacaan digital perlu diperiksa kebenarannya? Sebutkan satu alasan.',
                        'petunjuk' => 'Pikirkan tentang berita bohong di internet.',
                    ],
                    [
                        'pertanyaan' => 'Tulis satu kalimat ringkasan dari bacaan yang tadi kalian baca, dengan kalimatmu sendiri.',
                        'petunjuk' => 'Satu kalimat saja, jangan menyalin utuh dari bacaan.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 8 ============================
            [
                'order' => 8,
                'slug' => 'laboratorium-maya',
                'title' => 'Ronde 8 — Mengenal Laboratorium Maya',
                'difficulty' => 'Sedang',
                'xp' => 120,
                'story' => 'Laboratorium digital telah dibuka. Tanpa peralatan fisik, tim kalian harus bisa '
                    .'melakukan percobaan dan membaca hasilnya.',
                'objective' => 'Memahami pengertian laboratorium maya (virtual lab) dan contoh pemanfaatannya.',
                'instructions' => [
                    'Buka simulasi daring yang disediakan guru (mis. PhET).',
                    'Jalankan satu percobaan sederhana.',
                    'Ubah satu nilai input dan amati perubahannya.',
                    'Catat nama simulasi yang kalian buka, karena ditanyakan di soal 4.',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 8.',
                'hint_1' => 'Laboratorium maya = simulasi percobaan lewat komputer atau internet.',
                'hint_2' => 'Contohnya simulasi PhET untuk listrik, gaya, atau gelombang.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Apa itu laboratorium maya? Jawab satu kalimat.',
                        'petunjuk' => 'Kata kunci: percobaan dijalankan lewat komputer, bukan alat fisik.',
                    ],
                    [
                        'pertanyaan' => 'Sebutkan dua keunggulan laboratorium maya dibanding laboratorium fisik.',
                        'petunjuk' => 'Pikirkan soal biaya, keamanan, dan kemudahan mengulang percobaan.',
                    ],
                    [
                        'pertanyaan' => 'Apa satu keterbatasan laboratorium maya? Jawab singkat.',
                        'petunjuk' => 'Pikirkan hal yang tidak bisa disimulasikan komputer.',
                    ],
                    [
                        'pertanyaan' => 'Tulis nama simulasi yang tadi kalian buka dan satu nilai yang kalian ubah. Apa yang berubah setelah diubah?',
                        'petunjuk' => 'Jawab dari percobaanmu sendiri, bukan dari bacaan.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 9 ============================
            [
                'order' => 9,
                'slug' => 'lab-maya-vs-fisik',
                'title' => 'Ronde 9 — Input, Process, Output pada Simulasi',
                'difficulty' => 'Sulit',
                'xp' => 130,
                'story' => 'Pimpinan markas ingin laporan yang tajam. Kalian harus bisa membongkar cara kerja '
                    .'simulasi dari sisi input, process, dan output.',
                'objective' => 'Memahami konsep input-process-output pada sebuah simulasi dan membandingkan '
                    .'laboratorium maya dengan laboratorium fisik.',
                'instructions' => [
                    'Buka kembali simulasi laboratorium maya yang kamu pakai sebelumnya.',
                    'Identifikasi INPUT: apa saja yang kamu atur?',
                    'Identifikasi PROCESS: apa yang dihitung sistem?',
                    'Identifikasi OUTPUT: apa hasil yang muncul di layar?',
                    'Kode rahasia ronde ini ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia Ronde 9.',
                'hint_1' => 'Urutannya: sesuatu masuk (input) → diproses (process) → menghasilkan (output).',
                'hint_2' => 'Pada simulasi listrik: tegangan dan hambatan adalah input, arus adalah output.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Sebutkan satu perbedaan laboratorium maya dan laboratorium fisik. Cukup satu.',
                        'petunjuk' => 'Boleh dari segi biaya, keamanan, atau akses.',
                    ],
                    [
                        'pertanyaan' => 'Dari simulasi tadi, apa saja yang termasuk INPUT?',
                        'petunjuk' => 'Ingat nilai apa yang kamu ubah sebelum percobaan berjalan.',
                    ],
                    [
                        'pertanyaan' => 'Apa OUTPUT yang muncul pada simulasi itu?',
                        'petunjuk' => 'Sesuatu yang tampil di layar setelah input diubah.',
                    ],
                    [
                        'pertanyaan' => 'Ketika kalian mengubah salah satu nilai input, apa yang terjadi pada output-nya? Jawab satu kalimat.',
                        'petunjuk' => 'Ceritakan perubahan yang benar-benar kamu amati di layar.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],

            // ============================ RONDE 10 ===========================
            [
                'order' => 10,
                'slug' => 'keamanan-digital',
                'title' => 'Ronde 10 — Keamanan Dunia Maya & Refleksi Akhir',
                'difficulty' => 'Sulit',
                'xp' => 150,
                'story' => 'Misi terakhir. Seluruh keterampilan yang kalian pelajari harus dipakai untuk menjaga '
                    .'dunia digital tetap aman. Ini ujian pamungkas tim.',
                'objective' => 'Memahami keamanan dunia maya dan menyimpulkan seluruh materi Bab 3.',
                'instructions' => [
                    'Diskusikan dengan kelompokmu: bahaya apa saja yang ada di dunia maya?',
                    'Baca materi keamanan dunia maya yang disediakan guru.',
                    'Bayangkan kalian ingin membuat simulasi sederhana sendiri.',
                    'Tentukan input, process, dan output-nya.',
                    'Kode rahasia terakhir ada di layar guru.',
                ],
                'code_prompt' => 'Masukkan kode rahasia penutup Ronde 10.',
                'hint_1' => 'Keamanan dunia maya mencakup data pribadi, kata sandi, dan penipuan daring.',
                'hint_2' => 'Untuk merancang simulasi: apa yang diatur, apa yang dihitung, apa hasilnya.',
                'reflection_question' => null,
                'questions' => [
                    [
                        'pertanyaan' => 'Sebutkan dua bahaya di dunia maya dan cara mencegahnya. Tulis singkat.',
                        'petunjuk' => 'Contoh: pencurian data, penipuan daring, perundungan siber.',
                    ],
                    [
                        'pertanyaan' => 'Mengapa data pribadi tidak boleh dibagikan sembarangan? Jawab satu kalimat.',
                        'petunjuk' => 'Pikirkan siapa yang bisa menyalahgunakan data itu.',
                    ],
                    [
                        'pertanyaan' => 'Jika membuat simulasi sederhana, apa input dan output-nya? Tulis singkat.',
                        'petunjuk' => 'Pilih topik sederhana, mis. simulasi menghitung nilai rata-rata.',
                    ],
                    [
                        'pertanyaan' => 'Dari 10 ronde ini, bagian mana yang paling berguna bagimu? Jelaskan satu alasan singkat.',
                        'petunjuk' => 'Jawab dengan pengalamanmu sendiri selama mengerjakan ronde-ronde ini.',
                    ],
                ],
                'requires_pdf' => false,
                'is_active' => true,
            ],
        ];
    }
}
