<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\MissionCode;
use App\Models\Team;
use App\Models\TeamProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Siswa masuk memakai kode sesi, pembuatan kelompok, dan penguncian misi.
 */
class StudentJoinTest extends TestCase
{
    use RefreshDatabase;

    protected function seedBasics(): void
    {
        // Buat sesi dan 3 misi (cukup untuk menguji penguncian).
        $session = GameSession::query()->create([
            'code' => 'TIK8-TEST',
            'name' => 'Sesi Uji',
            'duration_minutes' => 0,
            'start_time' => now(),
            'status' => GameSession::STATUS_ACTIVE,
            'hints_enabled' => true,
        ]);

        foreach ([1, 2, 3] as $order) {
            Mission::query()->create([
                'order' => $order,
                'title' => 'Misi '.$order,
                'slug' => 'misi-'.$order,
                'story' => 'Cerita misi '.$order,
                'objective' => 'Tujuan misi '.$order,
                'instructions' => ['Langkah 1', 'Langkah 2'],
                'xp' => 100,
                'is_active' => true,
            ]);
        }
    }

    public function test_siswa_dapat_masuk_dengan_kode_sesi_yang_benar(): void
    {
        $this->seedBasics();

        $response = $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 3',
            'members' => ['Ana', 'Budi'],
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('teams', ['name' => 'Kelompok 3']);
        $this->assertDatabaseHas('team_members', ['name' => 'Ana']);
        $this->assertDatabaseHas('team_members', ['name' => 'Budi']);
    }

    public function test_kode_sesi_salah_ditolak_dengan_pesan_jelas(): void
    {
        $this->seedBasics();

        $response = $this->from('/student/join')->post('/student/join', [
            'code' => 'KODE-SALAH',
            'team_name' => 'Kelompok 1',
            'members' => ['Ana'],
        ]);

        $response->assertRedirect('/student/join');
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('teams', 0);
    }

    public function test_kode_sesi_tidak_membedakan_huruf_besar_kecil(): void
    {
        $this->seedBasics();

        $response = $this->post('/student/join', [
            'code' => 'tik8-test',
            'team_name' => 'Kelompok Kecil',
            'members' => ['Ana'],
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertDatabaseHas('teams', ['name' => 'Kelompok Kecil']);
    }

    public function test_nama_anggota_wajib_diisi(): void
    {
        $this->seedBasics();

        $response = $this->from('/student/join')->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 1',
            'members' => ['', ''],
        ]);

        $response->assertRedirect('/student/join');
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_anggota_dengan_format_nama_dan_no_absen_disimpan_utuh(): void
    {
        $this->seedBasics();

        // Format yang dianjurkan: "Nama Lengkap - No Absen".
        $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 1',
            'members' => ['Ahmad Rizki Pratama - 07', 'Siti Nurhaliza - 21'],
        ]);

        $team = Team::query()->firstOrFail();

        $this->assertSame(
            ['Ahmad Rizki Pratama - 07', 'Siti Nurhaliza - 21'],
            $team->members()->orderBy('id')->pluck('name')->all()
        );
    }

    public function test_format_nama_tanpa_no_absen_diberi_peringatan_tapi_tetap_masuk(): void
    {
        $this->seedBasics();

        $response = $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 2',
            'members' => ['Ahmad Rizki Pratama'],
        ]);

        // Tetap boleh masuk (peringatan saja), supaya tidak menghambat di kelas.
        $response->assertRedirect(route('student.dashboard'));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('teams', 1);
    }

    public function test_form_join_menjelaskan_format_nama_dan_no_absen(): void
    {
        $this->seedBasics();

        $response = $this->get('/student/join');

        $response->assertOk();
        $response->assertSee('Nama Lengkap - No Absen');
        $response->assertSee('Ahmad Rizki Pratama - 07');
    }

    public function test_misi_pertama_terbuka_dan_misi_berikutnya_terkunci(): void
    {
        $this->seedBasics();

        $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 1',
            'members' => ['Ana'],
        ]);

        $team = Team::query()->firstOrFail();
        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->with('mission')
            ->get()
            ->sortBy(fn ($p) => $p->mission->order)
            ->values();

        // Misi 1 harus tersedia.
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $progress[0]->status);
        // Misi 2 dan 3 harus terkunci.
        $this->assertSame(TeamProgress::STATUS_LOCKED, $progress[1]->status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $progress[2]->status);
    }

    public function test_siswa_tidak_dapat_membuka_misi_yang_terkunci(): void
    {
        $this->seedBasics();

        $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 1',
            'members' => ['Ana'],
        ]);

        $lockedMission = Mission::query()->where('order', 2)->firstOrFail();

        $response = $this->get('/student/mission/'.$lockedMission->id);

        $response->assertRedirect(route('student.dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_siswa_dapat_membuka_misi_pertama(): void
    {
        $this->seedBasics();

        $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 1',
            'members' => ['Ana'],
        ]);

        $firstMission = Mission::query()->where('order', 1)->firstOrFail();

        $response = $this->get('/student/mission/'.$firstMission->id);

        $response->assertOk();
        $response->assertSee($firstMission->title);
    }

    public function test_siswa_belum_masuk_diarahkan_ke_form_join(): void
    {
        $this->seedBasics();

        $response = $this->get('/student/dashboard');

        $response->assertRedirect(route('student.join'));
    }

    public function test_halaman_siswa_tidak_membocorkan_kode_rahasia(): void
    {
        $this->seedBasics();

        // Sisipkan kode rahasia untuk misi 1.
        $session = GameSession::query()->firstOrFail();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        MissionCode::query()->create([
            'game_session_id' => $session->id,
            'mission_id' => $mission->id,
            'code' => 'SUPERRAHASIA',
        ]);

        $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok 1',
            'members' => ['Ana'],
        ]);

        // Halaman dashboard.
        $this->get('/student/dashboard')->assertDontSee('SUPERRAHASIA');

        // Halaman misi.
        $this->get('/student/mission/'.$mission->id)->assertDontSee('SUPERRAHASIA');
    }

    public function test_token_kelompok_tidak_dapat_dipalsukan(): void
    {
        $this->seedBasics();

        // Buat dua kelompok.
        $this->post('/student/join', [
            'code' => 'TIK8-TEST',
            'team_name' => 'Kelompok A',
            'members' => ['Ana'],
        ]);

        $teamA = Team::query()->where('name', 'Kelompok A')->firstOrFail();

        // Ubah token di session menjadi token palsu.
        $this->withSession([
            'tik_student' => [
                'team_id' => $teamA->id,
                'token' => 'token-palsu-123',
            ],
        ]);

        // Harus dianggap belum masuk.
        $response = $this->get('/student/dashboard');
        $response->assertRedirect(route('student.join'));
    }
}
