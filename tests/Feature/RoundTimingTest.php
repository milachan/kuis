<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Aturan ronde: ronde lama ditutup saat guru pindah ronde, tetapi ronde 1
 * tetap terbuka untuk pendatang baru, dan waktu kerja dihitung per siswa.
 */
class RoundTimingTest extends TestCase
{
    use RefreshDatabase;

    protected GameSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        config()->set('ai.api_key', 'kunci-uji');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'skor' => 80, 'umpan_balik' => 'ok', 'bahasa_sendiri' => true,
            ])]]],
        ], 200)]);

        $this->session = GameSession::query()->create([
            'code' => 'TIK-TIME',
            'name' => 'Sesi Waktu',
            'duration_minutes' => 0,
            'start_time' => now(),
            'status' => GameSession::STATUS_ACTIVE,
            'hints_enabled' => true,
            'round_duration_minutes' => 10,
        ]);

        foreach ([1, 2, 3] as $order) {
            Mission::query()->create([
                'order' => $order,
                'title' => 'Ronde '.$order,
                'slug' => 'ronde-'.$order,
                'story' => 'Cerita '.$order,
                'objective' => 'Tujuan '.$order,
                'instructions' => ['Langkah 1'],
                'questions' => [
                    ['pertanyaan' => "Soal ronde {$order}?", 'jenis' => 'materi'],
                ],
                'xp' => 100,
                'is_active' => true,
            ]);
        }

        app(MissionService::class)->generateCodes($this->session);
    }

    protected function teacher(): User
    {
        return User::factory()->create(['role' => User::ROLE_TEACHER]);
    }

    protected function joinTeam(string $name = 'Kelompok A'): Team
    {
        $this->post('/student/join', [
            'code' => 'TIK-TIME',
            'team_name' => $name,
            'members' => ['Ana'],
        ]);

        return Team::query()->where('name', $name)->firstOrFail();
    }

    protected function startRound(int $round, ?int $duration = null): void
    {
        $payload = ['round' => $round];

        if ($duration !== null) {
            $payload['duration_minutes'] = $duration;
        }

        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/round/start', $payload);
    }

    protected function progressFor(Team $team, int $order): TeamProgress
    {
        $mission = Mission::query()->where('order', $order)->firstOrFail();

        return TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();
    }

    // -----------------------------------------------------------------
    // RONDE LAMA DITUTUP
    // -----------------------------------------------------------------

    public function test_ronde_lama_ditutup_saat_guru_pindah_ronde(): void
    {
        $team = $this->joinTeam();

        $this->startRound(1);
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($team, 1)->status);

        // Guru pindah ke ronde 2.
        $this->startRound(2);

        // Misi ronde 1 harus terkunci kembali.
        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 1)->status);
    }

    public function test_jawaban_ronde_lama_ditolak_setelah_guru_pindah_ronde(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1);
        $this->startRound(2);

        $response = $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban untuk ronde satu yang sudah lewat waktunya.',
        ]);

        // Ditolak: tidak ada kiriman baru untuk ronde lama.
        $this->assertDatabaseCount('submissions', 0);
        $response->assertSessionHas('error');
    }

    public function test_kiriman_ronde_lama_yang_sudah_masuk_tetap_aman(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1);

        // Murid sudah mengirim jawaban ronde 1.
        $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban ronde satu yang sudah terkirim sebelum guru pindah.',
        ]);

        $this->assertSame(TeamProgress::STATUS_WAITING_VALIDATION, $this->progressFor($team, 1)->status);

        // Guru pindah ronde.
        $this->startRound(2);

        // Kiriman yang sudah masuk TIDAK boleh hilang atau terhapus.
        $this->assertDatabaseCount('submissions', 1);
        $this->assertSame(
            TeamProgress::STATUS_WAITING_VALIDATION,
            $this->progressFor($team, 1)->status
        );
    }

    public function test_misi_yang_sudah_lulus_tidak_dikunci_ulang(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1);

        $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban ronde satu untuk divalidasi guru.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->actingAs($this->teacher())
            ->post('/teacher/validations/'.$submission->id.'/approve', []);

        $this->assertSame(TeamProgress::STATUS_COMPLETED, $this->progressFor($team, 1)->status);

        // Pindah ronde: misi yang sudah LULUS tidak boleh dikunci ulang,
        // karena XP-nya sudah sah.
        $this->startRound(2);

        $this->assertSame(TeamProgress::STATUS_COMPLETED, $this->progressFor($team, 1)->status);
    }

    // -----------------------------------------------------------------
    // PENDATANG BARU TETAP BISA MULAI DARI RONDE 1
    // -----------------------------------------------------------------

    public function test_kelompok_baru_tetap_bisa_mengerjakan_ronde_1(): void
    {
        $this->joinTeam('Kelompok Lama');

        // Guru sudah jauh di ronde 3.
        $this->startRound(3);

        // Kelompok baru bergabung setelah guru membuka lobi.
        $this->actingAs($this->teacher())
            ->post('/teacher/sessions/'.$this->session->id.'/lobby');

        $this->flushSession();

        $this->post('/student/join', [
            'code' => 'TIK-TIME',
            'team_name' => 'Kelompok Baru',
            'members' => ['Budi'],
        ]);

        $baru = Team::query()->where('name', 'Kelompok Baru')->firstOrFail();

        // Misi ronde 1 harus terbuka untuk pendatang baru.
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $this->progressFor($baru, 1)->status);

        // Dan dia benar-benar bisa mengirim jawaban ronde 1.
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $response = $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Saya murid baru, mengerjakan ronde satu dari awal.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_kelompok_lama_tidak_bisa_kembali_ke_ronde_1(): void
    {
        // Kelompok yang sudah ikut dari awal: setelah guru pindah ronde,
        // ronde 1 harus tertutup baginya (tidak bisa balik mengerjakan).
        $team = $this->joinTeam();

        $this->startRound(1);
        $this->startRound(2);

        $this->assertSame(TeamProgress::STATUS_LOCKED, $this->progressFor($team, 1)->status);

        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Mencoba kembali ke ronde satu setelah ronde dua dibuka.',
        ]);

        $this->assertDatabaseCount('submissions', 0);
    }

    // -----------------------------------------------------------------
    // WAKTU DIHITUNG SEJAK SISWA MASUK
    // -----------------------------------------------------------------

    public function test_waktu_mulai_dicatat_saat_siswa_membuka_ronde(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1, 10);

        $this->assertNull($this->progressFor($team, 1)->work_started_at);

        // Siswa membuka halaman misi.
        $this->get('/student/mission/'.$m1->id);

        $progress = $this->progressFor($team, 1);

        $this->assertNotNull($progress->work_started_at);
        $this->assertSame(TeamProgress::STATUS_IN_PROGRESS, $progress->status);
    }

    public function test_waktu_mulai_tidak_diubah_saat_halaman_dibuka_ulang(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1, 10);

        $this->get('/student/mission/'.$m1->id);
        $pertama = $this->progressFor($team, 1)->work_started_at;

        // Buka lagi setelah beberapa detik: waktu mulai tidak boleh bergeser,
        // supaya siswa tidak bisa "reset" waktunya dengan me-refresh.
        $this->travel(30)->seconds();
        $this->get('/student/mission/'.$m1->id);

        $this->assertTrue(
            $pertama->equalTo($this->progressFor($team, 1)->work_started_at),
            'Waktu mulai seharusnya tidak berubah saat halaman dibuka ulang.'
        );
    }

    public function test_kiriman_ditolak_setelah_waktu_siswa_habis(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1, 10);

        // Siswa membuka ronde, waktunya mulai berjalan.
        $this->get('/student/mission/'.$m1->id);

        // Lewati 11 menit.
        $this->travel(11)->minutes();

        $response = $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban yang dikirim setelah waktu mengerjakan habis.',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_kiriman_masih_diterima_sebelum_waktu_habis(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1, 10);
        $this->get('/student/mission/'.$m1->id);

        // Baru 5 menit: masih boleh.
        $this->travel(5)->minutes();

        $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban yang dikirim sebelum waktu habis.',
        ]);

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_siswa_terlambat_masuk_punya_waktu_penuh(): void
    {
        $team = $this->joinTeam('Kelompok Lambat');
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        // Guru membuka ronde dengan durasi 10 menit.
        $this->startRound(1, 10);

        // Siswa baru membuka rondenya 8 menit setelah guru membuka.
        // Waktunya dihitung dari DIA membuka, jadi masih 10 menit penuh.
        $this->travel(8)->minutes();
        $this->get('/student/mission/'.$m1->id);

        $progress = $this->progressFor($team, 1);

        // Sisa waktu harus sekitar 10 menit (600 detik), bukan 2 menit.
        $remaining = $progress->workSecondsRemaining(10);
        $this->assertNotNull($remaining);
        $this->assertGreaterThan(590, $remaining);

        // Dan masih bisa mengirim setelah lewat 10 menit dari guru membuka.
        $this->travel(9)->minutes();

        $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Saya masuk terlambat tapi masih dalam waktu saya sendiri.',
        ]);

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_tanpa_durasi_ronde_tidak_ada_batas_waktu(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1, 0);
        $this->get('/student/mission/'.$m1->id);

        $progress = $this->progressFor($team, 1);

        $this->assertNull($progress->workSecondsRemaining(0));
        $this->assertFalse($progress->isWorkTimeUp(0));

        // Kirim kapan saja tetap boleh.
        $this->travel(3)->hours();

        $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Tanpa batas waktu, jadi tetap boleh dikirim.',
        ]);

        $this->assertDatabaseCount('submissions', 1);
    }

    // -----------------------------------------------------------------
    // ARAH SETELAH KIRIM: RONDE BERIKUTNYA ATAU HALAMAN MENUNGGU
    // -----------------------------------------------------------------

    public function test_setelah_kirim_diarahkan_ke_ronde_berikutnya_bila_sudah_dibuka(): void
    {
        $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();
        $m2 = Mission::query()->where('order', 2)->firstOrFail();

        // Guru membuka ronde 1.
        $this->startRound(1, 10);

        // Murid mengirim jawaban ronde 1 selagi ronde 1 masih aktif.
        $response = $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban ronde satu yang dikirim selagi ronde satu aktif.',
        ]);

        // Ronde 2 belum dibuka -> diarahkan ke halaman menunggu dulu.
        $response->assertRedirect(route('student.waiting'));

        // Guru membuka ronde 2. Murid membuka misi ronde 2 lalu mengirim.
        $this->startRound(2, 10);

        $response2 = $this->post('/student/mission/'.$m2->id.'/submit', [
            'answer' => 'Jawaban ronde dua yang dikirim selagi ronde dua aktif.',
        ]);

        // Ronde 3 belum dibuka -> kembali ke halaman menunggu.
        $response2->assertRedirect(route('student.waiting'));
    }

    public function test_arah_ke_ronde_berikutnya_saat_ronde_berikutnya_sudah_terbuka_untuk_kelompok(): void
    {
        $team = $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        // Buka ronde 1 (hanya satu ronde aktif).
        $this->startRound(1, 10);

        // Buka misi ronde 2 untuk kelompok ini secara manual (simulasi guru
        // membuka ronde 2 lebih dahulu untuk kelompok tertentu).
        $m2 = Mission::query()->where('order', 2)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $m2);

        // Ronde 1 masih terbuka; kirim jawaban ronde 1.
        $response = $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban ronde satu, sementara ronde dua sudah terbuka.',
        ]);

        // Ronde 2 sudah bisa dikerjakan -> langsung diarahkan ke misi ronde 2.
        $response->assertRedirect(route('student.mission.show', $m2));
    }

    public function test_setelah_kirim_diarahkan_ke_halaman_menunggu_bila_ronde_belum_dibuka(): void
    {
        $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        // Hanya ronde 1 yang dibuka.
        $this->startRound(1, 10);

        $response = $this->post('/student/mission/'.$m1->id.'/submit', [
            'answer' => 'Jawaban ronde satu, ronde dua belum dibuka guru.',
        ]);

        // Ronde 2 belum dibuka: murid diarahkan ke halaman menunggu.
        $response->assertRedirect(route('student.waiting'));
    }

    public function test_halaman_menunggu_dapat_dibuka_dan_menampilkan_status(): void
    {
        $this->joinTeam();
        $this->startRound(1, 10);

        $response = $this->get('/student/waiting');

        $response->assertOk();
        $response->assertSee('Jawabanmu Sudah Terkirim');
        $response->assertSee('data-round-watch', false);
    }

    public function test_halaman_menunggu_memuat_pemantau_ronde(): void
    {
        $this->joinTeam();

        // Halaman menunggu harus punya pengawas ronde agar otomatis pindah
        // begitu guru membuka ronde berikutnya.
        $response = $this->get('/student/waiting');

        $response->assertOk();
        $response->assertSee('data-auto-redirect="true"', false);
        $response->assertSee(route('student.round.status'), false);
    }

    public function test_halaman_menunggu_butuh_login_kelompok(): void
    {
        // Tanpa sesi kelompok, halaman menunggu dialihkan ke halaman masuk.
        $this->get('/student/waiting')->assertRedirect(route('student.join'));
    }

    // -----------------------------------------------------------------
    // TAMPILAN
    // -----------------------------------------------------------------

    public function test_halaman_ronde_menampilkan_timer_siswa(): void
    {
        $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1, 10);

        $response = $this->get('/student/mission/'.$m1->id);

        $response->assertOk();
        $response->assertSee('Waktu Mengerjakanku');
        $response->assertSee('data-remaining', false);
    }

    public function test_halaman_ronde_menyimpan_id_misi_untuk_draf(): void
    {
        $this->joinTeam();
        $m1 = Mission::query()->where('order', 1)->firstOrFail();

        $this->startRound(1);

        $response = $this->get('/student/mission/'.$m1->id);

        $response->assertOk();
        $response->assertSee('data-mission-id="'.$m1->id.'"', false);
    }
}
