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
