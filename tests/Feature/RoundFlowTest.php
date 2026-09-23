<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Models\User;
use App\Services\AiReviewService;
use App\Services\RoundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mode permainan ala kuis: kendali ronde guru, pembukaan misi serentak,
 * penguncian lobi, dan data live untuk layar proyektor.
 */
class RoundFlowTest extends TestCase
{
    use RefreshDatabase;

    protected GameSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = GameSession::query()->create([
            'code' => 'TIK8-RONDE',
            'name' => 'Sesi Ronde',
            'duration_minutes' => 0,
            'start_time' => now(),
            'status' => GameSession::STATUS_ACTIVE,
            'hints_enabled' => true,
            'round_duration_minutes' => 10,
        ]);

        foreach ([1, 2, 3] as $order) {
            Mission::query()->create([
                'order' => $order,
                'title' => 'Misi Ronde '.$order,
                'slug' => 'misi-ronde-'.$order,
                'story' => 'Cerita '.$order,
                'objective' => 'Tujuan '.$order,
                'instructions' => ['Langkah 1'],
                'xp' => 100,
                'is_active' => true,
            ]);
        }
    }

    protected function teacher(): User
    {
        return User::factory()->create(['role' => User::ROLE_TEACHER]);
    }

    protected function joinTeam(string $name): Team
    {
        $this->post('/student/join', [
            'code' => 'TIK8-RONDE',
            'team_name' => $name,
            'members' => ['Ana'],
        ]);

        return Team::query()->where('name', $name)->firstOrFail();
    }

    // -----------------------------------------------------------------
    // KENDALI RONDE GURU
    // -----------------------------------------------------------------

    public function test_guru_dapat_memulai_ronde_dan_misi_terbuka_untuk_semua_kelompok(): void
    {
        $teamA = $this->joinTeam('Kelompok A');
        $teamB = $this->joinTeam('Kelompok B');

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', [
                'round' => 2,
                'duration_minutes' => 15,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->session->refresh();
        $this->assertSame(2, $this->session->current_round);
        $this->assertSame(GameSession::ROUND_RUNNING, $this->session->round_status);
        $this->assertSame(15, $this->session->round_duration_minutes);
        $this->assertTrue($this->session->lobby_locked);
        $this->assertNotNull($this->session->round_started_at);

        // Misi ronde 2 (order=2) harus terbuka untuk KEDUA kelompok.
        $mission2 = Mission::query()->where('order', 2)->firstOrFail();

        foreach ([$teamA, $teamB] as $team) {
            $progress = TeamProgress::query()
                ->where('team_id', $team->id)
                ->where('mission_id', $mission2->id)
                ->firstOrFail();

            $this->assertSame(TeamProgress::STATUS_AVAILABLE, $progress->status);
            $this->assertNotNull($progress->unlocked_at);
        }
    }

    public function test_siswa_dapat_mengerjakan_misi_ronde_tanpa_urutan_misi_pertama(): void
    {
        $team = $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 3]);

        // Misi 3 dibuka guru, jadi kelompok boleh mengerjakannya
        // walau misi 1 dan 2 belum selesai.
        $mission3 = Mission::query()->where('order', 3)->firstOrFail();

        $response = $this->get('/student/mission/'.$mission3->id);

        $response->assertOk();
        $response->assertSee($mission3->title);
    }

    public function test_guru_dapat_mengakhiri_ronde_dan_membuka_lobi(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/end');

        $this->session->refresh();
        $this->assertSame(GameSession::ROUND_ENDED, $this->session->round_status);
        $this->assertFalse($this->session->lobby_locked);
        $this->assertFalse($this->session->isRoundRunning());
    }

    public function test_reset_mengembalikan_sesi_ke_mode_lobi(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/reset');

        $this->session->refresh();
        $this->assertSame(0, $this->session->current_round);
        $this->assertSame(GameSession::ROUND_IDLE, $this->session->round_status);
        $this->assertNull($this->session->round_started_at);
        $this->assertFalse($this->session->lobby_locked);
    }

    public function test_ronde_tanpa_misi_yang_sesuai_ditolak_dengan_pesan_jelas(): void
    {
        // Nomor ronde di luar jumlah misi tidak dianggap galat validasi,
        // melainkan ditolak dengan pesan karena misinya tidak ditemukan.
        $response = $this->actingAs($this->teacher())
            ->from('/teacher/sessions/'.$this->session->id)
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 99]);

        $response->assertSessionHas('error');

        $this->assertSame(0, $this->session->fresh()->current_round);
    }

    public function test_ronde_tetap_dapat_dijalankan_saat_misi_dinonaktifkan(): void
    {
        $this->joinTeam('Kelompok A');

        // Mulai ronde 1 seperti biasa.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        // Guru menonaktifkan misi 3 di tengah permainan.
        Mission::query()->where('order', 3)->update(['is_active' => false]);

        // Ronde untuk misi 2 (masih aktif) tetap harus bisa dimulai,
        // dan misi 1 yang sudah berjalan tidak mengunci permainan.
        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 2]);

        $response->assertSessionHas('success');

        $this->assertSame(2, $this->session->fresh()->current_round);
    }

    public function test_siswa_biasa_tidak_dapat_mengendalikan_ronde(): void
    {
        $response = $this->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, $this->session->fresh()->current_round);
    }

    // -----------------------------------------------------------------
    // PENGUNCIAN LOBI
    // -----------------------------------------------------------------

    public function test_kelompok_baru_tidak_dapat_bergabung_saat_ronde_berjalan(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        // Kembali sebagai siswa (tanpa login guru) di browser baru.
        $this->flushSession();

        $response = $this->from('/student/join')->post('/student/join', [
            'code' => 'TIK8-RONDE',
            'team_name' => 'Kelompok Telat',
            'members' => ['Cici'],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('teams', ['name' => 'Kelompok Telat']);
    }

    public function test_kelompok_lama_tetap_dapat_masuk_kembali_saat_ronde_berjalan(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        $this->flushSession();

        // Kelompok dengan nama sama harus tetap boleh masuk kembali,
        // agar siswa yang tidak sengaja keluar bisa melanjutkan misinya.
        $response = $this->post('/student/join', [
            'code' => 'TIK8-RONDE',
            'team_name' => 'Kelompok A',
            'members' => ['Ana'],
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertDatabaseCount('teams', 1);
    }

    public function test_guru_dapat_membuka_lobi_saat_ronde_berjalan(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/lobby');

        $this->assertFalse($this->session->fresh()->lobby_locked);

        $this->flushSession();

        $response = $this->post('/student/join', [
            'code' => 'TIK8-RONDE',
            'team_name' => 'Kelompok Telat',
            'members' => ['Cici'],
        ]);

        $response->assertRedirect(route('student.dashboard'));
    }

    public function test_kelompok_terlambat_langsung_bisa_mengikuti_ronde_yang_berjalan(): void
    {
        $this->joinTeam('Kelompok A');

        // Guru sudah sampai ronde 3 saat kelompok baru datang.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 3]);

        // 1) Lobi masih terkunci: kelompok baru ditolak.
        $this->flushSession();

        $this->post('/student/join', [
            'code' => 'TIK8-RONDE',
            'team_name' => 'Kelompok Telat',
            'members' => ['Cici'],
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('teams', ['name' => 'Kelompok Telat']);

        // 2) Guru membuka lobi: kelompok baru boleh masuk...
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/lobby');

        $this->assertFalse($this->session->fresh()->lobby_locked);

        $this->flushSession();

        $this->post('/student/join', [
            'code' => 'TIK8-RONDE',
            'team_name' => 'Kelompok Telat',
            'members' => ['Cici'],
        ])->assertRedirect(route('student.dashboard'));

        $team = Team::query()->where('name', 'Kelompok Telat')->firstOrFail();
        $rondeAktif = Mission::query()->where('order', 3)->firstOrFail();

        // ...DAN langsung bisa mengerjakan ronde yang sedang berjalan, bukan
        // hanya bisa login lalu mentok di halaman "misi masih terkunci".
        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $rondeAktif->id)
            ->firstOrFail();

        $this->assertFalse(
            $progress->isLocked(),
            'Kelompok yang bergabung terlambat harus bisa ikut ronde yang sedang berjalan.'
        );

        $this->get('/student/mission/'.$rondeAktif->id)->assertOk();
    }

    // -----------------------------------------------------------------
    // ENDPOINT LIVE UNTUK LAYAR PROYEKTOR
    // -----------------------------------------------------------------

    public function test_endpoint_live_mengembalikan_status_ronde_dan_papan_skor(): void
    {
        $teamA = $this->joinTeam('Kelompok A');
        $teamB = $this->joinTeam('Kelompok B');

        $teamA->update(['xp' => 120]);
        $teamB->update(['xp' => 80]);

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        $response = $this->actingAs($this->teacher())
            ->getJson('/teacher/sessions/'.$this->session->id.'/live');

        $response->assertOk();
        $response->assertJsonPath('round.current', 1);
        $response->assertJsonPath('round.total', 3);
        $response->assertJsonPath('round.status', GameSession::ROUND_RUNNING);
        $response->assertJsonPath('round.is_running', true);
        $response->assertJsonPath('round.mission_title', 'Misi Ronde 1');
        $response->assertJsonPath('session.lobby_locked', true);

        // Papan skor diurutkan XP tertinggi lebih dulu.
        $teams = $response->json('teams');
        $this->assertCount(2, $teams);
        $this->assertSame('Kelompok A', $teams[0]['name']);
        $this->assertSame(120, $teams[0]['xp']);
        $this->assertSame('Kelompok B', $teams[1]['name']);
    }

    public function test_endpoint_live_menyertakan_sisa_waktu_ronde(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', [
                'round' => 1,
                'duration_minutes' => 5,
            ]);

        $response = $this->actingAs($this->teacher())
            ->getJson('/teacher/sessions/'.$this->session->id.'/live');

        $response->assertOk();
        $response->assertJsonPath('round.duration_minutes', 5);

        $remaining = $response->json('round.seconds_remaining');
        $this->assertNotNull($remaining);
        $this->assertLessThanOrEqual(300, $remaining);
        $this->assertGreaterThan(280, $remaining);
    }

    public function test_siswa_tidak_dapat_melihat_endpoint_live(): void
    {
        $this->getJson('/teacher/sessions/'.$this->session->id.'/live')
            ->assertUnauthorized();
    }

    public function test_endpoint_live_menghitung_kiriman_dinilai_ai_per_kiriman(): void
    {
        // Dua kelompok, satu di antaranya sudah mengirim 2 tugas yang dinilai AI.
        // Angka statistik harus 2, bukan 3 (kelompok + baris join).
        $teamA = $this->joinTeam('Kelompok A');
        $teamB = $this->joinTeam('Kelompok B');

        $missions = Mission::query()->orderBy('order')->take(2)->get();

        foreach ($missions as $mission) {
            foreach ([$teamA, $teamB] as $team) {
                Submission::query()->create([
                    'team_id' => $team->id,
                    'mission_id' => $mission->id,
                    'ai_status' => Submission::AI_SCORED,
                    'ai_score' => 80,
                ]);
            }
        }

        // Kelompok A sudah dinilai AI di 2 misi, Kelompok B juga 2 -> total 4.
        // Yang penting: tidak ada penggandaan akibat join dari sisi teams.
        $response = $this->actingAs($this->teacher())
            ->getJson('/teacher/sessions/'.$this->session->id.'/live');

        $response->assertOk();
        $this->assertSame(4, $response->json('stats.scored_by_ai'));

        // Tambah satu kiriman lagi untuk Kelompok A -> harus naik tepat 1.
        Submission::query()->create([
            'team_id' => $teamA->id,
            'mission_id' => $missions->last()->id,
            'ai_status' => Submission::AI_SCORED,
            'ai_score' => 50,
        ]);

        $this->assertSame(5, $this->actingAs($this->teacher())
            ->getJson('/teacher/sessions/'.$this->session->id.'/live')
            ->json('stats.scored_by_ai'));
    }

    // -----------------------------------------------------------------
    // STATUS RONDE UNTUK SISWA (agar tidak tertinggal di ronde lama)
    // -----------------------------------------------------------------

    public function test_siswa_dapat_menanyakan_status_ronde_aktif(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 2]);

        $response = $this->getJson('/student/round-status');

        $response->assertOk();
        $response->assertJsonPath('round', 2);
        $response->assertJsonPath('total', 3);
        $response->assertJsonPath('is_running', true);
        $response->assertJsonPath('mission.title', 'Misi Ronde 2');

        // Kelompok boleh mengerjakan karena misi ronde dibuka untuk semua.
        $response->assertJsonPath('can_work', true);
    }

    public function test_status_ronde_sebelum_ronde_dimulai_kosong(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->getJson('/student/round-status');

        $response->assertOk();
        $response->assertJsonPath('round', 0);
        $response->assertJsonPath('mission', null);
        $response->assertJsonPath('can_work', false);
    }

    public function test_status_ronde_mengarahkan_ke_misi_ronde_terbaru(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 3]);

        $mission3 = Mission::query()->where('order', 3)->firstOrFail();

        $response = $this->getJson('/student/round-status');

        // URL tujuan harus menunjuk misi ronde 3, bukan ronde lama.
        $response->assertJsonPath('mission.id', $mission3->id);
        $this->assertSame(
            route('student.mission.show', $mission3),
            $response->json('mission.url')
        );
    }

    public function test_status_ronde_memberi_tahu_saat_sesi_berakhir(): void
    {
        $this->joinTeam('Kelompok A');
        $this->session->update(['status' => GameSession::STATUS_ENDED]);

        $response = $this->getJson('/student/round-status');

        $response->assertOk();
        $response->assertJsonPath('session_ended', true);
        $response->assertJsonPath('accepts_submissions', false);
    }

    public function test_halaman_misi_memuat_pengawas_ronde(): void
    {
        $team = $this->joinTeam('Kelompok A');
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $response = $this->get('/student/mission/'.$mission->id);

        $response->assertOk();
        // Atribut pemicu auto-pindah ronde harus ada di halaman misi.
        $response->assertSee('data-round-watch', false);
        $response->assertSee('data-auto-redirect="true"', false);
        $response->assertSee('data-current-mission="'.$mission->id.'"', false);
    }

    public function test_dashboard_memuat_pengawas_ronde_tanpa_auto_pindah(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->get('/student/dashboard');

        $response->assertOk();
        $response->assertSee('data-round-watch', false);
        // Di dashboard tidak boleh memaksa pindah halaman.
        $response->assertSee('data-auto-redirect="false"', false);
    }

    // -----------------------------------------------------------------
    // BERPINDAH RONDE DARI SISI SISWA
    // -----------------------------------------------------------------

    public function test_halaman_misi_menampilkan_pemilih_ronde_untuk_semua_ronde_terbuka(): void
    {
        $this->joinTeam('Kelompok A');

        // Guru membuka ronde 1 dan 3 sekaligus (ronde 2 dilewati).
        app(RoundService::class)->openRounds($this->session, [1, 3], 10);

        $mission1 = Mission::query()->where('order', 1)->firstOrFail();
        $mission3 = Mission::query()->where('order', 3)->firstOrFail();

        $response = $this->get('/student/mission/'.$mission1->id);

        $response->assertOk();

        // Bilah pemilih ronde tersedia di halaman ronde, sehingga anak tidak
        // perlu mencari ke dashboard setiap kali mau berpindah.
        $response->assertSee('Pindah Ronde');

        // Ronde 3 (yang juga terbuka) bisa diklik dari sini.
        $response->assertSee(route('student.mission.show', $mission3), false);

        // Ronde yang sedang dibuka ditandai sebagai posisi sekarang.
        $response->assertSee('SEKARANG DI SINI');

        // Ronde yang masih terkunci tidak boleh muncul sebagai pilihan.
        $response->assertDontSee('Ronde 2: Misi Ronde 2');
    }

    public function test_pemilih_ronde_tidak_muncul_sebelum_guru_membuka_ronde(): void
    {
        $this->joinTeam('Kelompok A');

        $mission1 = Mission::query()->where('order', 1)->firstOrFail();

        $response = $this->get('/student/mission/'.$mission1->id);

        $response->assertOk();

        // Belum ada ronde berjalan: tidak ada yang bisa dipilih, jadi bilahnya
        // tidak perlu muncul dan tidak boleh membingungkan anak.
        $response->assertDontSee('Pindah Ronde');
    }

    public function test_dashboard_menandai_ronde_yang_sedang_dibuka(): void
    {
        $this->joinTeam('Kelompok A');

        app(RoundService::class)->openRounds($this->session, [2], 10);

        $response = $this->get('/student/dashboard');

        $response->assertOk();

        // Kartu misi ronde yang terbuka ditandai, supaya anak tidak menebak
        // kartu mana yang bisa dikerjakan.
        $response->assertSee('SEDANG DIBUKA');

        // Bilah pindah ronde juga tersedia di dashboard.
        $response->assertSee('Pindah Ronde');
    }

    public function test_pengawas_ronde_memberi_tahu_tanpa_menyeret_siswa_yang_sedang_menulis(): void
    {
        $js = file_get_contents(resource_path('js/round-watch.js'));

        // Bilah ronde harus ikut diperbarui saat guru membuka ronde baru,
        // tanpa perlu me-refresh halaman.
        $this->assertStringContainsString('syncRoundNav', $js);
        $this->assertStringContainsString('data-round-nav', $js);
        $this->assertStringContainsString('open_rounds', $js);

        // Anak yang sedang menulis tidak boleh dipindahkan paksa.
        $this->assertMatchesRegularExpression('/if \(sedangMenulis\(\)\)/', $js);

        // Ronde yang belum boleh dikerjakan kelompok ini tidak boleh ditarik.
        $this->assertStringContainsString('if (!data.can_work) return;', $js);

        // Jawaban setengah jadi tidak boleh terkirim sendiri lagi: dulu ini
        // menyebabkan kiriman bernilai rendah hanya karena guru menekan tombol.
        $this->assertStringNotContainsString('finishBeforeMoving', $js);
        $this->assertStringNotContainsString('otomatis terkirim', $js);

        // Halaman misi harus menyediakan banner pemberitahuan yang bisa ditutup.
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $this->joinTeam('Kelompok A');

        $response = $this->get('/student/mission/'.$mission->id);

        $response->assertSee('round-banner-title', false);
        $response->assertSee('data-round-banner-close', false);
    }

    /**
     * Ambil progres kelompok pada ronde tertentu.
     */
    protected function progressFor(Team $team, int $order): TeamProgress
    {
        $mission = Mission::query()->where('order', $order)->firstOrFail();

        return TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();
    }

    // -----------------------------------------------------------------
    // SATU TOMBOL "RONDE BERIKUTNYA"
    // -----------------------------------------------------------------

    public function test_tombol_ronde_berikutnya_membuka_ronde_pertama(): void
    {
        $team = $this->joinTeam('Kelompok A');

        // Belum ada ronde berjalan: tombol ini memulai ronde 1.
        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next', [
                'duration_minutes' => 10,
            ]);

        $response->assertSessionHas('success');

        $this->session->refresh();
        $this->assertSame(1, $this->session->current_round);
        $this->assertSame(GameSession::ROUND_RUNNING, $this->session->round_status);

        // Misi ronde 1 terbuka untuk kelompok.
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 1)->status);
    }

    public function test_tombol_ronde_berikutnya_menutup_ronde_lama_dan_membuka_baru(): void
    {
        $team = $this->joinTeam('Kelompok A');

        // Ronde 1.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next', ['duration_minutes' => 10]);

        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 1)->status);

        // Tekan tombol yang sama sekali lagi: ronde 1 ditutup, ronde 2 dibuka.
        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next', ['duration_minutes' => 10]);

        $response->assertSessionHas('success');

        $this->session->refresh();
        $this->assertSame(2, $this->session->current_round);

        // Ronde 1 tertutup, ronde 2 terbuka.
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 1)->status);
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 2)->status);
    }

    public function test_guru_tidak_perlu_menekan_akhiri_ronde_dulu(): void
    {
        $this->joinTeam('Kelompok A');

        // Panggil tombol "Ronde Berikutnya" berulang tanpa pernah "akhiri ronde".
        foreach ([1, 2, 3] as $ronde) {
            $this->actingAs($this->teacher())
                ->post('/teacher/sessions/'.$this->session->id.'/round/next', ['duration_minutes' => 5]);

            $this->assertSame($ronde, $this->session->fresh()->current_round);
        }
    }

    public function test_tombol_ronde_berikutnya_berhenti_setelah_ronde_terakhir(): void
    {
        $this->joinTeam('Kelompok A');

        // Jalankan semua ronde (3 ronde pada sesi uji ini).
        foreach ([1, 2, 3] as $ronde) {
            $this->actingAs($this->teacher())
                ->post('/teacher/sessions/'.$this->session->id.'/round/next');
        }

        $this->assertSame(3, $this->session->fresh()->current_round);

        // Menekan lagi harus memberi pesan, bukan membuat ronde ke-4.
        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');

        $response->assertSessionHas('error');
        $this->assertSame(3, $this->session->fresh()->current_round);
    }

    public function test_tombol_ronde_berikutnya_ditolak_saat_sesi_berakhir(): void
    {
        $this->session->update(['status' => GameSession::STATUS_ENDED]);

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');

        $response->assertSessionHas('error');
        $this->assertSame(0, $this->session->fresh()->current_round);
    }

    public function test_ronde_berikutnya_tidak_menggandakan_ronde(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');

        // Tekan dua kali lagi: tetap maju satu ronde per tekanan.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');

        $this->assertSame(2, $this->session->fresh()->current_round);
    }

    public function test_layar_proyektor_tidak_lagi_punya_tombol_kendali_ronde(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id.'/screen');

        $response->assertOk();

        // Layar proyektor murni tampilan: tidak boleh ada tombol kendali ronde,
        // supaya guru tidak bingung memilih layar.
        $html = $response->getContent();

        $this->assertStringNotContainsString('Mulai Ronde', $html);
        $this->assertStringNotContainsString('Akhiri Ronde', $html);
        $this->assertStringNotContainsString('Mode Lobi', $html);

        // Harus ada tautan menuju halaman kendali.
        $response->assertSee('Kendali Ronde');
    }

    public function test_halaman_sesi_punya_satu_tombol_utama_ronde(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id);

        $response->assertOk();

        $html = $response->getContent();

        // Hanya SATU tombol yang membuka ronde. Tombol lain (Akhiri Sesi,
        // Hentikan Ronde, dll) tidak boleh memakai kata "Buka Ronde N".
        $this->assertSame(
            1,
            substr_count($html, 'Buka Ronde 1'),
            'Seharusnya hanya ada satu tombol "Buka Ronde 1".'
        );

        // Tidak boleh ada lagi tombol lama "Mulai Ronde N".
        $this->assertStringNotContainsString('Mulai Ronde', $html);

        // Kendali berisiko disembunyikan di pengaturan lanjutan.
        $this->assertStringContainsString('Pengaturan lanjutan', $html);
        $this->assertStringContainsString('Hentikan Ronde', $html);
    }

    public function test_halaman_sesi_menampilkan_tombol_buka_lobi_sebagai_kontrol_utama(): void
    {
        $this->joinTeam('Kelompok A');

        // Ronde berjalan = lobi terkunci otomatis. Inilah kondisi yang membuat
        // siswa terlambat ditolak, jadi guru harus melihat tombolnya langsung.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', ['round' => 1]);

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id);

        $response->assertOk();
        $response->assertSee('LOBI TERKUNCI');
        $response->assertSee('Buka Lobi');
        $response->assertSee(route('teacher.sessions.lobby', $this->session), false);

        $html = $response->getContent();

        $posisiTombol = strpos($html, 'Buka Lobi');
        $posisiLanjutan = strpos($html, 'Pengaturan lanjutan');

        $this->assertNotFalse($posisiTombol, 'Tombol buka lobi harus ada di halaman sesi.');
        $this->assertNotFalse($posisiLanjutan, 'Menu pengaturan lanjutan harus tetap ada.');

        // Tombol harus tampil SEBELUM menu pengaturan lanjutan, bukan
        // tersembunyi di dalamnya (sebab itulah guru tidak menemukannya).
        $this->assertLessThan(
            $posisiLanjutan,
            $posisiTombol,
            'Tombol buka lobi tidak boleh tersembunyi di dalam menu pengaturan lanjutan.'
        );
    }

    // -----------------------------------------------------------------
    // BUKA RONDE MANA SAJA (TERMASUK MUNDUR)
    // -----------------------------------------------------------------

    public function test_guru_dapat_mundur_ke_ronde_sebelumnya(): void
    {
        $team = $this->joinTeam('Kelompok A');

        // Maju sampai ronde 3.
        foreach ([1, 2, 3] as $r) {
            $this->actingAs($this->teacher())
                ->post('/teacher/sessions/'.$this->session->id.'/round/next');
        }

        $this->assertSame(3, $this->session->fresh()->current_round);
        // Ronde 1 sudah tertutup karena ronde sudah lewat.
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 1)->status);

        // Mundur: guru membuka ronde 1 lagi.
        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/open', [
                'round' => 1,
                'duration_minutes' => 5,
            ]);

        $response->assertSessionHas('success');

        $this->session->refresh();
        $this->assertSame(1, $this->session->current_round);
        $this->assertSame(GameSession::ROUND_RUNNING, $this->session->round_status);

        // Ronde 1 terbuka kembali untuk kelompok.
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 1)->status);
    }

    public function test_mundur_ronde_tidak_menghapus_xp_yang_sudah_dikumpulkan(): void
    {
        $team = $this->joinTeam('Kelompok A');

        // Ronde 1 dibuka, murid mengerjakan dan mendapat XP.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');

        $mission1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->post('/student/mission/'.$mission1->id.'/submit', [
            'answer' => 'Jawaban ronde satu yang cukup panjang untuk dikirim.',
        ]);

        // Guru meluluskan, sehingga XP terkumpul.
        $submission = Submission::query()->firstOrFail();

        $this->actingAs($this->teacher())
            ->post('/teacher/validations/'.$submission->id.'/approve', []);

        $xpSebelum = $team->fresh()->xp;
        $this->assertGreaterThan(0, $xpSebelum);

        // Guru maju ke ronde 2, lalu MUNDUR lagi ke ronde 1.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/open', ['round' => 1]);

        // XP tidak boleh hilang.
        $this->assertSame($xpSebelum, $team->fresh()->xp);

        // Misi yang sudah LULUS tetap berstatus selesai (tidak dikunci ulang).
        $this->assertSame(TeamProgress::STATUS_COMPLETED, $this->progressFor($team, 1)->status);
    }

    public function test_membuka_ronde_tertentu_menutup_ronde_lain(): void
    {
        $team = $this->joinTeam('Kelompok A');

        // Buka ronde 1 dan 2 supaya keduanya sempat terbuka.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/next');

        // Sekarang lompat ke ronde 3.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/open', ['round' => 3]);

        $this->assertSame(3, $this->session->fresh()->current_round);

        // Hanya ronde 3 yang boleh dikerjakan.
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 3)->status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 2)->status);
    }

    public function test_guru_dapat_melompat_ke_ronde_terakhir(): void
    {
        $this->joinTeam('Kelompok A');

        $last = app(RoundService::class)->totalRounds();

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/open', ['round' => $last]);

        $this->assertSame($last, $this->session->fresh()->current_round);
    }

    public function test_buka_ronde_tidak_dikenal_ditolak(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/open', ['round' => 99]);

        $response->assertSessionHas('error');
        $this->assertSame(0, $this->session->fresh()->current_round);
    }

    public function test_buka_ronde_ditolak_saat_sesi_berakhir(): void
    {
        $this->session->update(['status' => GameSession::STATUS_ENDED]);

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/open', ['round' => 1]);

        $response->assertSessionHas('error');
        $this->assertSame(0, $this->session->fresh()->current_round);
    }

    public function test_halaman_sesi_menampilkan_daftar_pilih_ronde(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id);

        $response->assertOk();

        // Daftar ronde harus terlihat dan mendukung centang beberapa ronde.
        $response->assertSee('Pilih Ronde (boleh lebih dari satu)');
        $response->assertSee('Buka Ronde Terpilih');
        $response->assertSee('name="rounds[]"', false);

        // Harus ada petunjuk bahwa bisa kembali ke ronde sebelumnya.
        $response->assertSee('kembali ke ronde sebelumnya');
    }

    // -----------------------------------------------------------------
    // BUKA BEBERAPA RONDE SEKALIGUS
    // -----------------------------------------------------------------

    public function test_guru_dapat_membuka_beberapa_ronde_sekaligus(): void
    {
        $team = $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', [
                'rounds' => [1, 3],
                'duration_minutes' => 10,
            ]);

        $response->assertSessionHas('success');

        $this->session->refresh();

        // Kedua ronde terbuka bersamaan, ronde terdepan = 3.
        $this->assertSame([1, 3], $this->session->openRoundNumbers());
        $this->assertSame(3, $this->session->current_round);
        $this->assertSame(GameSession::ROUND_RUNNING, $this->session->round_status);
        $this->assertTrue($this->session->hasMultipleOpenRounds());

        // Keduanya bisa dikerjakan, ronde yang tidak dipilih justru dikunci.
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 1)->status);
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 3)->status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 2)->status);
    }

    public function test_beberapa_ronde_terbuka_mematikan_timer_ronde(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', [
                'rounds' => [1, 3],
                'duration_minutes' => 10,
            ]);

        $this->session->refresh();

        // Tanpa hitung mundur bersama: tiap kelompok bekerja dengan tempo sendiri.
        $this->assertNull($this->session->roundSecondsRemaining());
        $this->assertNull($this->session->formattedRoundRemaining());
        $this->assertFalse($this->session->isRoundTimeUp());
        $this->assertTrue($this->session->isRoundRunning());

        // Kembali ke satu ronde: timer hidup lagi.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', [
                'rounds' => [3],
                'duration_minutes' => 10,
            ]);

        $this->session->refresh();

        $this->assertFalse($this->session->hasMultipleOpenRounds());
        $this->assertNotNull($this->session->roundSecondsRemaining());
    }

    public function test_mengubah_pilihan_ronde_menutup_ronde_yang_dilepas(): void
    {
        $team = $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [1, 2, 3]]);

        $this->assertSame([1, 2, 3], $this->session->fresh()->openRoundNumbers());

        // Lepas ronde 1 dan 3 dari centang.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [2]]);

        $this->assertSame([2], $this->session->fresh()->openRoundNumbers());
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 2)->status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 1)->status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 3)->status);
    }

    public function test_buka_beberapa_ronde_menolak_nomor_yang_tidak_ada(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [1, 99]]);

        $response->assertSessionHas('error');

        // Satu nomor salah membatalkan seluruh permintaan.
        $this->assertSame([], $this->session->fresh()->openRoundNumbers());
    }

    public function test_buka_beberapa_ronde_butuh_minimal_satu_pilihan(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => []])
            ->assertSessionHasErrors('rounds');

        $this->assertSame([], $this->session->fresh()->openRoundNumbers());
    }

    public function test_guru_dapat_menutup_semua_ronde_sekaligus(): void
    {
        $team = $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [1, 3]]);

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/close')
            ->assertSessionHas('success');

        $this->session->refresh();

        $this->assertSame([], $this->session->openRoundNumbers());
        $this->assertSame(GameSession::ROUND_ENDED, $this->session->round_status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 1)->status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 3)->status);
    }

    public function test_halaman_sesi_menampilkan_ronde_yang_sedang_terbuka(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [1, 3]]);

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id);

        $response->assertOk();
        $response->assertSee('TERBUKA: 1, 3');
        $response->assertSee('hitung mundur ronde dimatikan');

        // Kotak centang ronde 1 dan 3 harus tercentang, ronde 2 tidak.
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/name="rounds\[\]"\s+value="1"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/name="rounds\[\]"\s+value="3"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="rounds\[\]"\s+value="2"\s+checked/', $html);
    }

    public function test_layar_proyektor_menyediakan_daftar_ronde_terbuka(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id.'/screen');

        $response->assertOk();
        $response->assertSee('id="scr-open-rounds"', false);
        $response->assertSee('Ronde yang sedang terbuka');
        $response->assertSee('id="scr-timer-note"', false);
    }

    public function test_status_ronde_siswa_mematikan_auto_pindah_saat_banyak_ronde_terbuka(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [1, 3]]);

        $response = $this->getJson('/student/round-status');

        $response->assertOk();
        $response->assertJsonPath('auto_switch', false);
        $response->assertJsonPath('can_work', true);
        $response->assertJsonCount(2, 'open_rounds');
        $response->assertJsonPath('open_rounds.0.order', 1);
        $response->assertJsonPath('open_rounds.1.order', 3);

        // Satu ronde saja = auto-pindah tetap aktif seperti semula.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [3]]);

        $this->getJson('/student/round-status')->assertJsonPath('auto_switch', true);
    }

    public function test_endpoint_live_menyertakan_ronde_terbuka_dan_status_timer(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [1, 3]]);

        $response = $this->actingAs($this->teacher())
            ->getJson('/teacher/sessions/'.$this->session->id.'/live');

        $response->assertOk();
        $response->assertJsonPath('round.timer_active', false);
        $response->assertJsonCount(2, 'round.open_rounds');
        $response->assertJsonPath('round.open_rounds.0.order', 1);
        $response->assertJsonPath('round.open_rounds.1.title', 'Misi Ronde 3');
    }

    public function test_kelompok_terlambat_mendapat_semua_ronde_yang_terbuka(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/rounds/open', ['rounds' => [1, 3]]);

        // Guru membuka lobi supaya murid yang datang terlambat bisa masuk.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/lobby');

        $this->flushSession();

        $this->post('/student/join', [
            'code' => 'TIK8-RONDE',
            'team_name' => 'Kelompok Telat',
            'members' => ['Cici'],
        ])->assertRedirect(route('student.dashboard'));

        $team = Team::query()->where('name', 'Kelompok Telat')->firstOrFail();

        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 1)->status);
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 3)->status);
    }

    public function test_daftar_ronde_menandai_ronde_yang_sedang_dibuka(): void
    {
        $this->joinTeam('Kelompok A');

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/open', ['round' => 2]);

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id);

        $response->assertOk();
        $response->assertSee('SEDANG DIBUKA');
        // Ronde yang sudah lewat harus ditandai bisa dibuka ulang.
        $response->assertSee('BUKA ULANG');
    }

    public function test_layar_proyektor_dapat_dibuka_guru(): void
    {
        $this->joinTeam('Kelompok A');

        $response = $this->actingAs($this->teacher())
            ->get('/teacher/sessions/'.$this->session->id.'/screen');

        $response->assertOk();
        $response->assertSee('Papan Skor');
        $response->assertSee('Kelompok A');
    }

    // -----------------------------------------------------------------
    // HELPER RONDE
    // -----------------------------------------------------------------

    public function test_timer_ronde_nol_berarti_tanpa_batas(): void
    {
        $rounds = app(RoundService::class);

        $rounds->start($this->session, 1, 0);
        $this->session->refresh();

        $this->assertTrue($this->session->isRoundRunning());
        $this->assertNull($this->session->roundSecondsRemaining());
        $this->assertFalse($this->session->isRoundTimeUp());
    }

    public function test_waktu_ronde_habis_dikenali(): void
    {
        $this->session->update([
            'current_round' => 1,
            'round_status' => GameSession::ROUND_RUNNING,
            'round_started_at' => now()->subMinutes(30),
            'round_duration_minutes' => 10,
        ]);

        $this->session->refresh();

        $this->assertTrue($this->session->isRoundTimeUp());
        $this->assertFalse($this->session->isRoundRunning());
        $this->assertSame(0, $this->session->roundSecondsRemaining());
    }

    public function test_ai_tidak_aktif_menyebabkan_kiriman_dilewati_dengan_aman(): void
    {
        config()->set('ai.api_key', null);
        config()->set('ai.enabled', true);

        $service = app(AiReviewService::class);
        $this->assertFalse($service->isConfigured());
    }
}
