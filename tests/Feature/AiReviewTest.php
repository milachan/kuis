<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Models\User;
use App\Services\AiReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Penilaian otomatis AI (DeepSeek) atas jawaban refleksi siswa.
 * Panggilan HTTP dipalsukan agar tidak memerlukan API key sungguhan.
 */
class AiReviewTest extends TestCase
{
    use RefreshDatabase;

    protected GameSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        config()->set('ai.enabled', true);
        config()->set('ai.api_key', 'test-key-rahasia');
        config()->set('ai.base_url', 'https://api.deepseek.com');
        config()->set('ai.model', 'deepseek-chat');
        config()->set('ai.xp_weight_percent', 70);

        $this->session = GameSession::query()->create([
            'code' => 'TIK8-AI',
            'name' => 'Sesi AI',
            'duration_minutes' => 0,
            'start_time' => now(),
            'status' => GameSession::STATUS_ACTIVE,
            'hints_enabled' => true,
        ]);

        Mission::query()->create([
            'order' => 1,
            'title' => 'Misi Format Teks',
            'slug' => 'misi-format-teks',
            'story' => 'Cerita',
            'objective' => 'Siswa dapat memformat teks di pengolah kata.',
            'instructions' => ['Buka aplikasi', 'Buat judul'],
            'reflection_question' => 'Apa fungsi fitur bold?',
            'xp' => 100,
            'is_active' => true,
        ]);
    }

    protected function joinTeam(): Team
    {
        $this->post('/student/join', [
            'code' => 'TIK8-AI',
            'team_name' => 'Kelompok AI',
            'members' => ['Ana'],
        ]);

        return Team::query()->where('name', 'Kelompok AI')->firstOrFail();
    }

    /**
     * Balasan sukses dari DeepSeek dalam format OpenAI-compatible.
     */
    protected function fakeAiResponse(int $score = 80, string $feedback = 'Jawabanmu tepat.'): array
    {
        $payload = [
            'skor' => $score,
            'umpan_balik' => $feedback,
            'kriteria' => [
                ['kriteria' => 'Ketepatan konsep', 'nilai' => $score, 'catatan' => 'Baik'],
                ['kriteria' => 'Kelengkapan jawaban', 'nilai' => 75, 'catatan' => 'Cukup'],
                ['kriteria' => 'Penggunaan istilah', 'nilai' => 70, 'catatan' => 'Cukup'],
            ],
        ];

        return [
            'model' => 'deepseek-chat',
            'choices' => [
                ['message' => ['content' => json_encode($payload)]],
            ],
        ];
    }

    // -----------------------------------------------------------------
    // PENILAIAN BERHASIL
    // -----------------------------------------------------------------

    public function test_jawaban_dinilai_ai_saat_submit_dan_skor_tersimpan(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response($this->fakeAiResponse(90, 'Bagus sekali!'), 200),
        ]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Fitur bold membuat teks menjadi lebih tebal dan menonjol.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(Submission::AI_SCORED, $submission->ai_status);
        $this->assertSame(90, $submission->ai_score);
        $this->assertSame('Bagus sekali!', $submission->ai_feedback);
        $this->assertSame('deepseek-chat', $submission->ai_model);
        $this->assertNotNull($submission->ai_reviewed_at);
        $this->assertCount(3, $submission->aiCriteria());
    }

    public function test_skor_ai_langsung_menambah_xp_kelompok(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(80), 200)]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Bold menebalkan teks.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        // XP maksimum misi = 100 dasar + 20 bonus tanpa petunjuk = 120.
        // Bobot 70%, skor 80 -> 120 * 0.7 * 0.8 = 67.
        $this->assertSame(67, $team->fresh()->xp);

        $progress = TeamProgress::query()->where('team_id', $team->id)->firstOrFail();
        $this->assertSame(67, $progress->xp);

        // Misi tetap menunggu validasi bukti oleh guru.
        $this->assertSame(TeamProgress::STATUS_WAITING_VALIDATION, $progress->status);
    }

    public function test_permintaan_ke_ai_memakai_model_dan_kunci_yang_benar(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(), 200)]);

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer test-key-rahasia')
                && $request['model'] === 'deepseek-chat';
        });

        // Tujuan misi harus ikut dikirim sebagai konteks penilaian.
        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            return str_contains($body, 'Misi Format Teks')
                && str_contains($body, 'memformat teks')
                && str_contains($body, 'Apa fungsi fitur bold?');
        });
    }

    public function test_pesan_sukses_menyebut_skor_ai(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(88), 200)]);

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $response = $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $response->assertRedirect(route('student.waiting'));
        $response->assertSessionHas('success', fn ($msg) => str_contains($msg, '88'));
    }

    // -----------------------------------------------------------------
    // PENANGANAN GAGAL (tidak boleh menghambat siswa)
    // -----------------------------------------------------------------

    public function test_ai_gagal_tidak_membatalkan_kiriman_siswa(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response('Server error', 500)]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $response = $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        // Kiriman tetap tersimpan & tetap menunggu validasi guru.
        // (Setelah kirim, siswa diarahkan ke halaman menunggu ronde berikutnya.)
        $response->assertRedirect(route('student.waiting'));

        $submission = Submission::query()->firstOrFail();
        $this->assertSame(Submission::STATUS_WAITING, $submission->status);
        $this->assertSame(Submission::AI_ERROR, $submission->ai_status);
        $this->assertNull($submission->ai_score);

        // Tidak ada XP dari AI.
        $this->assertSame(0, $team->fresh()->xp);
    }

    public function test_koneksi_ai_putus_tidak_membuat_error_500(): void
    {
        Http::fake(['api.deepseek.com/*' => fn () => throw new ConnectionException('timeout')]);

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $submission = Submission::query()->firstOrFail();
        $this->assertSame(Submission::AI_ERROR, $submission->ai_status);
    }

    public function test_balasan_ai_bukan_json_ditandai_error(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Maaf, saya tidak bisa menilai.']]],
            ], 200),
        ]);

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $this->assertSame(Submission::AI_ERROR, Submission::query()->firstOrFail()->ai_status);
    }

    public function test_json_dalam_pagar_markdown_tetap_dibaca(): void
    {
        $payload = json_encode(['skor' => 65, 'umpan_balik' => 'Lumayan.', 'kriteria' => []]);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => "```json\n{$payload}\n```"]]],
            ], 200),
        ]);

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $submission = Submission::query()->firstOrFail();
        $this->assertSame(Submission::AI_SCORED, $submission->ai_status);
        $this->assertSame(65, $submission->ai_score);
    }

    public function test_skor_ai_di_luar_rentang_dipangkas(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['skor' => 150, 'umpan_balik' => 'x'])]]],
            ], 200),
        ]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $submission = Submission::query()->firstOrFail();
        $this->assertSame(100, $submission->ai_score);

        // Skor dipangkas ke 100. XP maksimum 120 (100 + 20 bonus), bobot 70%
        // -> 120 * 0.7 * 1.0 = 84. Tidak boleh melebihi XP final misi (120).
        $this->assertSame(84, $team->fresh()->xp);
    }

    public function test_jawaban_kosong_tidak_sampai_ke_ai(): void
    {
        Http::fake();

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        // Sejak mode materi penuh, jawaban wajib diisi sehingga permintaan
        // ditolak validasi lebih dulu dan AI tidak pernah dipanggil.
        $response = $this->post('/student/mission/'.$mission->id.'/submit', [
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $response->assertSessionHasErrors('answer');
        $this->assertDatabaseCount('submissions', 0);
        Http::assertNothingSent();
    }

    public function test_api_key_kosong_membuat_ai_tidak_dipanggil(): void
    {
        config()->set('ai.api_key', null);
        Http::fake();

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        // Status tetap pending karena AI tidak pernah dijalankan.
        $this->assertSame(Submission::AI_PENDING, Submission::query()->firstOrFail()->ai_status);
        Http::assertNothingSent();
    }

    public function test_ai_nonaktif_membuat_pemeriksaan_dilewati(): void
    {
        config()->set('ai.enabled', false);

        $this->assertFalse(app(AiReviewService::class)->isConfigured());
    }

    // -----------------------------------------------------------------
    // PENILAIAN ULANG SETELAH PERBAIKAN
    // -----------------------------------------------------------------

    public function test_kiriman_ulang_dinilai_ulang_dan_xp_diperbarui(): void
    {
        // fakeSequence dipakai karena Http::fake() kedua tidak menggantikan
        // fake pertama yang sudah terlanjur diselesaikan container.
        Http::fakeSequence()
            ->push($this->fakeAiResponse(50), 200)
            ->push($this->fakeAiResponse(95), 200);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban pertama.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        // XP maksimum 120 (100 + 20 bonus), bobot 70%, skor 50 -> 42.
        $this->assertSame(42, $team->fresh()->xp);

        // Kirim ulang dengan jawaban lebih baik.
        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban yang jauh lebih lengkap dan tepat.',
        ]);

        // Skor 95 -> 120 * 0.7 * 0.95 = 79.8 -> 80 XP (naik, tidak menurun).
        $this->assertSame(80, $team->fresh()->xp);
        $this->assertSame(95, Submission::query()->firstOrFail()->ai_score);
    }

    public function test_kiriman_ulang_dengan_skor_lebih_rendah_tidak_menurunkan_xp(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(90), 200)]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban pertama yang bagus.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        // XP maksimum 120 (100 + 20 bonus), bobot 70%, skor 90 -> 76 XP.
        $this->assertSame(76, $team->fresh()->xp);
    }

    // -----------------------------------------------------------------
    // XP DIBATALKAN SAAT GURU MEMINTA PERBAIKAN
    // -----------------------------------------------------------------

    public function test_xp_ai_dibatalkan_saat_guru_meminta_perbaikan(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(50), 200)]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban pertama yang belum tepat.',
        ]);

        // Skor 50 -> 120 * 0.7 * 0.5 = 42 XP sementara.
        $this->assertSame(42, $team->fresh()->xp);

        $submission = Submission::query()->firstOrFail();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_TEACHER]))
            ->post('/teacher/validations/'.$submission->id.'/revision', [
                'teacher_comment' => 'Jawaban nomor 1 masih salah, tolong perbaiki.',
            ]);

        // XP sementara harus dicabut, karena jawaban ini ditolak.
        $this->assertSame(0, $team->fresh()->xp);

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();

        $this->assertSame(0, $progress->xp);
        $this->assertSame(TeamProgress::STATUS_IN_PROGRESS, $progress->status);
    }

    public function test_kelompok_tidak_dapat_menimbun_xp_dari_jawaban_yang_ditolak(): void
    {
        // fakeSequence diperlukan karena Http::fake() kedua tidak menggantikan
        // fake pertama yang sudah diselesaikan container.
        Http::fakeSequence()
            ->push($this->fakeAiResponse(90), 200)
            ->push($this->fakeAiResponse(50), 200);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', ['answer' => 'Jawaban pertama.']);

        $firstXp = $team->fresh()->xp;
        $this->assertGreaterThan(0, $firstXp);

        $submission = Submission::query()->firstOrFail();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_TEACHER]))
            ->post('/teacher/validations/'.$submission->id.'/revision', ['teacher_comment' => 'ulangi']);

        $this->assertSame(0, $team->fresh()->xp);

        // Kiriman kedua dengan skor lebih rendah: XP hanya sebesar kiriman kedua,
        // bukan akumulasi dari kiriman pertama yang sudah ditolak.
        $this->post('/student/mission/'.$mission->id.'/submit', ['answer' => 'Jawaban kedua.']);

        // Skor 50 -> 42 XP, bukan 76 + 42.
        $this->assertSame(42, $team->fresh()->xp);
    }

    // -----------------------------------------------------------------
    // ANTI-DUPLIKASI XP
    // -----------------------------------------------------------------

    public function test_kiriman_berulang_tidak_menggandakan_xp(): void
    {
        // Kasus paling ekstrem: kirim 5 kali dengan skor sempurna.
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(100), 200)]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/student/mission/'.$mission->id.'/submit', [
                'answer' => "Jawaban kiriman ke-{$i} dengan skor penuh.",
            ]);
        }

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();

        // XP hanya 84 (120 * 0.7 * 1.0), bukan 84 x 5 = 420.
        $this->assertSame(84, $progress->xp);
        $this->assertSame(84, $team->fresh()->xp);

        // Hanya ada SATU baris kiriman per kelompok per misi.
        $this->assertSame(1, Submission::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->count());
    }

    public function test_kiriman_ulang_setelah_lulus_tidak_menambah_xp(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(100), 200)]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', ['answer' => 'Jawaban pertama.']);

        $submission = Submission::query()->firstOrFail();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_TEACHER]))
            ->post('/teacher/validations/'.$submission->id.'/approve', []);

        // Setelah lulus: XP penuh 120.
        $this->assertSame(120, $team->fresh()->xp);

        // Kirim ulang setelah lulus tidak boleh menambah XP.
        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Kiriman setelah misi dinyatakan lulus oleh guru.',
        ]);

        $this->assertSame(120, $team->fresh()->xp);

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();

        $this->assertSame(120, $progress->xp);
        $this->assertSame(TeamProgress::STATUS_COMPLETED, $progress->status);
    }

    public function test_total_xp_selalu_sama_dengan_jumlah_progress(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(80), 200)]);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        // Kirim berulang.
        for ($i = 1; $i <= 3; $i++) {
            $this->post('/student/mission/'.$mission->id.'/submit', ['answer' => "Jawaban ke-{$i}."]);
        }

        // xp tim harus selalu = jumlah xp seluruh progress, tidak pernah lebih.
        $this->assertSame(
            (int) TeamProgress::query()->where('team_id', $team->id)->sum('xp'),
            (int) $team->fresh()->xp
        );
    }

    public function test_skor_lebih_rendah_tidak_menurunkan_xp(): void
    {
        Http::fakeSequence()
            ->push($this->fakeAiResponse(90), 200)
            ->push($this->fakeAiResponse(30), 200);

        $team = $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban bagus.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $highXp = $team->fresh()->xp;
        $this->assertSame(76, $highXp);

        $this->post('/student/mission/'.$mission->id.'/submit', ['answer' => 'Jawaban buruk.']);

        // Skor turun ke 30 (25 XP), tetapi XP tidak boleh turun dari 76.
        $this->assertSame(30, Submission::query()->firstOrFail()->ai_score);
        $this->assertSame($highXp, $team->fresh()->xp);
    }

    // -----------------------------------------------------------------
    // TAMPILAN GURU
    // -----------------------------------------------------------------

    public function test_guru_melihat_skor_dan_umpan_balik_ai_di_halaman_validasi(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(77, 'Perlu contoh konkret.'), 200)]);

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $submission = Submission::query()->firstOrFail();

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_TEACHER]))
            ->get('/teacher/validations/'.$submission->id);

        $response->assertOk();
        $response->assertSee('Penilaian AI');
        $response->assertSee('77');
        $response->assertSee('Perlu contoh konkret.');
    }

    public function test_halaman_siswa_tidak_membocorkan_kunci_api(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response($this->fakeAiResponse(), 200)]);

        $this->joinTeam();
        $mission = Mission::query()->firstOrFail();

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji.',
            'evidence' => UploadedFile::fake()->image('bukti.png'),
        ]);

        $this->get('/student/mission/'.$mission->id)->assertDontSee('test-key-rahasia');
    }
}
