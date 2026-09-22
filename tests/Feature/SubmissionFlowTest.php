<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\MissionCode;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kirim bukti, validasi guru, XP, petunjuk, dan timer.
 */
class SubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected GameSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Matikan penilaian AI agar tes ini fokus pada alur kiriman dan
        // tidak pernah memanggil DeepSeek sungguhan. Penilaian AI diuji
        // terpisah di AiReviewTest dengan balasan tiruan.
        config()->set('ai.enabled', false);

        $this->session = GameSession::query()->create([
            'code' => 'TIK8-FLOW',
            'name' => 'Sesi Flow',
            'duration_minutes' => 0,
            'start_time' => now(),
            'status' => GameSession::STATUS_ACTIVE,
            'hints_enabled' => true,
        ]);

        foreach ([1, 2] as $order) {
            Mission::query()->create([
                'order' => $order,
                'title' => 'Misi '.$order,
                'slug' => 'misi-'.$order,
                'story' => 'Cerita '.$order,
                'objective' => 'Tujuan '.$order,
                'instructions' => ['Langkah 1'],
                'code_prompt' => 'Masukkan kode',
                'hint_1' => 'Petunjuk pertama',
                'hint_2' => 'Petunjuk kedua',
                'reflection_question' => 'Apa yang kamu pelajari?',
                'xp' => 100,
                'requires_pdf' => false,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Masuk sebagai siswa dan kembalikan kelompoknya.
     */
    protected function joinStudent(string $teamName = 'Kelompok 1'): Team
    {
        $this->post('/student/join', [
            'code' => 'TIK8-FLOW',
            'team_name' => $teamName,
            'members' => ['Ana', 'Budi'],
        ]);

        return Team::query()->where('name', $teamName)->firstOrFail();
    }

    protected function teacher(): User
    {
        return User::factory()->create(['role' => User::ROLE_TEACHER]);
    }

    // -----------------------------------------------------------------
    // UPLOAD
    // -----------------------------------------------------------------

    public function test_siswa_dapat_mengunggah_screenshot_sebagai_bukti(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $file = UploadedFile::fake()->image('bukti.png', 800, 600);

        $response = $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Saya belajar membuat header.',
            'evidence' => $file,
        ]);

        // Setelah kirim, siswa diarahkan ke halaman menunggu ronde berikutnya.
        $response->assertRedirect(route('student.waiting'));

        $submission = Submission::query()->firstOrFail();
        $this->assertSame(Submission::STATUS_WAITING, $submission->status);
        $this->assertNotNull($submission->evidence_path);
        $this->assertSame('Saya belajar membuat header.', $submission->answer);

        // File tersimpan di disk publik.
        Storage::disk('public')->assertExists($submission->evidence_path);
    }

    public function test_jawaban_tanpa_lampiran_diterima_karena_bukti_tidak_wajib(): void
    {
        // Mode materi penuh: yang dinilai adalah jawaban uraian, bukan unggahan.
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $response = $this->from('/student/mission/'.$mission->id)
            ->post('/student/mission/'.$mission->id.'/submit', [
                'answer' => 'Perangkat lunak aplikasi dipakai untuk menyelesaikan tugas tertentu.',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('submissions', 1);

        $submission = Submission::query()->firstOrFail();
        $this->assertNull($submission->evidence_path);
    }

    public function test_file_dengan_ekstensi_tidak_diizinkan_ditolak(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        // File .exe tidak termasuk whitelist.
        $file = UploadedFile::fake()->create('virus.exe', 100);

        $response = $this->from('/student/mission/'.$mission->id)
            ->post('/student/mission/'.$mission->id.'/submit', [
                'answer' => 'Jawaban benar, tetapi lampirannya memakai format terlarang.',
                'evidence' => $file,
            ]);

        $response->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_file_terlalu_besar_ditolak(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        // 11 MB > batas 10 MB (10240 KB).
        $file = UploadedFile::fake()->create('besar.png', 11264, 'image/png');

        $response = $this->from('/student/mission/'.$mission->id)
            ->post('/student/mission/'.$mission->id.'/submit', [
                'answer' => 'Jawaban benar, tetapi lampirannya terlalu besar ukurannya.',
                'evidence' => $file,
            ]);

        $response->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_misi_tidak_lagi_mewajibkan_pdf(): void
    {
        // Aturan requires_pdf sudah tidak dipakai: murid hanya menjawab uraian.
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        $mission->update(['requires_pdf' => true]);

        $team = $this->joinStudent();

        $response = $this->from('/student/mission/'.$mission->id)
            ->post('/student/mission/'.$mission->id.'/submit', [
                'answer' => 'Jawaban uraian saja, tanpa file PDF apa pun.',
            ]);

        // Tetap diterima karena kewajiban PDF sudah dihapus dari alur.
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('submissions', 1);
        $this->assertNull(Submission::query()->firstOrFail()->file_path);
    }

    public function test_lampiran_pdf_tetap_dapat_diunggah_bila_diinginkan(): void
    {
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $team = $this->joinStudent();

        $response = $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uraian lengkap beserta lampiran PDF sebagai bukti tambahan.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
            'file' => UploadedFile::fake()->create('laporan.pdf', 500, 'application/pdf'),
        ]);

        $response->assertRedirect(route('student.waiting'));

        $submission = Submission::query()->firstOrFail();
        $this->assertNotNull($submission->file_path);
        Storage::disk('public')->assertExists($submission->file_path);
    }

    public function test_nama_file_lampiran_disimpan_dengan_prefix_acak(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uraian dengan lampiran untuk menguji penamaan file.',
            'evidence' => UploadedFile::fake()->image('namaku-rahasia.png'),
        ]);

        $submission = Submission::query()->firstOrFail();

        $basename = basename($submission->evidence_path);

        // Nama file harus memakai prefix acak 32 karakter di depan nama asli,
        // sehingga file tidak dapat ditebak atau ditimpa.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}_/', $basename);

        // Path tidak sama dengan nama asli mentah.
        $this->assertNotSame('namaku-rahasia.png', $basename);

        // Nama asli tetap dipertahankan setelah prefix agar guru dapat mengenali file.
        $this->assertStringEndsWith('_namaku-rahasia.png', $basename);
    }

    public function test_kiriman_mengubah_status_misi_menjadi_menunggu_validasi(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uraian yang cukup panjang untuk dikirim ke guru.',
        ]);

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();

        $this->assertSame(TeamProgress::STATUS_WAITING_VALIDATION, $progress->status);
    }

    // -----------------------------------------------------------------
    // VALIDASI GURU
    // -----------------------------------------------------------------

    protected function submitForMission(Team $team, Mission $mission): Submission
    {
        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        return Submission::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();
    }

    public function test_guru_dapat_menyatakan_lulus_dan_misi_berikutnya_terbuka(): void
    {
        $team = $this->joinStudent();
        $mission1 = Mission::query()->where('order', 1)->firstOrFail();
        $mission2 = Mission::query()->where('order', 2)->firstOrFail();

        $submission = $this->submitForMission($team, $mission1);

        // Guru login dan menyatakan lulus.
        $response = $this->actingAs($this->teacher())
            ->post('/teacher/validations/'.$submission->id.'/approve', [
                'teacher_comment' => 'Pekerjaan bagus.',
            ]);

        $response->assertRedirect(route('teacher.validations'));

        // Kiriman harus LULUS.
        $submission->refresh();
        $this->assertSame(Submission::STATUS_APPROVED, $submission->status);
        $this->assertSame('Pekerjaan bagus.', $submission->teacher_comment);

        // Misi 1 harus COMPLETED.
        $progress1 = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission1->id)
            ->firstOrFail();
        $this->assertSame(TeamProgress::STATUS_COMPLETED, $progress1->status);

        // Misi 2 harus terbuka (AVAILABLE).
        $progress2 = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission2->id)
            ->firstOrFail();
        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $progress2->status);
    }

    public function test_xp_bertambah_setelah_validasi_lulus(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        $submission = $this->submitForMission($team, $mission);

        $this->assertSame(0, $team->xp);

        $this->actingAs($this->teacher())
            ->post('/teacher/validations/'.$submission->id.'/approve', []);

        $team->refresh();

        // XP dasar 100 + bonus tanpa petunjuk 20 = 120 (sesi tanpa timer).
        $this->assertSame(120, $team->xp);
    }

    public function test_guru_dapat_meminta_perbaikan_dengan_komentar(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        $submission = $this->submitForMission($team, $mission);

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/validations/'.$submission->id.'/revision', [
                'teacher_comment' => 'Screenshot belum jelas, kirim ulang.',
            ]);

        $response->assertRedirect(route('teacher.validations'));

        $submission->refresh();
        $this->assertSame(Submission::STATUS_REVISION, $submission->status);
        $this->assertSame('Screenshot belum jelas, kirim ulang.', $submission->teacher_comment);

        // Misi kembali dikerjakan.
        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();
        $this->assertSame(TeamProgress::STATUS_IN_PROGRESS, $progress->status);

        // XP tidak bertambah.
        $this->assertSame(0, $team->fresh()->xp);
    }

    public function test_komentar_wajib_diisi_saat_meminta_perbaikan(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        $submission = $this->submitForMission($team, $mission);

        $response = $this->actingAs($this->teacher())
            ->post('/teacher/validations/'.$submission->id.'/revision', [
                'teacher_comment' => '',
            ]);

        $response->assertSessionHasErrors('teacher_comment');
    }

    public function test_siswa_tidak_dapat_memvalidasi_misi(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        $submission = $this->submitForMission($team, $mission);

        // Siswa (tanpa login guru) mencoba memvalidasi.
        $response = $this->post('/teacher/validations/'.$submission->id.'/approve', []);

        $response->assertRedirect(route('login'));

        // Status tidak berubah.
        $this->assertSame(Submission::STATUS_WAITING, $submission->fresh()->status);
    }

    // -----------------------------------------------------------------
    // PETUNJUK
    // -----------------------------------------------------------------

    public function test_petunjuk_mengurangi_xp_saat_misi_divalidasi(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        // Buka misi agar berstatus in_progress.
        $this->get('/student/mission/'.$mission->id);

        // Pakai dua petunjuk.
        $this->postJson('/student/mission/'.$mission->id.'/hint', ['level' => 1])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/student/mission/'.$mission->id.'/hint', ['level' => 2])
            ->assertOk();

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();
        $this->assertSame(2, $progress->hints_used);

        // Kirim dan validasi.
        $submission = $this->submitForMission($team, $mission);

        $this->actingAs($this->teacher())
            ->post('/teacher/validations/'.$submission->id.'/approve', []);

        // XP: 100 dasar - 20 (2 petunjuk x 10) = 80. Tanpa bonus (ada petunjuk).
        $this->assertSame(80, $team->fresh()->xp);
    }

    public function test_petunjuk_tidak_dihitung_dua_kali_untuk_level_yang_sama(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        $this->get('/student/mission/'.$mission->id);

        $this->postJson('/student/mission/'.$mission->id.'/hint', ['level' => 1]);
        $second = $this->postJson('/student/mission/'.$mission->id.'/hint', ['level' => 1]);

        $second->assertOk();
        $second->assertJsonPath('penalty', 0);

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();
        $this->assertSame(1, $progress->hints_used);
    }

    public function test_petunjuk_dinonaktifkan_guru_mengembalikan_403(): void
    {
        $this->session->update(['hints_enabled' => false]);

        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        $this->get('/student/mission/'.$mission->id);

        $response = $this->postJson('/student/mission/'.$mission->id.'/hint', ['level' => 1]);

        $response->assertStatus(403);
        $response->assertJsonPath('ok', false);
    }

    // -----------------------------------------------------------------
    // TIMER
    // -----------------------------------------------------------------

    public function test_siswa_tidak_dapat_mengirim_setelah_waktu_habis(): void
    {
        // Sesi 30 menit, dimulai 1 jam lalu → waktu habis.
        $this->session->update([
            'duration_minutes' => 30,
            'start_time' => now()->subHour(),
        ]);

        // Masuk sebelum waktu habis dicek (join tetap boleh).
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        $response = $this->from('/student/mission/'.$mission->id)
            ->post('/student/mission/'.$mission->id.'/submit', [
                'evidence' => UploadedFile::fake()->image('bukti.png'),
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_kiriman_lama_tetap_tersimpan_setelah_waktu_habis(): void
    {
        Storage::fake('public');

        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        // Kirim saat waktu masih ada.
        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uraian yang dikirim sebelum waktu sesi habis.',
        ]);

        $this->assertDatabaseCount('submissions', 1);

        // Habiskan waktu.
        $this->session->update([
            'duration_minutes' => 30,
            'start_time' => now()->subHour(),
        ]);

        // Kiriman lama tetap ada.
        $this->assertSame(1, Submission::query()->count());
    }

    public function test_join_ditolak_bila_sesi_sudah_berakhir(): void
    {
        $this->session->update(['status' => GameSession::STATUS_ENDED]);

        $response = $this->from('/student/join')->post('/student/join', [
            'code' => 'TIK8-FLOW',
            'team_name' => 'Kelompok Baru',
            'members' => ['Ana'],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('teams', 0);
    }

    // -----------------------------------------------------------------
    // KODE RAHASIA
    // -----------------------------------------------------------------

    public function test_kode_rahasia_benar_dan_salah_sesuai_harapan(): void
    {
        $team = $this->joinStudent();
        $mission = Mission::query()->where('order', 1)->firstOrFail();

        MissionCode::query()->create([
            'game_session_id' => $this->session->id,
            'mission_id' => $mission->id,
            'code' => 'FORMAT',
        ]);

        $service = app(MissionService::class);

        $this->assertTrue($service->verifyCode($this->session, $mission, 'FORMAT'));
        $this->assertTrue($service->verifyCode($this->session, $mission, 'format'));
        $this->assertTrue($service->verifyCode($this->session, $mission, ' format '));
        $this->assertFalse($service->verifyCode($this->session, $mission, 'SALAH'));
    }
}
