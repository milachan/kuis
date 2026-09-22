<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Services\MissionService;
use App\Services\ScoringService;
use App\Services\SubmissionService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kelola kelompok dan validasi bukti siswa.
 */
class TeamController extends Controller
{
    public function __construct(
        protected MissionService $missions,
        protected TeamService $teams,
        protected ScoringService $scoring,
    ) {}

    /**
     * Daftar semua kelompok beserta progresnya.
     */
    public function index(Request $request)
    {
        $sessionFilter = $request->query('session_id');

        $teams = Team::query()
            ->with(['gameSession', 'members'])
            ->withCount(['members'])
            ->when($sessionFilter, fn ($q) => $q->where('game_session_id', $sessionFilter))
            ->withCount([
                'progress as completed_count' => fn ($q) => $q->where('status', TeamProgress::STATUS_COMPLETED),
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totalMissions = Mission::query()->active()->count();

        $sessionOptions = GameSession::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code']);

        return view('teacher.teams.index', compact('teams', 'totalMissions', 'sessionOptions', 'sessionFilter'));
    }

    /**
     * Detail kelompok: progres tiap misi + riwayat kiriman.
     */
    public function show(Team $team)
    {
        $team->load(['gameSession', 'members']);

        $missionList = Mission::query()->active()->ordered()->get();
        $progress = $team->progress()->get()->keyBy('mission_id');
        $codes = $this->missions->codesForSession($team->gameSession);

        $submissions = $team->submissions()
            ->with('mission')
            ->get()
            ->keyBy('mission_id');

        return view('teacher.teams.show', compact(
            'team', 'missionList', 'progress', 'submissions', 'codes'
        ));
    }

    /**
     * Reset progres kelompok.
     */
    public function reset(Team $team, SubmissionService $submissions)
    {
        $this->teams->resetProgress($team, $submissions);

        return back()->with('success',
            'Progres kelompok '.$team->name.' berhasil direset ke Misi 01.');
    }

    /**
     * Hapus kelompok.
     */
    public function destroy(Team $team, SubmissionService $submissions)
    {
        $name = $team->name;
        $session = $team->gameSession;
        $this->teams->delete($team, $submissions);

        return redirect()
            ->route('teacher.sessions.show', $session)
            ->with('success', 'Kelompok '.$name.' berhasil dihapus.');
    }

    /**
     * Buka misi secara manual untuk sebuah kelompok.
     */
    public function unlock(Request $request, Team $team)
    {
        $validated = $request->validate([
            'mission_id' => ['required', 'integer', 'exists:missions,id'],
        ], [
            'mission_id.required' => 'Misi tidak dipilih.',
            'mission_id.exists' => 'Misi tidak ditemukan.',
        ]);

        $mission = Mission::query()->findOrFail($validated['mission_id']);
        $this->missions->unlockManually($team, $mission);

        return back()->with('success',
            'Misi "'.$mission->title.'" dibuka untuk kelompok '.$team->name.'.');
    }

    /**
     * Kunci kembali sebuah misi secara manual.
     */
    public function lock(Request $request, Team $team)
    {
        $validated = $request->validate([
            'mission_id' => ['required', 'integer', 'exists:missions,id'],
        ]);

        $mission = Mission::query()->findOrFail($validated['mission_id']);
        $this->missions->lockManually($team, $mission);

        return back()->with('success',
            'Misi "'.$mission->title.'" dikunci kembali untuk kelompok '.$team->name.'.');
    }

    // -----------------------------------------------------------------
    // VALIDASI
    // -----------------------------------------------------------------

    /**
     * Daftar tugas yang menunggu validasi + riwayat.
     */
    public function validations(Request $request)
    {
        $statusFilter = $request->query('status', Submission::STATUS_WAITING);

        $submissions = Submission::query()
            ->with(['team.gameSession', 'mission'])
            ->when(in_array($statusFilter, ['waiting_validation', 'approved', 'revision'], true),
                fn ($q) => $q->where('status', $statusFilter))
            ->orderByRaw("FIELD(status, 'waiting_validation') DESC")
            ->latest('submitted_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'waiting_validation' => Submission::query()->where('status', Submission::STATUS_WAITING)->count(),
            'approved' => Submission::query()->where('status', Submission::STATUS_APPROVED)->count(),
            'revision' => Submission::query()->where('status', Submission::STATUS_REVISION)->count(),
        ];

        return view('teacher.validations.index', compact('submissions', 'statusFilter', 'counts'));
    }

    /**
     * Detail validasi satu kiriman.
     */
    public function showValidation(Submission $submission)
    {
        $submission->load(['team.gameSession', 'mission']);

        $progress = TeamProgress::query()
            ->where('team_id', $submission->team_id)
            ->where('mission_id', $submission->mission_id)
            ->first();

        $previewXp = $progress
            ? $this->scoring->previewFor($progress, $submission->team->gameSession)
            : 0;

        return view('teacher.validations.show', compact('submission', 'progress', 'previewXp'));
    }

    /**
     * Lulus: misi COMPLETED, XP bertambah, misi berikutnya terbuka.
     */
    public function approve(Request $request, Submission $submission)
    {
        $validated = $request->validate([
            'teacher_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $team = $submission->team;
        $session = $team->gameSession;

        $progress = TeamProgress::query()
            ->where('team_id', $submission->team_id)
            ->where('mission_id', $submission->mission_id)
            ->first();

        if (! $progress) {
            return back()->with('error', 'Data progres misi tidak ditemukan.');
        }

        DB::transaction(function () use ($submission, $progress, $session, $validated, $team) {
            // Tandai kiriman lulus.
            $submission->update([
                'status' => Submission::STATUS_APPROVED,
                'teacher_comment' => $validated['teacher_comment'] ?? null,
                'validated_at' => now(),
            ]);

            // Beri XP & tandai misi selesai.
            $this->scoring->awardForCompletion($progress, $session);

            // Buka misi berikutnya.
            $this->missions->unlockNext($progress->fresh());

            // Tandai kelompok selesai bila semua misi tuntas.
            if ($team->fresh()->hasFinishedAll()) {
                $team->update(['completed_at' => $team->completed_at ?? now()]);
            }
        });

        return redirect()
            ->route('teacher.validations')
            ->with('success', 'Misi "'.$submission->mission->title.'" untuk '.$team->name
                .' dinyatakan LULUS. Misi berikutnya telah terbuka.');
    }

    /**
     * Perlu perbaikan: kembalikan ke siswa dengan komentar.
     */
    public function requestRevision(Request $request, Submission $submission)
    {
        $validated = $request->validate([
            'teacher_comment' => ['required', 'string', 'max:1000'],
        ], [
            'teacher_comment.required' => 'Tuliskan catatan perbaikan untuk siswa.',
        ]);

        $submission->update([
            'status' => Submission::STATUS_REVISION,
            'teacher_comment' => $validated['teacher_comment'],
            'validated_at' => now(),
        ]);

        // Kembalikan misi ke status dikerjakan agar siswa bisa kirim ulang.
        $progress = TeamProgress::query()
            ->where('team_id', $submission->team_id)
            ->where('mission_id', $submission->mission_id)
            ->first();

        if ($progress) {
            // Cabut XP sementara yang sempat diberikan AI, karena jawaban ini
            // ditolak dan misi harus dikerjakan ulang. Tanpa ini, kelompok bisa
            // mengumpulkan XP dari jawaban yang tidak disetujui guru.
            if ($progress->xp > 0) {
                $progress->update(['xp' => 0]);
                $progress->team->recalculateXp();
            }

            $progress->update(['status' => TeamProgress::STATUS_IN_PROGRESS]);
        }

        return redirect()
            ->route('teacher.validations')
            ->with('warning', 'Kiriman '.$submission->team->name.' dikembalikan untuk perbaikan. '
                .'XP sementara dari jawaban ini dibatalkan.');
    }
}
