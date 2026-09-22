<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guru: membuat sesi, mengelola kelompok, membuka misi manual, dan laporan CSV.
 */
class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function teacher(): User
    {
        return User::factory()->teacher()->create();
    }

    protected function makeMissions(int $count = 3): void
    {
        foreach (range(1, $count) as $order) {
            Mission::query()->create([
                'order' => $order,
                'title' => 'Misi '.$order,
                'slug' => 'misi-'.$order,
                'story' => 'Cerita '.$order,
                'objective' => 'Tujuan '.$order,
                'instructions' => ['Langkah'],
                'xp' => 100,
                'is_active' => true,
            ]);
        }
    }

    protected function makeSession(string $code = 'TIK8-GURU'): GameSession
    {
        return GameSession::query()->create([
            'code' => $code,
            'name' => 'Sesi Guru',
            'duration_minutes' => 60,
            'start_time' => now(),
            'status' => GameSession::STATUS_ACTIVE,
            'hints_enabled' => true,
        ]);
    }

    // -----------------------------------------------------------------
    // SESI
    // -----------------------------------------------------------------

    public function test_guru_dapat_membuat_sesi_dan_kode_rahasia_otomatis_dibuat(): void
    {
        $this->makeMissions(2);

        $response = $this->actingAs($this->teacher())->post('/teacher/sessions', [
            'name' => 'Kelas 8A',
            'code' => 'TIK8-8A',
            'duration_minutes' => 45,
            'hints_enabled' => '1',
        ]);

        $session = GameSession::query()->where('code', 'TIK8-8A')->firstOrFail();

        $response->assertRedirect(route('teacher.sessions.show', $session));

        $this->assertDatabaseHas('game_sessions', ['code' => 'TIK8-8A', 'duration_minutes' => 45]);

        // Kode rahasia harus dibuat untuk setiap misi aktif.
        $this->assertSame(2, $session->missionCodes()->count());
    }

    public function test_kode_sesi_duplikat_ditolak(): void
    {
        $this->makeMissions(2);
        $this->makeSession('TIK8-DUP');

        $response = $this->actingAs($this->teacher())->post('/teacher/sessions', [
            'name' => 'Sesi Baru',
            'code' => 'TIK8-DUP',
            'duration_minutes' => 30,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_guru_dapat_mengakhiri_dan_mengaktifkan_kembali_sesi(): void
    {
        $this->makeMissions(1);
        $session = $this->makeSession();

        $teacher = $this->teacher();

        $this->actingAs($teacher)
            ->post('/teacher/sessions/'.$session->id.'/end')
            ->assertRedirect();

        $this->assertSame(GameSession::STATUS_ENDED, $session->fresh()->status);

        $this->actingAs($teacher)
            ->post('/teacher/sessions/'.$session->id.'/reopen')
            ->assertRedirect();

        $this->assertSame(GameSession::STATUS_ACTIVE, $session->fresh()->status);
    }

    public function test_guru_dapat_mengubah_kode_rahasia_misi(): void
    {
        $this->makeMissions(1);
        $session = $this->makeSession();
        $mission = Mission::query()->firstOrFail();

        // Buat kode awal.
        app(MissionService::class)->generateCodes($session);

        $this->actingAs($this->teacher())->put('/teacher/sessions/'.$session->id, [
            'name' => $session->name,
            'code' => $session->code,
            'duration_minutes' => 60,
            'codes' => [$mission->id => 'RAHASIAKU'],
        ]);

        $this->assertDatabaseHas('mission_codes', [
            'game_session_id' => $session->id,
            'mission_id' => $mission->id,
            'code' => 'RAHASIAKU',
        ]);
    }

    // -----------------------------------------------------------------
    // KELOMPOK
    // -----------------------------------------------------------------

    public function test_guru_dapat_membuka_misi_secara_manual(): void
    {
        $this->makeMissions(3);
        $session = $this->makeSession();
        $teacher = $this->teacher();

        $team = Team::query()->create([
            'game_session_id' => $session->id,
            'name' => 'Kelompok 1',
            'started_at' => now(),
        ]);
        app(MissionService::class)->initializeProgress($team);

        $mission3 = Mission::query()->where('order', 3)->firstOrFail();

        $this->actingAs($teacher)->post('/teacher/teams/'.$team->id.'/unlock', [
            'mission_id' => $mission3->id,
        ])->assertRedirect();

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission3->id)
            ->firstOrFail();

        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $progress->status);
    }

    public function test_guru_dapat_mereset_progres_kelompok(): void
    {
        $this->makeMissions(2);
        $session = $this->makeSession();
        $teacher = $this->teacher();

        $team = Team::query()->create([
            'game_session_id' => $session->id,
            'name' => 'Kelompok 1',
            'started_at' => now(),
            'xp' => 250,
        ]);
        app(MissionService::class)->initializeProgress($team);

        // Anggap misi 1 sudah selesai.
        TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', Mission::query()->where('order', 1)->value('id'))
            ->update(['status' => TeamProgress::STATUS_COMPLETED, 'xp' => 250]);

        $this->actingAs($teacher)
            ->post('/teacher/teams/'.$team->id.'/reset')
            ->assertRedirect();

        $team->refresh();

        // XP kembali 0.
        $this->assertSame(0, $team->xp);

        // Misi 1 tersedia lagi, misi 2 terkunci lagi.
        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->with('mission')
            ->get()
            ->sortBy(fn ($p) => $p->mission->order)
            ->values();

        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $progress[0]->status);
        $this->assertSame(TeamProgress::STATUS_LOCKED, $progress[1]->status);
    }

    // -----------------------------------------------------------------
    // LAPORAN & CSV
    // -----------------------------------------------------------------

    public function test_export_csv_berisi_data_kelompok(): void
    {
        $this->makeMissions(2);
        $session = $this->makeSession();

        $team = Team::query()->create([
            'game_session_id' => $session->id,
            'name' => 'Kelompok Emas',
            'started_at' => now(),
            'xp' => 300,
        ]);
        $team->members()->create(['name' => 'Siti']);
        app(MissionService::class)->initializeProgress($team);

        $response = $this->actingAs($this->teacher())->get('/teacher/report/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Kelompok Emas', $content);
        $this->assertStringContainsString('Siti', $content);
        $this->assertStringContainsString('Nama Kelompok', $content);
    }

    public function test_guru_dapat_melihat_halaman_laporan(): void
    {
        $this->makeMissions(1);
        $this->makeSession();

        $response = $this->actingAs($this->teacher())->get('/teacher/report');

        $response->assertOk();
        $response->assertSee('Laporan');
    }
}
