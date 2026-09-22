<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\TeamProgress;
use App\Services\AiReviewService;
use App\Services\MissionService;
use App\Services\RoundService;
use App\Services\ScoringService;
use App\Services\StudentAuthService;
use App\Services\SubmissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard misi siswa, halaman misi, kirim bukti, dan petunjuk.
 */
class MissionController extends Controller
{
    public function __construct(
        protected StudentAuthService $studentAuth,
        protected MissionService $missions,
        protected SubmissionService $submissions,
        protected ScoringService $scoring,
        protected AiReviewService $aiReview,
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

        return view('student.dashboard', compact(
            'team', 'session', 'progressList', 'completed', 'total', 'percent'
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

        // Kelompok boleh berpindah bila misi ronde aktif sudah dibuka untuknya.
        $canWork = false;

        if ($mission) {
            $progress = TeamProgress::query()
                ->where('team_id', $team->id)
                ->where('mission_id', $mission->id)
                ->first();

            $canWork = $progress !== null && ! $progress->isLocked();
        }

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
            'can_work' => $canWork,
            'session_ended' => $session->isEnded(),
            'accepts_submissions' => $session->acceptsSubmissions(),
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

        // Mulai mengerjakan saat dibuka.
        $this->missions->markInProgress($progress);

        $submission = Submission::query()
            ->where('team_id', $team->id)
            ->where('mission_id', $mission->id)
            ->first();

        // Petunjuk hanya untuk misi yang belum selesai.
        $hintsEnabled = $session->hints_enabled && ! $progress->isCompleted();

        return view('student.mission', compact(
            'team', 'session', 'mission', 'progress', 'submission', 'hintsEnabled'
        ));
    }

    /**
     * Simpan kiriman tugas (jawaban + bukti + file).
     */
    public function submit(Request $request, Mission $mission)
    {
        $team = $this->studentAuth->currentTeam();
        $session = $team->gameSession;

        // Timer habis → tolak kiriman baru, tetapi data lama tetap aman.
        if (! $session->acceptsSubmissions()) {
            return back()->with('error',
                $session->isEnded()
                    ? 'Sesi sudah berakhir. Kiriman baru tidak dapat diproses.'
                    : 'Waktu sesi sudah habis. Kiriman baru tidak dapat diproses.');
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
            ->route('student.mission.show', $mission)
            ->with('success', $message);
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
