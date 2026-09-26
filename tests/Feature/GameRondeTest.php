<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;
use App\Services\RoundService;
use Database\Seeders\Bab3MateriSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Game arcade belajar: soal menjadi mekanik permainan.
 */
class GameRondeTest extends TestCase
{
    use RefreshDatabase;

    protected GameSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        // AI dimatikan karena fokus tes ini pada mekanik game.
        config()->set('ai.enabled', false);

        $this->seed(Bab3MateriSeeder::class);

        $this->session = GameSession::query()->where('code', 'TIK8-DEMO')->firstOrFail();
    }

    protected function joinTeam(string $nama = 'Tim Game'): Team
    {
        $this->post('/student/join', [
            'code' => 'TIK8-DEMO',
            'team_name' => $nama,
            'members' => ['Ana'],
        ]);

        return Team::query()->where('name', $nama)->firstOrFail();
    }

    /** Buka ronde tertentu untuk tim lewat RoundService. */
    protected function bukaRonde(int $order): Mission
    {
        $rounds = app(RoundService::class);
        $rounds->start($this->session, $order, 10);

        return Mission::query()->where('order', $order)->firstOrFail();
    }

    /**
     * Susun daftar jawaban game seperti yang dikirim browser.
     *
     * Server menilai sendiri memakai kunci misi, jadi tes ini hanya menentukan
     * BERAPA soal pertama dijawab benar; sisanya sengaja dijawab salah.
     * Angka benar/salah/skor TIDAK pernah dikirim klien.
     *
     * @return array<int, array{pertanyaan: string, pilihan: string}>
     */
    protected function jawabanGame(Mission $mission, int $benar): array
    {
        return collect($mission->gameQuestionList())
            ->values()
            ->map(function (array $q, int $i) use ($benar) {
                $kunci = (int) $q['jawaban'];
                $pilihan = $i < $benar
                    ? $q['pilihan'][$kunci]
                    : $q['pilihan'][($kunci + 1) % count($q['pilihan'])];

                return ['pertanyaan' => $q['pertanyaan'], 'pilihan' => (string) $pilihan];
            })
            ->all();
    }

    /** Kirim hasil permainan: `$benar` soal pertama benar, sisanya salah. */
    protected function mainkan(Mission $mission, int $benar): TestResponse
    {
        return $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => $this->jawabanGame($mission, $benar),
        ]);
    }

    // -----------------------------------------------------------------
    // DATA RONDE & GAME
    // -----------------------------------------------------------------

    public function test_setiap_ronde_punya_game(): void
    {
        $missions = Mission::query()->active()->ordered()->get();

        $this->assertCount(7, $missions);

        foreach ($missions as $mission) {
            $this->assertTrue(
                $mission->hasGame(),
                "Ronde {$mission->order} seharusnya punya game."
            );
        }
    }

    public function test_setiap_ronde_punya_delapan_soal_game(): void
    {
        // Delapan soal per ronde: satu kali bermain harus terasa seperti kuis
        // beberapa soal, bukan tamat setelah satu jawaban benar.
        foreach (Mission::query()->active()->ordered()->get() as $mission) {
            $this->assertSame(
                8,
                $mission->gameQuestionCount(),
                "Ronde {$mission->order} seharusnya punya 8 soal game."
            );
        }
    }

    public function test_kunci_jawaban_game_tidak_selalu_pilihan_pertama(): void
    {
        // Kalau jawaban benar selalu di pilihan pertama, anak menghafal posisi
        // tombol tanpa membaca soalnya.
        foreach (Mission::query()->active()->get() as $mission) {
            $posisi = array_map(
                fn (array $q) => (int) $q['jawaban'],
                $mission->gameQuestionList()
            );

            $this->assertGreaterThanOrEqual(
                2,
                count(array_unique($posisi)),
                "Ronde {$mission->order}: posisi jawaban benar terlalu seragam."
            );
        }
    }

    public function test_game_berbeda_antar_ronde(): void
    {
        $jenisGame = Mission::query()->active()->pluck('game_type')->unique()->values()->all();

        // Harus memakai lebih dari satu jenis game (bervariasi).
        $this->assertGreaterThanOrEqual(2, count($jenisGame), 'Jenis game kurang bervariasi.');

        // Semua jenis game harus dikenali sistem.
        foreach ($jenisGame as $jenis) {
            $this->assertArrayHasKey($jenis, Mission::GAMES);
        }

        // Ketiga jenis game yang didukung harus muncul.
        foreach (['snake', 'breakout', 'flappy'] as $wajib) {
            $this->assertContains($wajib, $jenisGame, "Game '{$wajib}' belum dipakai.");
        }
    }

    public function test_soal_game_punya_kunci_jawaban_yang_valid(): void
    {
        foreach (Mission::query()->active()->get() as $mission) {
            foreach ($mission->gameQuestionList() as $i => $q) {
                $this->assertNotEmpty($q['pertanyaan']);
                $this->assertGreaterThanOrEqual(2, count($q['pilihan']));

                // Kunci jawaban harus menunjuk pilihan yang benar-benar ada.
                $this->assertGreaterThanOrEqual(0, $q['jawaban']);
                $this->assertLessThan(
                    count($q['pilihan']),
                    $q['jawaban'],
                    'Soal '.($i + 1)." ronde {$mission->order} punya kunci jawaban di luar jangkauan."
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // HALAMAN GAME
    // -----------------------------------------------------------------

    public function test_halaman_game_dapat_dibuka_siswa(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->get('/student/mission/'.$mission->id.'/game');

        $response->assertOk();
        $response->assertSee('game-root', false);
        $response->assertSee('game-canvas', false);
        $response->assertSee('Mulai Bermain');
        $response->assertSee('stat-nyawa', false);
    }

    public function test_halaman_game_memuat_soal_untuk_mesin_permainan(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->get('/student/mission/'.$mission->id.'/game');

        $response->assertOk();
        // Soal dikirim sebagai JSON untuk dibaca resources/js/game.js.
        $response->assertSee('game-questions', false);

        $html = $response->getContent();
        $this->assertStringContainsString('"pertanyaan"', $html);
    }

    public function test_halaman_game_menyediakan_panggung_layar_penuh(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->get('/student/mission/'.$mission->id.'/game');

        $response->assertOk();

        // Panggung (elemen yang dimasukkan mode layar penuh).
        $response->assertSee('id="game-stage"', false);
        $response->assertSee('tik-game-stage', false);

        // Tombol untuk masuk/keluar layar penuh.
        $response->assertSee('id="btn-keluar-fullscreen"', false);
        $response->assertSee('Layar Penuh');
    }

    public function test_panel_soal_berada_di_dalam_panggung_layar_penuh(): void
    {
        // Ini penting: bila panel soal berada DI LUAR panggung, soal tidak
        // akan terlihat saat mode layar penuh — anak hanya melihat papan game.
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $html = $this->get('/student/mission/'.$mission->id.'/game')->getContent();

        $posisiStage = strpos($html, 'id="game-stage"');
        $posisiSoal = strpos($html, 'id="panel-soal"');
        $posisiKanvas = strpos($html, 'id="game-canvas"');

        $this->assertNotFalse($posisiStage, 'Panggung game harus ada.');
        $this->assertNotFalse($posisiSoal, 'Panel soal harus ada.');
        $this->assertNotFalse($posisiKanvas, 'Kanvas game harus ada.');

        // Panel soal harus muncul SETELAH panggung dibuka.
        $this->assertGreaterThan($posisiStage, $posisiSoal, 'Panel soal harus di dalam panggung.');

        // Dan tidak boleh ada penutup panggung di antara keduanya.
        $antar = substr($html, $posisiStage, $posisiSoal - $posisiStage);
        $this->assertStringContainsString('tik-game-layout', $antar, 'Panel soal harus berada di dalam tata letak panggung.');

        // Kanvas juga harus di dalam panggung (sebelum panel soal).
        $this->assertLessThan($posisiSoal, $posisiKanvas, 'Kanvas harus berada sebelum panel soal di dalam panggung.');
    }

    public function test_skrip_game_memuat_logika_layar_penuh(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Normalisasi akhir baris supaya pencarian batas fungsi tetap akurat.
        $js = str_replace(["\r\n", "\r"], "\n", $js);

        // Harus ada panggilan untuk MASUK layar penuh saat mulai bermain.
        $this->assertStringContainsString('requestFullscreen', $js);

        // Harus ada panggilan untuk KELUAR layar penuh (lewat tombol panggung).
        $this->assertStringContainsString('exitFullscreen', $js);

        // Layar penuh dipanggil dari fungsi mulaiMain (aksi tekanan tombol).
        $this->assertMatchesRegularExpression(
            '/function mulaiMain\(\).*?masukLayarPenuh\(\)/s',
            $js,
            'Fungsi mulaiMain harus memanggil masukLayarPenuh().'
        );

        // Permainan selesai TIDAK boleh menutup layar penuh otomatis. Ronde game
        // umumnya hanya berisi satu soal, sehingga penutupan otomatis membuat
        // anak terlempar dari mode bermain tepat saat menekan jawaban.
        $mulaiSelesai = strpos($js, 'function selesai(');
        $this->assertNotFalse($mulaiSelesai, 'Fungsi selesai() harus ada.');

        // Batas fungsi: baris berikutnya yang ditutup tepat pada indentasi 8 spasi.
        $akhirSelesai = strpos($js, "\n        }\n", $mulaiSelesai);
        $this->assertNotFalse($akhirSelesai, 'Penutup fungsi selesai() tidak ditemukan.');

        $badanSelesai = substr($js, $mulaiSelesai, $akhirSelesai - $mulaiSelesai);

        $this->assertStringNotContainsString(
            'keluarLayarPenuh()',
            $badanSelesai,
            'Fungsi selesai() tidak boleh memanggil keluarLayarPenuh() secara otomatis.'
        );

        // Tombol panggung tetap menjadi satu-satunya cara keluar layar penuh.
        $this->assertMatchesRegularExpression(
            '/btnKeluarFs\.addEventListener\([\s\S]*?keluarLayarPenuh\(\)/',
            $js,
            'Tombol layar penuh harus memanggil keluarLayarPenuh().'
        );
    }

    public function test_skrip_game_selesai_saat_soal_habis_dijawab(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Permainan harus berakhir saat semua soal sudah dijawab benar,
        // bukan mengulang soal yang sama tanpa henti.
        $this->assertStringContainsString('Semua soal selesai', $js);

        // Daftar soal harus disiapkan ulang SETIAP ronde di mulaiMain(), supaya
        // ronde baru tidak langsung tamat karena sisa daftar dari ronde sebelumnya.
        $this->assertMatchesRegularExpression(
            '/function mulaiMain\([\s\S]*?tumpukan = acak\(soal\);/',
            $js,
            'mulaiMain() harus menyiapkan ulang daftar soal tiap ronde.'
        );

        // Dan daftar itu tidak boleh diisi ulang saat habis: soal yang sudah
        // dijawab benar tidak muncul lagi (tidak berputar tanpa henti).
        $this->assertSame(
            1,
            substr_count($js, 'tumpukan = acak(soal);'),
            'Daftar soal hanya boleh diisi di mulaiMain(), bukan diisi ulang di ambilSoal().'
        );

        // Jawaban salah menawarkan soal yang SAMA lagi, bukan menghabiskan soal.
        $this->assertMatchesRegularExpression(
            '/function jawab\([\s\S]*?gambarSoal\(\);/',
            $js,
            'Jawaban salah harus menampilkan ulang soal yang sama lewat gambarSoal().'
        );
    }

    // -----------------------------------------------------------------
    // REGRESI: kehabisan nyawa TIDAK boleh mengakhiri ronde
    // -----------------------------------------------------------------
    // Dulu jawaban salah / menabrak mengurangi nyawa (cuma 3) dan saat nyawa
    // habis ronde langsung "selesai", padahal sebagian besar soal belum
    // dikerjakan. Sekarang nyawa diisi ulang dan ronde hanya berakhir bila
    // SEMUA soal sudah dijawab benar.

    public function test_kehabisan_nyawa_tidak_mengakhiri_permainan(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Nyawa habis -> isi ulang, bukan selesai().
        $this->assertStringContainsString('isiUlangNyawa', $js);

        // Nyawa habis tidak boleh memanggil selesai().
        $this->assertDoesNotMatchRegularExpression(
            '/nyawa <= 0[\s\S]{0,80}?selesai\(/',
            $js,
            'Kehabisan nyawa tidak boleh memanggil selesai(); ronde hanya '
            .'selesai setelah semua soal dijawab benar.'
        );

        // Pesan "nyawa habis" harus menyatakan permainan tetap lanjut.
        $this->assertStringContainsString('Nyawa habis, diisi ulang', $js);
        $this->assertStringContainsString('permainan tetap lanjut', $js);
    }

    public function test_permainan_dibekukan_saat_soal_terbuka(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Simulasi hanya melangkah bila soal TIDAK sedang terbuka, supaya
        // anak bisa membaca/mendiskusikan soal tanpa karakternya menabrak.
        $this->assertMatchesRegularExpression(
            '/if \(!soalTerbuka\) \{\s*\n\s*akumulasi \+= delta;/',
            $js,
            'loop() harus berhenti melangkah selama soal terbuka.'
        );

        // Jeda beku harus SINGKAT: 2,5 detik dulu membuat permainan terasa
        // berhenti terus setiap kali ganti soal.
        $this->assertStringNotContainsString('}, 2500);', $js);

        // Dan soal harus menampilkan nomor urut / progres supaya anak tahu
        // masih ada berapa soal lagi.
        $this->assertStringContainsString('dari ', $js);
    }

    public function test_langkah_game_memakai_akumulator_waktu_tetap(): void
    {
        // REGRESI: ambang `waktu - waktuLangkah >= intervalLangkah` menggeser
        // patokan ke frame terakhir TANPA menyimpan sisa waktu, sehingga di
        // monitor 120/144 Hz langkah tidak segaris dengan frame — gerakan
        // patah-patah dan input terasa telat di PC baru.
        $js = file_get_contents(resource_path('js/game.js'));

        $this->assertStringNotContainsString('waktu - waktuLangkah >=', $js);
        $this->assertStringContainsString('akumulasi += delta;', $js);
        $this->assertStringContainsString('akumulasi -= intervalLangkah;', $js);
    }

    public function test_permainan_tidak_beku_terus_menerus(): void
    {
        // REGRESI: versi sebelumnya men-set `soalTerbuka = true` saat soal
        // muncul dan TIDAK pernah melepasnya, sehingga permainan tidak pernah
        // bergerak sama sekali ("game tidak jalan").
        $js = file_get_contents(resource_path('js/game.js'));

        // Beku harus DILEPAS otomatis (ada setTimeout yang mengeset false),
        // bukan hanya di-set true.
        $this->assertMatchesRegularExpression(
            '/setTimeout\([\s\S]{0,200}?soalTerbuka = false;/',
            $js,
            'Beku saat soal muncul harus dilepas otomatis lewat setTimeout().'
        );
    }

    public function test_nomor_soal_dihitung_dari_posisi_bukan_jawaban_benar(): void
    {
        // REGRESI: label soal dulu memakai (benar + 1), sehingga saat anak
        // menjawab SALAH angkanya tidak maju / salah. Sekarang harus dihitung
        // dari posisi soal lewat counter `nomorSoal`.
        $js = file_get_contents(resource_path('js/game.js'));

        $this->assertStringContainsString('nomorSoal += 1', $js);
        $this->assertStringContainsString("'Soal ' + Math.min(nomorSoal, soal.length)", $js);

        // Tidak boleh lagi memakai (benar + 1) sebagai nomor soal.
        $this->assertStringNotContainsString("'[' + (benar + 1) + ' dari '", $js);
    }

    public function test_permainan_dihentikan_guru_tidak_mengaku_ronde_selesai(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Ada jalur terpisah untuk "dihentikan" guru/waktu.
        $this->assertStringContainsString('dihentikan(', $js);
        $this->assertStringContainsString('Permainan dihentikan', $js);

        // Label "ronde selesai" tidak boleh dipakai untuk kasus dihentikan:
        // judul panel diganti saat dihentikan.
        $this->assertStringContainsString(
            "elHasilJudul.textContent = 'Permainan dihentikan'",
            $js
        );
    }

    public function test_pesan_hasil_tidak_menulis_selesai_saat_dihentikan(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // saat dihentikan, panel TIDAK memakai judul "Ronde selesai".
        $this->assertStringNotContainsString(
            "elHasilJudul.textContent = 'Ronde selesai'",
            $js
        );

        // dan tidak memulai hitung mundur pindah ke soal uraian.
        $this->assertMatchesRegularExpression(
            '/if \(!dihentikanGuru\) \{\s*\n\s*mulaiHitungMundur\(10\);/',
            $js
        );
    }

    public function test_gaya_layar_penuh_tersedia_di_css(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('tik-game-stage:fullscreen', $css);
    }

    public function test_ronde_tanpa_game_diarahkan_ke_halaman_misi(): void
    {
        $this->joinTeam();

        // Buat misi tanpa game.
        $mission = Mission::query()->create([
            'order' => 90,
            'title' => 'Ronde Tanpa Game',
            'slug' => 'ronde-tanpa-game',
            'story' => 's',
            'objective' => 'o',
            'instructions' => ['x'],
            'questions' => [['pertanyaan' => 'Soal?', 'jenis' => 'materi']],
            'xp' => 50,
            'is_active' => true,
        ]);

        $response = $this->get('/student/mission/'.$mission->id.'/game');

        $response->assertRedirect(route('student.mission.show', $mission));
        $response->assertSessionHas('error');
    }

    public function test_halaman_game_butuh_login_kelompok(): void
    {
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $this->get('/student/mission/'.$mission->id.'/game')
            ->assertRedirect(route('student.join'));
    }

    // -----------------------------------------------------------------
    // PENCATATAN JAWABAN GAME
    // -----------------------------------------------------------------

    public function test_jawaban_salah_dicatat_untuk_guru(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $soal = $mission->gameQuestionList()[0];
        $salah = $soal['pilihan'][($soal['jawaban'] + 1) % count($soal['pilihan'])];

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => [
                ['pertanyaan' => $soal['pertanyaan'], 'pilihan' => $salah],
            ],
        ])->assertOk();

        $submission = Submission::query()->firstOrFail();

        $this->assertNotNull($submission->game_missed);
        $this->assertCount(1, $submission->game_missed);
        $this->assertSame($soal['pertanyaan'], $submission->game_missed[0]['pertanyaan']);
    }

    public function test_jawaban_benar_tidak_dicatat_sebagai_kesalahan(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $soal = $mission->gameQuestionList()[0];
        $benar = $soal['pilihan'][$soal['jawaban']];

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => [
                ['pertanyaan' => $soal['pertanyaan'], 'pilihan' => $benar],
            ],
        ])->assertOk();

        $submission = Submission::query()->first();

        $this->assertNotNull($submission);
        $this->assertSame(1, $submission->game_correct);
        $this->assertTrue(empty($submission->game_missed));
    }

    // -----------------------------------------------------------------
    // PAPAN HASIL: PERAYAAN & ARAHAN KE SOAL URAIAN
    // -----------------------------------------------------------------

    public function test_papan_hasil_berada_di_dalam_panggung_dan_mengarah_ke_soal_uraian(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $html = $this->get('/student/mission/'.$mission->id.'/game')->getContent();

        $panggung = strpos($html, 'id="game-stage"');
        $hasil = strpos($html, 'id="game-hasil"');
        $caraMain = strpos($html, 'CARA MAIN');

        $this->assertNotFalse($hasil, 'Papan hasil game harus ada di halaman game.');

        // Papan hasil harus DI DALAM panggung layar penuh, bukan di bawahnya:
        // kalau di luar, anak yang bermain layar penuh tidak melihat hasilnya.
        $this->assertGreaterThan($panggung, $hasil, 'Papan hasil harus berada di dalam panggung.');
        $this->assertLessThan($caraMain, $hasil, 'Papan hasil harus berada di dalam panggung.');

        // Tombol utama mengarahkan ke soal uraian ronde ini.
        $this->assertStringContainsString('id="hasil-lanjut"', $html);
        $this->assertStringContainsString(route('student.mission.show', $mission), $html);
        $this->assertStringContainsString('Soal Uraian', $html);

        // Dan menyediakan tombol bermain ulang, bukan langsung memaksa pindah.
        $this->assertStringContainsString('id="hasil-ulang"', $html);
    }

    public function test_skrip_game_merayakan_hasil_dan_menampilkan_xp_yang_didapat(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Perayaan: bintang sesuai akurasi + konfeti (tanpa pustaka luar).
        $this->assertStringContainsString('hasil-bintang', $js);
        $this->assertStringContainsString('hujanKonfeti', $js);
        $this->assertStringContainsString('tik-confetti', $js);

        // XP dari server ditampilkan, bukan hanya disimpan diam-diam.
        $this->assertStringContainsString('xp_gain', $js);
        $this->assertStringContainsString('total_xp', $js);

        // Anak diarahkan ke soal uraian: tombol + hitung mundur.
        $this->assertStringContainsString('hasil-lanjut', $js);
        $this->assertStringContainsString('mulaiHitungMundur', $js);

        // Konfeti juga harus punya gayanya, dan dihormati bila anak sensitif
        // terhadap gerakan.
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('tik-confetti', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_papan_hasil_benar_benar_berada_di_dalam_pembungkus_papan(): void
    {
        // Posisi papan hasil bergantung pada STRUKTUR DOM, bukan urutan teks di
        // file. Kalau ada satu tag penutup yang berlebih/kurang, browser akan
        // mengeluarkan elemen ini dari .tik-papan sehingga hasilnya jatuh ke
        // BAWAH papan permainan walaupun CSS-nya benar.
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $html = $this->get('/student/mission/'.$mission->id.'/game')->getContent();

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $doc->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $hasil = $xpath->query('//*[@id="game-hasil"]')->item(0);
        $kanvas = $xpath->query('//*[@id="game-canvas"]')->item(0);

        $this->assertNotNull($hasil, 'Papan hasil harus ada di halaman game.');
        $this->assertNotNull($kanvas, 'Kanvas game harus ada.');

        $pembungkusHasil = $xpath->query('ancestor::*[contains(@class, "tik-papan")]', $hasil)->item(0);
        $pembungkusKanvas = $xpath->query('ancestor::*[contains(@class, "tik-papan")]', $kanvas)->item(0);

        $this->assertNotNull($pembungkusHasil, 'Papan hasil harus berada di dalam .tik-papan.');
        $this->assertNotNull($pembungkusKanvas, 'Kanvas harus berada di dalam .tik-papan.');

        // Kelas .tik-hasil WAJIB terpasang di ELEMEN-nya, bukan hanya ada di
        // file CSS. Kalau kelasnya lupa dipasang, CSS lapisan tidak menempel dan
        // papan hasil jatuh mengalir DI BAWAH papan permainan.
        $this->assertStringContainsString(
            'tik-hasil',
            (string) $hasil->getAttribute('class'),
            'Elemen papan hasil harus memakai kelas .tik-hasil.'
        );

        // Keduanya harus berbagi pembungkus yang SAMA, supaya lapisan hasil
        // menempel tepat di atas papan permainan.
        $this->assertSame(
            $pembungkusKanvas->getNodePath(),
            $pembungkusHasil->getNodePath(),
            'Papan hasil dan kanvas harus berada di dalam pembungkus papan yang sama.'
        );
    }

    public function test_papan_hasil_menutupi_papan_permainan_tanpa_perlu_menggulir(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/game.js'));

        // Papan hasil menutupi PAPAN PERMAINAN, bukan seluruh layar: papan itu
        // sudah dilihat anak sejak mulai bermain, jadi hasilnya muncul di depan
        // mata tanpa perlu menggulir halaman.
        $this->assertStringContainsString('.tik-papan', $css);
        $this->assertMatchesRegularExpression('/\.tik-papan\s*\{[^}]*position:\s*relative;/s', $css);

        // Kartunya dipusatkan di dalam papan, dan LAPISANNYA yang bergulir bila
        // layarnya sangat pendek — bukan halaman.
        $this->assertStringContainsString('.tik-hasil-kartu', $css);
        $this->assertMatchesRegularExpression('/margin:\s*auto;/', $css);
        $this->assertMatchesRegularExpression('/\.tik-hasil\s*\{[^}]*overflow-y:\s*auto;/s', $css);

        // Visibilitas lewat atribut `hidden`, bukan kelas utility, supaya tidak
        // bentrok dengan display:flex milik lapisan itu.
        $this->assertStringContainsString('.tik-hasil[hidden]', $css);
        $this->assertStringContainsString('elHasil.hidden = false;', $js);
        $this->assertStringContainsString('elHasil.hidden = true;', $js);
        $this->assertStringNotContainsString("elHasil.classList.remove('hidden')", $js);

        // Harus ada jalan keluar tanpa menunggu hitungan mundur.
        $this->assertStringContainsString('hasil-tutup', $js);

        // Di HTML: papan hasil harus berada DI DALAM papan permainan — setelah
        // kanvas dan sebelum pesan permainan yang ada di bawah kanvas.
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $html = $this->get('/student/mission/'.$mission->id.'/game')->getContent();

        $kanvas = strpos($html, 'id="game-canvas"');
        $hasil = strpos($html, 'id="game-hasil"');
        $pesan = strpos($html, 'id="game-pesan"');

        $this->assertNotFalse($kanvas);
        $this->assertNotFalse($hasil);
        $this->assertNotFalse($pesan);
        $this->assertGreaterThan($kanvas, $hasil, 'Papan hasil harus berada di dalam papan permainan.');
        $this->assertLessThan($pesan, $hasil, 'Papan hasil harus menempel pada papan permainan, bukan di bawahnya.');
    }

    public function test_tombol_main_lagi_membatalkan_arahan_pindah_ronde(): void
    {
        // Bila anak memilih bermain ulang, hitung mundur ke soal uraian HARUS
        // dibatalkan — kalau tidak, dia dipindahkan di tengah permainan.
        $js = file_get_contents(resource_path('js/game.js'));

        $this->assertMatchesRegularExpression(
            '/btnHasilUlang\.addEventListener\([\s\S]*?batalHitungMundur\(\);[\s\S]*?mulaiMain\(\);/',
            $js,
            'Tombol Main Lagi harus membatalkan hitung mundur sebelum memulai ulang.'
        );
    }

    public function test_halaman_misi_mengingatkan_lanjut_ke_soal_uraian_setelah_main_game(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Mainkan sampai selesai (daftar jawaban terkirim).
        $this->mainkan($mission, 8)->assertOk();

        $response = $this->get('/student/mission/'.$mission->id);

        $response->assertOk();

        // Anak harus diberi tahu bahwa permainan hanya separuh ronde.
        $response->assertSee('Hasil Game Ronde Ini');
        $response->assertSee('soal uraian');
        $response->assertSee('Hasil Game Sudah Tercatat');

        // Dan TIDAK boleh diberi tahu bahwa jawabannya sudah terkirim:
        // yang tersimpan baru ringkasan permainan, soal uraian belum dijawab.
        $response->assertDontSee('Jawaban Terkirim');
    }

    public function test_ringkasan_permainan_menyimpan_hasil_dan_memberi_xp(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $totalSoal = $mission->gameQuestionCount();
        $response = $this->mainkan($mission, 7);

        $response->assertOk();
        // Server menilai sendiri: 7 benar dari 8 soal (sisanya dijawab salah).
        $response->assertJsonPath('benar', 7);
        $response->assertJsonPath('salah', $totalSoal - 7);

        // Papan hasil memerlukan tambahan XP dari permainan ini (bukan hanya
        // total misi) supaya anak melihat efek jawabannya.
        $response->assertJsonStructure(['xp', 'xp_gain', 'total_xp', 'skor']);
        $this->assertGreaterThan(0, $response->json('xp_gain'));
        $this->assertSame($response->json('xp'), $response->json('total_xp'));

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(7, $submission->game_correct);
        $this->assertSame($totalSoal - 7, $submission->game_wrong);
        // Skor dihitung server: 7 x poin-per-benar (bukan angka dari klien).
        $this->assertSame(7 * (int) config('tikmission.game_score_per_correct'), $submission->game_score);
        $this->assertNotNull($submission->game_played_at);

        // XP kelompok harus bertambah dari hasil game.
        $this->assertGreaterThan(0, $team->fresh()->xp);
    }

    public function test_skor_palsu_dari_klien_diabaikan_server(): void
    {
        // KEAMANAN: siswa tidak boleh bisa memalsukan hasil permainan.
        // Klien hanya boleh mengirim daftar pilihan; angka benar/salah/skor
        // apa pun yang ikut dikirim HARUS diabaikan.
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => $this->jawabanGame($mission, 1),
            // Angka palsu: dikirim sengaja untuk menguji apakah dipercaya.
            'benar' => 100,
            'salah' => 0,
            'skor' => 99999,
            'ringkasan' => true,
            'tepat' => true,
        ]);

        $response->assertOk();

        $submission = Submission::query()->firstOrFail();
        $totalSoal = $mission->gameQuestionCount();

        // Server harus melaporkan hasil penilaiannya SENDIRI, bukan angka klien.
        $this->assertSame(1, $submission->game_correct, 'Angka "benar" dari klien tidak boleh dipercaya.');
        $this->assertSame($totalSoal - 1, $submission->game_wrong);
        $this->assertSame(
            1 * (int) config('tikmission.game_score_per_correct'),
            $submission->game_score,
            'Skor harus dihitung server, bukan dari "skor" kiriman klien.'
        );
        $this->assertLessThan(100, $submission->game_correct);

        // XP tetap dibatasi XP maksimum misi, bukan dari skor palsu.
        $maksimum = $mission->xp + (int) config('tikmission.no_hint_bonus_xp');
        $this->assertLessThanOrEqual($maksimum, $team->fresh()->xp);
    }

    public function test_kunci_jawaban_game_dikirim_untuk_umpan_balik_langsung(): void
    {
        // Kunci jawaban IKUT dikirim supaya game.js bisa langsung menandai
        // benar/salah (nyawa & lanjut soal) tanpa menunggu jaringan. Skor tetap
        // 100% dinilai server — angka dari klien diabaikan (diuji di test lain),
        // jadi kunci yang terlihat di DevTools tidak bisa dipakai memalsukan XP.
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $html = $this->get('/student/mission/'.$mission->id.'/game')->getContent();

        $json = null;

        if (preg_match('/<script type="application\/json" id="game-questions">(.*?)<\/script>/s', $html, $m)) {
            $json = json_decode($m[1], true);
        }

        $this->assertIsArray($json, 'Data soal game harus dikirim sebagai JSON.');
        $this->assertNotEmpty($json);

        // Tiap soal berisi pertanyaan + pilihan + indeks jawaban yang valid.
        foreach ($json as $soal) {
            $this->assertArrayHasKey('pertanyaan', $soal);
            $this->assertArrayHasKey('pilihan', $soal);
            $this->assertArrayHasKey('jawaban', $soal);
            $this->assertGreaterThanOrEqual(0, $soal['jawaban']);
            $this->assertLessThan(
                count($soal['pilihan']),
                $soal['jawaban'],
                'Indeks kunci jawaban harus menunjuk salah satu pilihan.'
            );
        }
    }

    public function test_xp_game_tetap_terkirim_saat_ronde_dihentikan_atau_waktu_habis(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Ronde bisa berhenti saat anak masih bermain (guru menekan Hentikan
        // Ronde, timer ronde habis, atau waktu sesi habis). Hasil permainan
        // harus tetap dikirim pada saat itu, bukan menunggu anak menyelesaikan
        // semua soal — asal sudah menjawab minimal satu soal.
        $this->assertStringContainsString('awasiBerhentinyaRonde', $js);
        $this->assertStringContainsString('roundStatus', $js);
        $this->assertStringContainsString('Ronde dihentikan guru', $js);
        $this->assertStringContainsString('Waktu ronde habis', $js);
        $this->assertStringContainsString('Waktu sesi habis', $js);

        // Syaratnya: sudah menjawab sesuatu.
        $this->assertStringContainsString('catatanJawaban.length === 0', $js);
    }

    public function test_status_ronde_menyatakan_ronde_masih_boleh_dikerjakan(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->getJson('/student/round-status?mission='.$mission->id);

        $response->assertOk();
        $response->assertJsonPath('work_allowed', true);
    }

    public function test_status_ronde_menyatakan_ronde_tertutup_tidak_boleh_dikerjakan(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Guru menutup semua ronde.
        app(RoundService::class)->closeAllRounds($this->session);

        $response = $this->getJson('/student/round-status?mission='.$mission->id);

        $response->assertOk();
        $response->assertJsonPath('work_allowed', false);
    }

    public function test_timer_kelas_habis_tidak_menghentikan_kelompok_dengan_jatah_waktu_sendiri(): void
    {
        // Inilah bug yang dilaporkan: timer kelas & timer ronde sudah habis,
        // TETAPI server masih menerima pekerjaan kelompok yang baru mulai
        // (masuk terlambat). Permainan tidak boleh dihentikan lebih dulu oleh
        // klien hanya karena timer kelas mati.
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Jam kelas sudah lewat 2 jam, timer ronde juga sudah lewat.
        $this->session->update([
            'duration_minutes' => 60,
            'start_time' => now()->subHours(3),
            'round_started_at' => now()->subMinutes(30),
        ]);

        // Kelompok ini baru mulai mengerjakan ronde SETELAH jam kelas lewat.
        $progress = $team->progress()->where('mission_id', $mission->id)->firstOrFail();
        $progress->update(['work_started_at' => now()]);

        $response = $this->getJson('/student/round-status?mission='.$mission->id);

        $response->assertOk();
        // Timer ronde memang mati...
        $response->assertJsonPath('is_running', false);
        // ...tapi server masih menerima, jadi klien harus ikut mengizinkan.
        $response->assertJsonPath('work_allowed', true);
    }

    public function test_ringkasan_disimpan_dan_dikirim_ulang_bila_gagal_terkirim(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        // Gagal terkirim -> disimpan di browser, bukan dibuang.
        $this->assertStringContainsString('TikPendingGame?.save', $js);
        $this->assertStringContainsString('TikPendingGame?.clear', $js);

        // Tab ditutup di tengah permainan -> dititipkan lewat sendBeacon,
        // dengan cadangan di browser bila titipannya gagal.
        $this->assertStringContainsString('pagehide', $js);
        $this->assertStringContainsString('sendBeacon', $js);

        // Dicoba kirim ulang setiap halaman siswa dibuka.
        $this->assertStringContainsString('tik-game-pending-', $app);
        $this->assertStringContainsString('TikPendingGame.flush()', $app);

        // Halaman game harus memberi tahu mesin permainan ronde mana yang
        // sedang dipantau.
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $html = $this->get('/student/mission/'.$mission->id.'/game')->getContent();

        $this->assertStringContainsString('data-round-status', $html);
        $this->assertStringContainsString('data-mission-id', $html);
        $this->assertStringContainsString('data-mission-order="1"', $html);
    }

    public function test_jawaban_biasa_tetap_butuh_pertanyaan_dan_pilihan(): void
    {
        // Kiriman jawaban game harus berbentuk daftar {pertanyaan, pilihan}.
        // Tanpa itu, tidak ada yang bisa dinilai server.
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Tanpa kunci "jawaban" sama sekali.
        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'pilihan' => 'Hardware',
        ])->assertStatus(422);

        // Daftar kosong juga ditolak.
        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => [],
        ])->assertStatus(422);
    }

    public function test_kolom_jawaban_uraian_tidak_terisi_teks_hasil_game(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Main game sampai selesai: server mengisi kolom jawaban dengan
        // ringkasan permainan supaya guru melihat hasilnya.
        $this->mainkan($mission, 8)->assertOk();

        $this->assertStringContainsString(
            '[Hasil game',
            (string) Submission::query()->firstOrFail()->answer
        );

        $html = $this->get('/student/mission/'.$mission->id)->getContent();

        // Kolom jawaban uraian harus tetap KOSONG: teks otomatis itu bukan
        // tulisan siswa, dan tidak boleh bisa dikirim sebagai jawaban uraian
        // lalu dinilai AI.
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*id="answer"[^>]*>\s*<\/textarea>/s',
            $html,
            'Kolom jawaban uraian tidak boleh terisi teks hasil game.'
        );

        $this->assertStringNotContainsString('[Hasil game', $html);
    }

    public function test_ringkasan_menuliskan_hasil_ke_kolom_jawaban(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $this->mainkan($mission, 6);

        $answer = Submission::query()->firstOrFail()->answer;

        // Guru harus melihat ringkasan yang mudah dibaca di kolom jawaban.
        $this->assertStringContainsString('Hasil game', $answer);
        $this->assertStringContainsString('benar 6', $answer);
    }

    public function test_akurasi_sempurna_memberi_xp_lebih_besar(): void
    {
        // Dua tim: satu akurasi sempurna, satu akurasi rendah.
        $teamBagus = $this->joinTeam('Tim Bagus');
        $missionA = $this->bukaRonde(1);

        // Tim bagus: semua soal benar.
        $this->mainkan($missionA, $missionA->gameQuestionCount());

        $xpBagus = $teamBagus->fresh()->xp;

        // Buka lobi agar tim kedua bisa bergabung (ronde berjalan mengunci lobi).
        app(RoundService::class)->openLobby($this->session);

        $teamLemah = $this->joinTeam('Tim Lemah');
        // Tim lemah: hanya 1 soal benar.
        $this->mainkan($missionA, 1);

        $this->assertGreaterThan(
            $teamLemah->fresh()->xp,
            $xpBagus,
            'Tim dengan akurasi lebih tinggi seharusnya mendapat XP lebih besar.'
        );
    }

    public function test_jawaban_game_ditolak_bila_ronde_belum_dibuka(): void
    {
        $this->joinTeam();

        // Ronde 3 belum dibuka (hanya ronde 1 yang otomatis tersedia).
        $mission = Mission::query()->where('order', 3)->firstOrFail();

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => [
                ['pertanyaan' => 'soal', 'pilihan' => 'x'],
            ],
        ])->assertStatus(422);
    }

    public function test_jawaban_game_ditolak_untuk_ronde_tanpa_game(): void
    {
        $this->joinTeam();

        $mission = Mission::query()->create([
            'order' => 91,
            'title' => 'Misi Biasa',
            'slug' => 'misi-biasa',
            'story' => 's',
            'objective' => 'o',
            'instructions' => ['x'],
            'questions' => [['pertanyaan' => 'Soal?', 'jenis' => 'materi']],
            'xp' => 50,
            'is_active' => true,
        ]);

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => [
                ['pertanyaan' => 'soal', 'pilihan' => 'x'],
            ],
        ])->assertStatus(422);
    }

    // -----------------------------------------------------------------
    // KONTROL KEYBOARD MURNI (tidak mengganggu yang mengetik jawaban)
    // -----------------------------------------------------------------

    public function test_skrip_game_tidak_memakai_kontrol_mouse(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Tidak boleh ada pointermove (gerak mouse): anak yang menggerakkan
        // game tidak boleh berebut kursor dengan yang mengetik jawaban.
        $this->assertStringNotContainsString('pointermove', $js);
        $this->assertStringNotContainsString('data-arah', $js);
    }

    public function test_skrip_game_tidak_memakai_tombol_spasi(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Spasi tidak boleh dipakai untuk game, karena tombol spasi adalah
        // tombol paling sering dipakai saat mengetik jawaban di textarea.
        $this->assertDoesNotMatchRegularExpression(
            "/e\\.key === ' '\\s*\\)/",
            $js,
            'Tombol spasi tidak boleh dipakai untuk game.'
        );
    }

    public function test_skrip_game_mengecek_apakah_sedang_mengetik(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Harus ada pencegah agar tombol game tidak mencuri ketikan saat
        // fokus berada di input atau textarea.
        $this->assertStringContainsString('sedangMengetik', $js);
        $this->assertStringContainsString('textarea', $js);
        $this->assertStringContainsString('input', $js);
    }

    public function test_skrip_game_mendukung_panah_kiri_kanan_untuk_breakout(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Breakout harus bisa digerakkan panah kiri/kanan atau A/D.
        $this->assertStringContainsString('aturArahPemukul', $js);
        $this->assertStringContainsString('arahPemukul', $js);
    }

    public function test_halaman_game_menampilkan_panduan_keyboard(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->get('/student/mission/'.$mission->id.'/game');

        $response->assertOk();
        $response->assertSee('Kontrol Keyboard');
        $response->assertSee('Tanpa mouse');
    }

    // -----------------------------------------------------------------
    // XP GAME TIDAK BOLEH MELEBIHI BATAS
    // -----------------------------------------------------------------

    public function test_xp_game_tidak_melebihi_xp_maksimum_misi(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Bahkan bila klien mencoba mengirim angka palsu yang besar, XP tidak
        // boleh melampaui XP maksimum misi.
        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'jawaban' => $this->jawabanGame($mission, $mission->gameQuestionCount()),
            'benar' => 100, 'salah' => 0, 'skor' => 99999,
        ])->assertOk();

        $maksimum = $mission->xp + (int) config('tikmission.no_hint_bonus_xp');

        $this->assertLessThanOrEqual($maksimum, $team->fresh()->xp);
    }

    public function test_xp_game_tidak_menurunkan_xp_yang_sudah_ada(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Main bagus dulu: semua soal benar.
        $this->mainkan($mission, $mission->gameQuestionCount());

        $xpTinggi = $team->fresh()->xp;
        $this->assertGreaterThan(0, $xpTinggi);

        // Main lagi dengan hasil jelek: XP tidak boleh turun.
        $this->mainkan($mission, 0);

        $this->assertSame($xpTinggi, $team->fresh()->xp);
    }

    // -----------------------------------------------------------------
    // TAMPILAN
    // -----------------------------------------------------------------

    public function test_halaman_misi_menampilkan_ajakan_bermain_game(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->get('/student/mission/'.$mission->id);

        $response->assertOk();
        $response->assertSee('Ronde ini pakai game');
        $response->assertSee('Main Game');
    }

    public function test_guru_melihat_hasil_game_di_halaman_validasi(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $this->mainkan($mission, 5);

        $submission = Submission::query()->firstOrFail();

        $response = $this->actingAs(User::factory()->create([
            'role' => User::ROLE_TEACHER,
        ]))->get('/teacher/validations/'.$submission->id);

        $response->assertOk();
        // Guru harus melihat angka hasil permainan.
        $response->assertSee('5');
        $response->assertSee('Game');
    }

    public function test_dashboard_guru_menampilkan_alur_bernomor(): void
    {
        $response = $this->actingAs(User::factory()->create([
            'role' => User::ROLE_TEACHER,
        ]))->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('Jalankan Ronde');
        $response->assertSee('Periksa Jawaban');

        // Istilah lama "bukti" sudah tidak dipakai.
        $response->assertDontSee('Validasi Bukti');
    }

    public function test_halaman_sesi_guru_menampilkan_alur_bernomor(): void
    {
        $this->joinTeam();

        $response = $this->actingAs(User::factory()->create([
            'role' => User::ROLE_TEACHER,
        ]))->get('/teacher/sessions/'.$this->session->id);

        $response->assertOk();
        $response->assertSee('Bagikan Kode Sesi');
        $response->assertSee('Jalankan Ronde');
        $response->assertSee('Pantau Kelompok');

        // Tidak boleh ada tombol Layar Proyektor yang dobel.
        $jumlahTombol = substr_count($response->getContent(), 'Buka Layar Proyektor');
        $this->assertSame(1, $jumlahTombol, 'Tombol "Buka Layar Proyektor" seharusnya hanya satu.');
    }
}
