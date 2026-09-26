<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Services\AiReviewService;
use App\Services\GameScoreService;
use App\Services\MissionService;
use App\Services\RoundService;
use App\Services\ScoringService;
use App\Services\StudentAuthService;
use App\Services\SubmissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard misi siswa, halaman misi, game arcade, kirim bukti, dan petunjuk.
 */
class MissionController extends Controller
{
    public function __construct(
        protected StudentAuthService $studentAuth,
        protected MissionService $missions,
        protected SubmissionService $submissions,
        protected ScoringService $scoring,
        protected AiReviewService $aiReview,
        protected GameScoreService $gameScores,
    ) {}

    /**
     * Dashboard misi: daftar kartu misi + progres + timer.
     */
    public function dashboard()
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        $progressList = $this->missions->progressList($team);

        $completed = $progressList->where('status', TeamProgress::STATUS_COMPLETED)->count();
        $total = $progressList->count();
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        // Kelompok yang baru mulai mengerjakan rondenya setelah jam kelas habis
        // tetap boleh lanjut (jatah waktu ronde miliknya sendiri).
        $acceptsSubmissions = $session->acceptsSubmissionsFor($team);

        // Ronde yang sedang dibuka guru. Dipakai untuk menandai kartu misi mana
        // yang benar-benar bisa dikerjakan sekarang (sisanya masih terkunci),
        // supaya anak tidak menebak-nebak kartu mana yang aktif.
        $openRoundNumbers = $session->openRoundNumbers();

