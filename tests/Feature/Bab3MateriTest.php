<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Models\User;
use App\Services\MissionService;
use App\Services\RoundService;
use Database\Seeders\Bab3MateriSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Materi penuh Bab Sistem Komputer: 7 ronde, tanpa kewajiban unggah bukti,
 * dan penilaian AI atas jawaban uraian.
 */
class Bab3MateriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        config()->set('ai.enabled', true);
        config()->set('ai.api_key', 'kunci-uji');
        config()->set('tikmission.ai_xp_weight_percent', 70);

        // Pakai seeder materi sungguhan agar yang diuji adalah data nyata.
        $this->seed(Bab3MateriSeeder::class);
    }

    protected function fakeAi(int $score = 85, ?int $authenticity = null, ?string $note = null, ?bool $ownWords = null): void
    {
        $payload = [
            'skor' => $score,
            'umpan_balik' => 'Bagus, pertahankan.',
            'kriteria' => [
                ['kriteria' => 'Pertanyaan 1', 'nilai' => $score],
                ['kriteria' => 'Keseluruhan', 'nilai' => $score],
            ],
        ];

        if ($authenticity !== null) {
            $payload['keaslian'] = $authenticity;
            $payload['catatan_keaslian'] = $note ?? 'Terlihat wajar.';
        }

        if ($ownWords !== null) {
            $payload['bahasa_sendiri'] = $ownWords;
        }

        Http::fake(['*' => Http::response([
            'model' => 'deepseek-chat',
            'choices' => [['message' => ['content' => json_encode($payload)]]],
        ], 200)]);
    }

    protected function joinTeam(string $name = 'Kelompok Materi'): Team
    {
        $this->post('/student/join', [
            'code' => 'TIK8-DEMO',
            'team_name' => $name,
            'members' => ['Ana'],
        ]);

        return Team::query()->where('name', $name)->firstOrFail();
    }

    // -----------------------------------------------------------------
    // DATA MATERI
    // -----------------------------------------------------------------

    public function test_ada_tujuh_ronde_dari_materi_bab_4(): void
    {
        $this->assertSame(7, app(RoundService::class)->totalRounds());

        $missions = Mission::query()->active()->ordered()->get();

        $this->assertCount(7, $missions);

        // Nomor ronde harus berurutan 1..7.
        $this->assertSame(range(1, 7), $missions->pluck('order')->all());

        // Setiap ronde punya 1-2 soal saja, tetapi bervariasi jenisnya.
        foreach ($missions as $mission) {
            $this->assertGreaterThanOrEqual(1, $mission->questionCount());
            $this->assertLessThanOrEqual(2, $mission->questionCount());
        }
    }

    public function test_materi_mencakup_komponen_dan_heksadesimal(): void
    {
        $slugs = Mission::query()->active()->pluck('slug')->all();

        // A. Komponen sistem komputer (hardware, software, brainware).
        $this->assertContains('komponen-sistem-komputer', $slugs);
        // Perangkat masukan/keluaran.
        $this->assertContains('perangkat-io', $slugs);
        // Pemrosesan (CPU).
        $this->assertContains('cpu-pemrosesan', $slugs);
        // Penyimpanan & cloud.
        $this->assertContains('penyimpanan-cloud', $slugs);
        // Sistem operasi.
        $this->assertContains('sistem-operasi', $slugs);
        // Aplikasi & bahasa pemrograman.
        $this->assertContains('aplikasi-pemrograman', $slugs);
        // B. Bilangan heksadesimal.
        $this->assertContains('heksadesimal', $slugs);
    }

    public function test_jenis_soal_uraian_bervariasi_antar_ronde(): void
    {
        $semuaJenis = Mission::query()->active()->get()
            ->flatMap(fn ($m) => collect($m->questionList())->pluck('jenis'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Soal uraian pendamping game: jenisnya bervariasi antara pendapat,
        // materi, dan saran. Variasi soal cepat sendiri ada di game.
        $this->assertGreaterThanOrEqual(
            3,
            count($semuaJenis),
            'Jenis soal uraian kurang bervariasi: '.implode(', ', $semuaJenis)
        );

        foreach (['materi', 'pendapat', 'saran'] as $wajib) {
            $this->assertContains($wajib, $semuaJenis, "Jenis soal '{$wajib}' tidak ditemukan.");
        }
    }

    // -----------------------------------------------------------------
    // SUBMIT TANPA UNGGAH FILE
    // -----------------------------------------------------------------

    public function test_siswa_dapat_mengirim_jawaban_tanpa_unggah_file(): void
    {
        $this->fakeAi();
        $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();

        // Buka misi (guru membuka lewat ronde / siswa lewat kode).
        app(MissionService::class)->unlockManually(
            Team::query()->firstOrFail(),
            $mission
        );

        $response = $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Perangkat lunak aplikasi adalah program siap pakai yang membantu pengguna '
                .'menyelesaikan tugas tertentu. Contohnya pengolah kata untuk menulis dokumen.',
        ]);

        $response->assertRedirect();

        $submission = Submission::query()->firstOrFail();

        // Kiriman tersimpan tanpa file bukti sama sekali.
        $this->assertNull($submission->evidence_path);
        $this->assertNull($submission->file_path);
        $this->assertSame(Submission::AI_SCORED, $submission->ai_status);
        $this->assertSame(85, $submission->ai_score);
    }

    public function test_jawaban_kosong_ditolak(): void
    {
        $this->fakeAi();
        $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually(Team::query()->firstOrFail(), $mission);

        $response = $this->post('/student/mission/'.$mission->id.'/submit', ['answer' => '']);

        $response->assertSessionHasErrors('answer');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_jawaban_terlalu_pendek_ditolak(): void
    {
        $this->fakeAi();
        $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually(Team::query()->firstOrFail(), $mission);

        $response = $this->post('/student/mission/'.$mission->id.'/submit', ['answer' => 'tidak tau']);

        $response->assertSessionHasErrors('answer');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_lampiran_tetap_diterima_bila_siswa_mengirim(): void
    {
        $this->fakeAi();
        $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually(Team::query()->firstOrFail(), $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban lengkap dengan lampiran opsional untuk menguji unggahan.',
            'evidence' => UploadedFile::fake()->image('catatan.png'),
        ]);

        // Lampiran boleh, tetapi tidak pernah diwajibkan.
        $this->assertNotNull(Submission::query()->firstOrFail()->evidence_path);
    }

    // -----------------------------------------------------------------
    // AI MENERIMA KONTEKS PERTANYAAN
    // -----------------------------------------------------------------

    public function test_ai_menerima_daftar_pertanyaan_dan_materi_misi(): void
    {
        $this->fakeAi();
        $this->joinTeam();

        $mission = Mission::query()->where('order', 2)->firstOrFail();
        app(MissionService::class)->unlockManually(Team::query()->firstOrFail(), $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji untuk memeriksa konteks yang dikirim.',
        ]);

        Http::assertSent(function ($request) use ($mission) {
            $body = json_encode($request->data());

            // Judul misi memuat tanda khusus (— dan &) yang berubah bentuk di
            // JSON, jadi dicek lewat potongan kata yang aman saja.
            $titleFragment = 'Bata Input';
            $firstQuestion = $mission->questionList()[0]['pertanyaan'];

            return str_contains($body, $titleFragment)
                && str_contains($body, 'MATERI')
                && str_contains($body, mb_substr($firstQuestion, 0, 30))
                && str_contains($body, 'JAWABAN SISWA');
        });
    }

    public function test_skor_ai_memberi_xp_pada_ronde_berbobot_besar(): void
    {
        $this->fakeAi(100);
        $team = $this->joinTeam();

        // Ronde 7 berbobot 150 XP.
        $mission = Mission::query()->where('order', 7)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban lengkap untuk ronde terakhir dengan penjelasan yang cukup panjang.',
        ]);

        // XP maksimum = 150 dasar + 20 bonus tanpa petunjuk = 170.
        // Bobot 70%, skor 100 -> 119.
        $this->assertSame(119, $team->fresh()->xp);
    }

    // -----------------------------------------------------------------
    // SOAL SINGKAT & JENIS YANG BERVARIASI
    // -----------------------------------------------------------------

    public function test_setiap_ronde_punya_satu_atau_dua_pertanyaan(): void
    {
        foreach (Mission::query()->active()->ordered()->get() as $mission) {
            // Cukup 1-2 soal per ronde agar murid tidak lelah mengetik.
            $this->assertGreaterThanOrEqual(1, $mission->questionCount());
            $this->assertLessThanOrEqual(
                2,
                $mission->questionCount(),
                "Ronde {$mission->order} seharusnya maksimal 2 pertanyaan."
            );
        }
    }

    public function test_setiap_soal_punya_jenis_yang_jelas(): void
    {
        foreach (Mission::query()->active()->ordered()->get() as $mission) {
            foreach ($mission->questionList() as $i => $q) {
                $this->assertNotEmpty(
                    $q['jenis'],
                    'Soal '.($i + 1)." ronde {$mission->order} belum punya jenis."
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // BONUS XP BAHASA SENDIRI
    // -----------------------------------------------------------------

    public function test_bonus_xp_diberikan_saat_jawaban_pakai_bahasa_sendiri(): void
    {
        // Skor rendah (jawaban kurang tepat), TETAPI bahasa sendiri = true.
        $this->fakeAi(30, 5, 'Wajar, menulis sendiri.', true);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Aku ngga begitu paham tapi kayaknya komputer butuh listrik biar nyala.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertTrue($submission->own_words);

        // XP = (120 * 0.7 * 0.3) + bonus 15 = 25 + 15 = 40.
        // Jadi meskipun jawabannya kurang tepat (skor 30), siswa tetap dapat XP usaha.
        $this->assertSame(15, $submission->own_words_bonus);
        $this->assertSame(40, $team->fresh()->xp);
    }

    public function test_tanpa_bahasa_sendiri_tidak_ada_bonus(): void
    {
        $this->fakeAi(30, 2, 'Mirip salinan.', false);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Perangkat lunak aplikasi merupakan program siap pakai yang dirancang.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertFalse($submission->own_words);
        $this->assertSame(0, $submission->own_words_bonus);

        // XP hanya dari skor: 120 * 0.7 * 0.3 = 25.
        $this->assertSame(25, $team->fresh()->xp);
    }

    public function test_bonus_usaha_tidak_melebihi_xp_maksimum_misi(): void
    {
        // Skor penuh + bonus: tidak boleh melebihi XP maksimum misi (120).
        $this->fakeAi(100, 5, 'Sangat asli.', true);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban dengan bahasa sendiri yang isinya juga sudah tepat.',
        ]);

        // 120 * 0.7 * 1.0 = 84, + bonus 15 = 99. Masih di bawah 120, aman.
        $this->assertSame(99, $team->fresh()->xp);
        $this->assertLessThanOrEqual(120, $team->fresh()->xp);
    }

    public function test_jawaban_salah_tetap_dapat_xp_lebih_bila_pakai_bahasa_sendiri(): void
    {
        // Dua skenario dengan SKOR SAMA, bedanya hanya bahasa sendiri.
        $this->fakeAi(40, 5, 'Menulis sendiri.', true);
        $teamA = $this->joinTeam('Kelompok Bahasa Sendiri');
        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($teamA, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Aku coba jawab pake pikiranku sendiri walaupun mungkin salah.',
        ]);

        $xpDenganUsaha = $teamA->fresh()->xp;

        // XP skor saja tanpa bonus = 120 * 0.7 * 0.4 = 34 (dibulatkan 34).
        // Dengan bonus 15 -> 49.
        $this->assertSame(49, $xpDenganUsaha);
    }

    public function test_guru_melihat_bonus_bahasa_sendiri(): void
    {
        $this->fakeAi(30, 5, 'Wajar.', true);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Aku ngga tau pasti tapi kayaknya gitu deh.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $response = $this->actingAs(User::factory()->create([
            'role' => User::ROLE_TEACHER,
        ]))->get('/teacher/validations/'.$submission->id);

        $response->assertOk();
        $response->assertSee('BONUS USAHA');
        $response->assertSee('bahasanya sendiri');
    }

    public function test_ai_diminta_menilai_bahasa_sendiri(): void
    {
        $this->fakeAi(50, 4, 'cukup', true);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji untuk memeriksa prompt bahasa sendiri.',
        ]);

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            return str_contains($body, 'bahasa_sendiri')
                && str_contains($body, 'BAHASA SENDIRI');
        });
    }

    public function test_ai_tanpa_field_bahasa_sendiri_tetap_berjalan(): void
    {
        // Model lama tidak mengirim "bahasa_sendiri": tidak boleh error.
        $this->fakeAi(70);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji tanpa field bahasa sendiri dari AI.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(Submission::AI_SCORED, $submission->ai_status);
        $this->assertNull($submission->own_words);
        $this->assertSame(0, $submission->own_words_bonus);
    }

    public function test_pertanyaan_dirancang_singkat(): void
    {
        foreach (Mission::query()->active()->get() as $mission) {
            foreach ($mission->questionList() as $i => $q) {
                // Pertanyaan harus pendek agar murid tidak keberatan membacanya.
                $this->assertLessThan(
                    160,
                    mb_strlen($q['pertanyaan']),
                    'Pertanyaan '.($i + 1)." ronde {$mission->order} terlalu panjang."
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // INDIKATOR KEASLIAN
    // -----------------------------------------------------------------

    public function test_skor_keaslian_dari_ai_disimpan(): void
    {
        $this->fakeAi(85, 5, 'Ada salah ketik wajar dan menyebut pengalaman sendiri.');
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Aplikasi itu program siap pakai buat ngerjain tugas, kaya Word gitu.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(5, $submission->authenticity_score);
        $this->assertSame('Ada salah ketik wajar dan menyebut pengalaman sendiri.', $submission->authenticity_note);
        $this->assertTrue($submission->hasAuthenticity());
        $this->assertSame('Sangat Asli', $submission->authenticityLabel());
        $this->assertFalse($submission->needsAuthenticityCheck());
    }

    public function test_keaslian_rendah_ditandai_untuk_diperiksa(): void
    {
        $this->fakeAi(90, 2, 'Bahasa sangat rapi tanpa detail pengalaman.');
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Perangkat lunak aplikasi merupakan program yang dirancang untuk membantu pengguna.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(2, $submission->authenticity_score);
        $this->assertTrue($submission->needsAuthenticityCheck());
        $this->assertSame('Mirip Salinan', $submission->authenticityLabel());
    }

    public function test_skor_keaslian_di_luar_rentang_dipangkas(): void
    {
        $this->fakeAi(80, 99, 'catatan');
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji yang cukup panjang untuk dikirim.',
        ]);

        // Nilai lebih dari 5 dibatasi ke 5.
        $this->assertSame(5, Submission::query()->firstOrFail()->authenticity_score);
    }

    public function test_ai_tanpa_field_keaslian_tetap_berjalan(): void
    {
        // Model lama mungkin tidak mengirim "keaslian" sama sekali.
        $this->fakeAi(75);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji tanpa indikator keaslian dari AI.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(Submission::AI_SCORED, $submission->ai_status);
        $this->assertSame(75, $submission->ai_score);
        $this->assertNull($submission->authenticity_score);
        $this->assertFalse($submission->hasAuthenticity());
    }

    public function test_guru_melihat_indikator_keaslian_di_halaman_validasi(): void
    {
        $this->fakeAi(60, 2, 'Struktur sangat rapi dan seragam.');
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban yang sangat rapi tanpa kesalahan penulisan sama sekali.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $response = $this->actingAs(User::factory()->create([
            'role' => User::ROLE_TEACHER,
        ]))->get('/teacher/validations/'.$submission->id);

        $response->assertOk();
        $response->assertSee('Indikator Keaslian');
        $response->assertSee('Mirip Salinan');
        $response->assertSee('Struktur sangat rapi dan seragam.');
        // Harus ada pengingat bahwa ini sinyal, bukan bukti.
        $response->assertSee('bukan bukti');
    }

    public function test_halaman_siswa_tidak_menampilkan_skor_keaslian(): void
    {
        $this->fakeAi(85, 1, 'Sangat mirip salinan AI.');
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji untuk memeriksa tampilan siswa.',
        ]);

        // Murid tidak boleh melihat indikator keaslian (bisa memancing perselisihan).
        $response = $this->get('/student/mission/'.$mission->id);

        $response->assertOk();
        $response->assertDontSee('Indikator Keaslian');
        $response->assertDontSee('Sangat mirip salinan AI');
    }

    public function test_permintaan_ke_ai_memuat_instruksi_keaslian(): void
    {
        $this->fakeAi(80, 4);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban uji untuk memeriksa prompt yang dikirim ke AI.',
        ]);

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            return str_contains($body, 'keaslian')
                && str_contains($body, 'catatan_keaslian')
                && str_contains($body, 'SINGKAT');
        });
    }

    public function test_halaman_misi_menampilkan_daftar_pertanyaan(): void
    {
        $this->fakeAi();
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $response = $this->get('/student/mission/'.$mission->id);

        $response->assertOk();
        $response->assertSee('Pertanyaan');
        $response->assertSee('komponen sistem komputer');

        // Tidak boleh lagi menyebut kewajiban unggah bukti.
        $response->assertDontSee('Wajib PDF');
        $response->assertDontSee('File PDF Hasil Ekspor');
    }

    public function test_form_tidak_mewajibkan_unggah_bukti(): void
    {
        $this->fakeAi();
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $html = $this->get('/student/mission/'.$mission->id)->getContent();

        // Tidak ada penanda wajib pada input file.
        $this->assertStringNotContainsString('Bukti Screenshot', $html);
        $this->assertStringContainsString('Lampiran opsional', $html);
    }

    public function test_progres_misi_dibuat_untuk_ketujuh_ronde(): void
    {
        $team = $this->joinTeam();

        // Misi 1 otomatis tersedia, sisanya terkunci.
        $this->assertSame(
            7,
            TeamProgress::query()->where('team_id', $team->id)->count()
        );

        $first = TeamProgress::query()->where('team_id', $team->id)
            ->where('mission_id', Mission::query()->where('order', 1)->value('id'))
            ->firstOrFail();

        $this->assertSame(TeamProgress::STATUS_AVAILABLE, $first->status);
    }

    public function test_guru_melihat_pertanyaan_di_halaman_validasi(): void
    {
        $this->fakeAi(77);
        $team = $this->joinTeam();

        $mission = Mission::query()->where('order', 1)->firstOrFail();
        app(MissionService::class)->unlockManually($team, $mission);

        $this->post('/student/mission/'.$mission->id.'/submit', [
            'answer' => 'Jawaban untuk diuji tampilannya di halaman guru.',
        ]);

        $submission = Submission::query()->firstOrFail();

        $response = $this->actingAs(User::factory()->create([
            'role' => User::ROLE_TEACHER,
        ]))->get('/teacher/validations/'.$submission->id);

        $response->assertOk();
        $response->assertSee('Penilaian AI');
        $response->assertSee('77');
    }
}
