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

    public function test_setiap_ronde_hanya_satu_soal_game(): void
    {
        // Satu soal per ronde supaya anak tidak bingung, dan permainan
        // harus benar-benar menyelesaikan soal itu sebelum ronde selesai.
        foreach (Mission::query()->active()->ordered()->get() as $mission) {
            $this->assertSame(
                1,
                $mission->gameQuestionCount(),
                "Ronde {$mission->order} seharusnya punya tepat 1 soal game."
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

        // Harus ada panggilan untuk MASUK layar penuh saat mulai bermain.
        $this->assertStringContainsString('requestFullscreen', $js);

        // Harus ada panggilan untuk KELUAR layar penuh (tombol & otomatis).
        $this->assertStringContainsString('exitFullscreen', $js);

        // Layar penuh dipanggil dari fungsi mulaiMain (aksi tekanan tombol).
        $this->assertMatchesRegularExpression(
            '/function mulaiMain\(\).*?masukLayarPenuh\(\)/s',
            $js,
            'Fungsi mulaiMain harus memanggil masukLayarPenuh().'
        );

        // Dan dikembalikan saat permainan selesai.
        $this->assertMatchesRegularExpression(
            '/function selesai\(.*?keluarLayarPenuh\(\)/s',
            $js,
            'Fungsi selesai harus memanggil keluarLayarPenuh().'
        );
    }

    public function test_skrip_game_selesai_saat_soal_habis_dijawab(): void
    {
        $js = file_get_contents(resource_path('js/game.js'));

        // Permainan harus berakhir saat semua soal sudah dijawab benar,
        // bukan mengulang soal yang sama tanpa henti.
        $this->assertStringContainsString('indeksSoal >= soal.length', $js);
        $this->assertStringContainsString('Semua soal selesai', $js);
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

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'pertanyaan' => 'Komponen komputer yang bisa disentuh disebut...',
            'pilihan' => 'Software',
            'tepat' => false,
        ])->assertOk();

        $submission = Submission::query()->firstOrFail();

        $this->assertNotNull($submission->game_missed);
        $this->assertCount(1, $submission->game_missed);
        $this->assertSame('Komponen komputer yang bisa disentuh disebut...', $submission->game_missed[0]['pertanyaan']);
    }

    public function test_jawaban_benar_tidak_dicatat_sebagai_kesalahan(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'pertanyaan' => 'Hardware adalah...',
            'pilihan' => 'Perangkat keras',
            'tepat' => true,
        ])->assertOk();

        $submission = Submission::query()->first();
        $this->assertTrue($submission === null || empty($submission->game_missed));
    }

    public function test_ringkasan_permainan_menyimpan_hasil_dan_memberi_xp(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $response = $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'ringkasan' => true,
            'benar' => 7,
            'salah' => 1,
            'skor' => 240,
            'pertanyaan' => 'ringkasan',
            'tepat' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('benar', 7);
        $response->assertJsonPath('salah', 1);

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(7, $submission->game_correct);
        $this->assertSame(1, $submission->game_wrong);
        $this->assertSame(240, $submission->game_score);
        $this->assertNotNull($submission->game_played_at);

        // XP kelompok harus bertambah dari hasil game.
        $this->assertGreaterThan(0, $team->fresh()->xp);
    }

    public function test_ringkasan_menuliskan_hasil_ke_kolom_jawaban(): void
    {
        $this->joinTeam();
        $mission = $this->bukaRonde(1);

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'ringkasan' => true,
            'benar' => 6,
            'salah' => 2,
            'skor' => 200,
            'pertanyaan' => 'ringkasan',
            'tepat' => true,
        ]);

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

        $this->postJson('/student/mission/'.$missionA->id.'/game-answer', [
            'ringkasan' => true, 'benar' => 8, 'salah' => 0, 'skor' => 300,
            'pertanyaan' => 'r', 'tepat' => true,
        ]);

        $xpBagus = $teamBagus->fresh()->xp;

        // Buka lobi agar tim kedua bisa bergabung (ronde berjalan mengunci lobi).
        app(RoundService::class)->openLobby($this->session);

        $teamLemah = $this->joinTeam('Tim Lemah');
        $this->postJson('/student/mission/'.$missionA->id.'/game-answer', [
            'ringkasan' => true, 'benar' => 1, 'salah' => 7, 'skor' => 30,
            'pertanyaan' => 'r', 'tepat' => true,
        ]);

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
            'pertanyaan' => 'soal',
            'pilihan' => 'x',
            'tepat' => true,
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
            'pertanyaan' => 'soal',
            'tepat' => true,
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

        // Skor sangat besar tidak boleh melampaui XP maksimum misi (120).
        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'ringkasan' => true, 'benar' => 100, 'salah' => 0, 'skor' => 99999,
            'pertanyaan' => 'r', 'tepat' => true,
        ]);

        $maksimum = $mission->xp + (int) config('tikmission.no_hint_bonus_xp');

        $this->assertLessThanOrEqual($maksimum, $team->fresh()->xp);
    }

    public function test_xp_game_tidak_menurunkan_xp_yang_sudah_ada(): void
    {
        $team = $this->joinTeam();
        $mission = $this->bukaRonde(1);

        // Main bagus dulu.
        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'ringkasan' => true, 'benar' => 8, 'salah' => 0, 'skor' => 300,
            'pertanyaan' => 'r', 'tepat' => true,
        ]);

        $xpTinggi = $team->fresh()->xp;
        $this->assertGreaterThan(0, $xpTinggi);

        // Main lagi dengan hasil jelek: XP tidak boleh turun.
        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'ringkasan' => true, 'benar' => 0, 'salah' => 8, 'skor' => 0,
            'pertanyaan' => 'r', 'tepat' => true,
        ]);

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

        $this->postJson('/student/mission/'.$mission->id.'/game-answer', [
            'ringkasan' => true, 'benar' => 5, 'salah' => 3, 'skor' => 150,
            'pertanyaan' => 'r', 'tepat' => true,
        ]);

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
        $response->assertSee('Kode Rahasia Tiap Ronde');

        // Tidak boleh ada tombol Layar Proyektor yang dobel.
        $jumlahTombol = substr_count($response->getContent(), 'Buka Layar Proyektor');
        $this->assertSame(1, $jumlahTombol, 'Tombol "Buka Layar Proyektor" seharusnya hanya satu.');
    }
}