        return view('student.dashboard', compact(
            'team', 'session', 'progressList', 'completed', 'total', 'percent', 'acceptsSubmissions',
            'openRoundNumbers'
        ));
    }

    /**
     * Status ronde aktif untuk kelompok ini.
     *
     * Dipakai halaman misi & dashboard agar otomatis berpindah ketika guru
     * membuka ronde baru, sehingga murid tidak tertinggal di ronde lama.
     */
    public function roundStatus()
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        $rounds = app(RoundService::class);

        $currentRound = (int) $session->current_round;
        $mission = $currentRound > 0 ? $rounds->missionForRound($currentRound) : null;

        // Ronde yang sedang terbuka. Guru boleh membuka beberapa ronde sekaligus,
        // sehingga kelompok perlu tahu semua pilihannya (bukan hanya satu).
        $openRounds = collect($session->openRoundNumbers())
            ->map(function (int $order) use ($rounds) {
                $open = $rounds->missionForRound($order);

                return $open ? [
                    'id' => $open->id,
                    'order' => $order,
                    'title' => $open->title,
                    'url' => route('student.mission.show', $open),
                    'is_game' => $open->hasGame(),
                ] : null;
            })
            ->filter()
            ->values();

        // Kelompok boleh berpindah bila ada minimal satu ronde terbuka yang sudah
        // dibuka untuknya.
        $canWork = $openRounds->isNotEmpty()
            && TeamProgress::query()
                ->where('team_id', $team->id)
                ->whereIn('mission_id', $openRounds->pluck('id'))
                ->whereIn('status', [
                    TeamProgress::STATUS_AVAILABLE,
                    TeamProgress::STATUS_IN_PROGRESS,
                    TeamProgress::STATUS_WAITING_VALIDATION,
                ])
                ->exists();

        return response()->json([
            'round' => $currentRound,
            'total' => $rounds->totalRounds(),
            'status' => $session->round_status,
            'is_running' => $session->isRoundRunning(),
            'seconds_remaining' => $session->roundSecondsRemaining(),
            'formatted_remaining' => $session->formattedRoundRemaining(),
            'mission' => $mission ? [
                'id' => $mission->id,
                'title' => $mission->title,
                'url' => route('student.mission.show', $mission),
            ] : null,
            'open_rounds' => $openRounds->all(),
            // Bila beberapa ronde terbuka, halaman misi tidak boleh memaksa
            // pindah: kelompok bebas memilih ronde mana yang mau dikerjakan.
            'auto_switch' => $openRounds->count() <= 1,
            'can_work' => $canWork,
            'session_ended' => $session->isEnded(),
            'accepts_submissions' => $session->acceptsSubmissionsFor($team),
        ]);
    }

    /**
     * Halaman detail misi.
     */
    public function show(Mission $mission)
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        // Misi yang tidak ada / tidak aktif.
        if (! $mission->is_active) {
            abort(404, 'Misi yang kamu cari tidak ditemukan.');
        }

        // Misi terkunci tidak boleh dibuka.
        if (! $progress || $progress->isLocked()) {
            return redirect()
                ->route('student.dashboard')
                ->with('error', 'Misi ini masih terkunci. Selesaikan misi sebelumnya terlebih dahulu.');
        }

        // Jam kelas habis bukan alasan bagi kelompok yang baru mulai mengerjakan
        // ronde ini (mis. murid yang masuk terlambat). Penilaiannya HARUS
        // dilakukan sebelum markInProgress() mencatat waktu mulai yang baru —
        // kalau tidak, setiap kelompok akan terlihat sebagai pendatang baru.
        $acceptsSubmissions = $session->acceptsSubmissionsFor($team, $progress);
        $lateGrace = $acceptsSubmissions && $session->isTimeUp() && $progress->work_started_at === null;

        // Mulai mengerjakan saat dibuka (waktu mulai dicatat di sini).
        $this->missions->markInProgress($progress);

        $submission = Submission::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        // Petunjuk hanya untuk misi yang belum selesai.
        $hintsEnabled = $session->hints_enabled && ! $progress->isCompleted();

        // Batas waktu per siswa: dihitung sejak dia membuka halaman ronde ini.
        $workSeconds = $progress->isCompleted()
            ? null
            : $progress->workSecondsRemaining($session->round_duration_minutes);

        return view('student.mission', compact(
            'team', 'session', 'mission', 'progress', 'submission', 'hintsEnabled', 'workSeconds',
            'acceptsSubmissions', 'lateGrace'
        ));
    }

    /**
     * Simpan kiriman tugas (jawaban + bukti + file).
     */
    public function submit(Request $request, Mission $mission)
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        // Sesi sudah berakhir: tidak ada kiriman baru lagi (data lama tetap aman).
        if ($session->isEnded()) {
            return back()->with('error', 'Sesi sudah berakhir. Kiriman baru tidak dapat diproses.');
        }

        if (! $mission->is_active) {
            abort(404, 'Misi yang kamu cari tidak ditemukan.');
        }

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        if (! $progress || ! $progress->canSubmit()) {
            return back()->with('error', 'Misi ini belum bisa dikerjakan atau sudah selesai.');
        }

        // Ronde lama yang sudah ditutup guru: tolak kiriman baru.
        // (Jawaban yang sudah diketik tersimpan di browser siswa, tidak hilang.)
        if ($progress->isLocked()) {
            return back()->with('error',
                'Ronde misi ini sudah ditutup karena guru sudah membuka ronde berikutnya. '
                .'Kerjakan ronde yang sedang aktif.');
        }

        // Jam kelas habis → tolak kiriman baru, TETAPI kelompok yang baru mulai
        // mengerjakan ronde ini setelah jam kelas lewat (mis. murid yang masuk
        // terlambat) tidak ikut terkunci: mereka memakai jatah waktu ronde
        // miliknya sendiri.
        if (! $session->acceptsSubmissionsFor($team, $progress)) {
            return back()->with('error', 'Waktu sesi sudah habis. Kiriman baru tidak dapat diproses.');
        }

        // Batas waktu per siswa: dihitung sejak dia membuka halaman ronde.
        if ($progress->isWorkTimeUp($session->round_duration_minutes)) {
            return back()->with('error',
                'Waktu pengerjaanmu di ronde ini sudah habis. Tunggu ronde berikutnya dari guru.');
        }

        // Mode materi penuh: murid menjawab pertanyaan uraian, tanpa unggah file.
        // Kolom bukti masih diterima bila ada (mis. guru memintanya manual),
        // tetapi tidak pernah diwajibkan.
        $allowedExtensions = implode(',', config('tikmission.allowed_extensions'));
        $maxKb = (int) config('tikmission.upload_max_kb');

        $rules = [
            'answer' => ['required', 'string', 'min:10', 'max:8000'],
            'evidence' => ['nullable', 'file', 'mimes:'.$allowedExtensions, 'max:'.$maxKb],
            'file' => ['nullable', 'file', 'mimes:'.$allowedExtensions, 'max:'.$maxKb],
        ];

        $messages = [
            'answer.required' => 'Tuliskan jawabanmu dulu sebelum mengirim.',
            'answer.min' => 'Jawabanmu terlalu pendek. Jelaskan dengan kalimatmu sendiri minimal beberapa kalimat.',
            'answer.max' => 'Jawaban terlalu panjang (maksimal 8000 karakter).',
            'evidence.mimes' => 'Format lampiran tidak diizinkan. Gunakan jpg, jpeg, png, webp, pdf, atau docx.',
            'evidence.max' => 'Ukuran lampiran terlalu besar. Maksimal 10 MB.',
            'file.mimes' => 'Lampiran tambahan harus berformat PDF.',
            'file.max' => 'Ukuran lampiran terlalu besar. Maksimal 10 MB.',
        ];

        $validated = $request->validate($rules, $messages);

        $submission = $this->submissions->store($team, $progress, [
            'answer' => $validated['answer'] ?? null,
            'evidence' => $request->file('evidence'),
            'file' => $request->file('file'),
        ]);

        // Penilaian otomatis AI atas jawaban uraian (bila diaktifkan).
        // Skor AI langsung menambah XP sehingga papan skor guru bergerak,
        // dan guru tetap bisa menimpa nilainya.
        $aiApplied = $this->reviewWithAi($submission, $progress);

        $message = 'Jawaban berhasil dikirim. Tunggu validasi dari gurumu.';
        if ($aiApplied) {
            $message = 'Jawaban terkirim! AI memberi skor '.$submission->fresh()->ai_score.'/100. '
                .'Gurumu tetap bisa menyesuaikan nilai.';
        }

        return redirect()
            ->to($this->nextDestination($team, $mission)['url'])
            ->with('success', $message);
    }

    /**
     * Tentukan ke mana siswa diarahkan setelah mengirim jawaban.
     *
     * Bila ronde berikutnya sudah dibuka guru dan boleh dikerjakan kelompok
     * ini, siswa langsung diarahkan ke misi ronde berikutnya. Bila belum,
     * siswa diarahkan ke halaman "menunggu ronde" yang memantau sendiri.
     *
     * @return array{url: string, type: string}
     */
    protected function nextDestination(Team $team, Mission $mission): array
    {
        $rounds = app(RoundService::class);

        // Ronde berikutnya menurut urutan misi.
        $berikutnya = $rounds->missions()
            ->first(fn (Mission $m) => $m->order > $mission->order);

        if ($berikutnya) {
            $progress = TeamProgress::query()
                ->where('team_id', $team->id)
                ->where('mission_id', $berikutnya->id)
                ->first();

            // Sudah dibuka guru DAN boleh dikerjakan kelompok ini.
            if ($progress && $progress->isPlayable()) {
                return [
                    'url' => route('student.mission.show', $berikutnya),
                    'type' => 'next',
                ];
            }
        }

        // Belum ada ronde berikutnya yang terbuka: tunggu aba-aba guru.
        return [
            'url' => route('student.waiting'),
            'type' => 'waiting',
        ];
    }

    /**
     * Halaman menunggu ronde berikutnya dibuka.
     */
    public function waiting()
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        return view('student.waiting', compact('team', 'session'));
    }

    /**
     * Halaman GAME ARCADE untuk sebuah misi.
     *
     * Soal materi menjadi mekanik permainan: menjawab benar menambah tenaga,
     * menjawab salah mengurangi nyawa. Hasilnya dicatat untuk guru.
     */
    public function game(Mission $mission)
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        if (! $mission->hasGame()) {
            return redirect()
                ->route('student.mission.show', $mission)
                ->with('error', 'Ronde ini tidak memakai game. Kerjakan lewat halaman misi biasa.');
        }

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        if (! $progress || ! $progress->canSubmit()) {
            return redirect()
                ->route('student.dashboard')
                ->with('error', 'Ronde ini belum bisa dimainkan atau sudah selesai.');
        }

        if (! $session->acceptsSubmissionsFor($team, $progress)) {
            return redirect()
                ->route('student.mission.show', $mission)
                ->with('error', 'Waktu sesi sudah habis. Permainan tidak dapat dimulai.');
        }

        // Soal game TIDAK boleh dibocorkan bersama kuncinya. Yang dikirim ke
        // browser hanya pertanyaan + pilihan; kunci jawaban tetap di server
        // (lihat GameScoreService) supaya skor tidak bisa dipalsukan.
        $questions = collect($mission->gameQuestionList())
            ->map(fn (array $q) => [
                'pertanyaan' => $q['pertanyaan'],
                'pilihan' => $q['pilihan'],
            ])
            ->values()
            ->all();

        // Catat mulai mengerjakan (timer per siswa).
        $this->missions->markInProgress($progress);

        $submission = Submission::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        return view('student.game', compact('team', 'session', 'mission', 'progress', 'submission', 'questions'));
    }

    /**
     * Catat jawaban game.
     *
     * KEAMANAN: browser HANYA mengirim soal mana yang tampil dan pilihan mana
     * yang ditekan (`jawaban`: daftar {pertanyaan, pilihan}). Server menilai
     * ulang semuanya dengan kunci jawaban misi dan menghitung sendiri
     * benar / salah / skor. Angka apa pun dari klien diabaikan, sehingga siswa
     * tidak bisa memalsukan skor lewat DevTools atau permintaan langsung.
     */
    public function gameAnswer(Request $request, Mission $mission)
    {
        $team = $this->studentAuth->currentTeam();

        if (! $mission->hasGame()) {
            return response()->json(['ok' => false, 'message' => 'Ronde ini bukan ronde game.'], 422);
        }

        $sudahMulai = $team->progress()
            ->where('mission_id', $mission->id)
            ->whereIn('status', [
                TeamProgress::STATUS_AVAILABLE,
                TeamProgress::STATUS_IN_PROGRESS,
                TeamProgress::STATUS_WAITING_VALIDATION,
            ])
            ->exists();

        if (! $sudahMulai) {
            return response()->json(['ok' => false, 'message' => 'Ronde ini belum bisa dimainkan.'], 422);
        }

        $validated = $request->validate([
            'jawaban' => ['required', 'array', 'min:1', 'max:200'],
            'jawaban.*.pertanyaan' => ['required', 'string', 'max:500'],
            'jawaban.*.pilihan' => ['nullable', 'string', 'max:500'],
        ], [
            'jawaban.required' => 'Tidak ada jawaban permainan yang dikirim.',
            'jawaban.min' => 'Tidak ada jawaban permainan yang dikirim.',
        ]);

        $progress = $team->progress()->where('mission_id', $mission->id)->firstOrFail();
        $submission = $this->gameScores->submissionFor($team, $mission);

        // Server yang menilai: benar/salah/skor dihitung dari kunci jawaban misi.
        $hasil = $this->gameScores->evaluate($progress, $submission, $mission, $validated['jawaban']);

        return response()->json([
            'ok' => true,
            'message' => 'Hasil permainan tersimpan.',
            'benar' => $hasil['benar'],
            'salah' => $hasil['salah'],
            'skor' => $hasil['skor'],
            'total' => $hasil['total'],
            // Total XP misi ini setelah permainan dinilai server.
            'xp' => $hasil['xp'],
            // Tambahan XP dari permainan yang BARU SAJA selesai, supaya anak
            // melihat langsung efek jawabannya di papan hasil.
            'xp_gain' => $hasil['xp_gain'],
            'total_xp' => $hasil['total_xp'],
        ]);
    }

    /**
     * Jalankan penilaian AI dan beri XP sementara.
     * Kegagalan AI tidak boleh mengganggu proses siswa.
     */
    protected function reviewWithAi(Submission $submission, TeamProgress $progress): bool
    {
        if (! $this->aiReview->isConfigured()) {
            return false;
        }

        $scored = $this->aiReview->review($submission);

        if ($scored) {
            $this->scoring->awardFromAi($progress, $submission->fresh());
        }

        return $scored;
    }

    /**
     * Buka petunjuk (mengurangi XP).
     */
    public function hint(Request $request, Mission $mission)
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        if (! $session->hints_enabled) {
            return response()->json([
                'ok' => false,
                'message' => 'Sistem petunjuk sedang dinonaktifkan oleh guru.',
            ], 403);
        }

        $validated = $request->validate([
            'level' => ['required', 'integer', Rule::in([1, 2])],
        ], [
            'level.required' => 'Level petunjuk tidak dikenali.',
            'level.in' => 'Level petunjuk tidak dikenali.',
        ]);

        $progress = TeamProgress::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        // Petunjuk hanya untuk misi yang sedang/akan dikerjakan.
        if (! $progress || ! $progress->canSubmit() || $progress->isCompleted()) {
            return response()->json([
                'ok' => false,
                'message' => 'Petunjuk tidak tersedia untuk misi ini.',
            ], 403);
        }

        $level = (int) $validated['level'];
        $text = $level === 1 ? $mission->hint_1 : $mission->hint_2;

        if (! $text) {
            return response()->json([
                'ok' => false,
                'message' => 'Petunjuk ini belum tersedia.',
            ], 404);
        }

        // Hanya hitung sekali per level.
        $alreadyUsed = $progress->hints_used >= $level;

        if (! $alreadyUsed) {
            $this->scoring->registerHintUse($progress);
        }

        return response()->json([
            'ok' => true,
            'level' => $level,
            'text' => $text,
            'hints_used' => $progress->fresh()->hints_used,
            'penalty' => $alreadyUsed ? 0 : (int) config('tikmission.hint_penalty_xp'),
            'message' => $alreadyUsed
                ? 'Petunjuk ini sudah pernah kamu buka.'
                : 'Kamu memakai satu petunjuk. XP berkurang '.config('tikmission.hint_penalty_xp').'.',
        ]);
    }
}
