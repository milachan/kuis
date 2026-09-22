<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use App\Services\MissionService;
use App\Services\SubmissionService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Dashboard guru: ringkasan, sesi, kelompok, validasi, dan laporan.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected MissionService $missions,
        protected TeamService $teams,
        protected SubmissionService $submissions,
    ) {}

    /**
     * Ringkasan utama.
     */
    public function index()
    {
        $stats = [
            'active_sessions' => GameSession::query()->active()->count(),
            'total_teams' => Team::query()->count(),
            'completed_missions' => TeamProgress::query()
                ->where('status', TeamProgress::STATUS_COMPLETED)
                ->count(),
            'waiting_validation' => Submission::query()
                ->where('status', Submission::STATUS_WAITING)
                ->count(),
        ];

        $recentSubmissions = Submission::query()
            ->with(['team.gameSession', 'mission'])
            ->where('status', Submission::STATUS_WAITING)
            ->latest('submitted_at')
            ->limit(5)
            ->get();

        $activeSessions = GameSession::query()
            ->active()
            ->withCount('teams')
            ->latest()
            ->limit(5)
            ->get();

        return view('teacher.dashboard', compact('stats', 'recentSubmissions', 'activeSessions'));
    }

    // -----------------------------------------------------------------
    // SESI
    // -----------------------------------------------------------------

    /**
     * Daftar sesi.
     */
    public function sessions()
    {
        $sessions = GameSession::query()
            ->withCount('teams')
            ->latest()
            ->paginate(10);

        return view('teacher.sessions.index', compact('sessions'));
    }

    /**
     * Form buat sesi.
     */
    public function createSession()
    {
        $durationOptions = config('tikmission.duration_options');

        return view('teacher.sessions.create', compact('durationOptions'));
    }

    /**
     * Simpan sesi baru beserta kode rahasia tiap misi.
     */
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/', 'unique:game_sessions,code'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'leaderboard_enabled' => ['nullable', 'boolean'],
            'hints_enabled' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama sesi wajib diisi.',
            'code.required' => 'Kode sesi wajib diisi.',
            'code.regex' => 'Kode sesi hanya boleh berisi huruf, angka, dan tanda minus.',
            'code.unique' => 'Kode sesi sudah dipakai. Gunakan kode lain.',
            'duration_minutes.required' => 'Durasi wajib dipilih.',
        ]);

        $session = GameSession::query()->create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'duration_minutes' => $validated['duration_minutes'],
            'start_time' => now(),
            'status' => GameSession::STATUS_ACTIVE,
            'leaderboard_enabled' => $request->boolean('leaderboard_enabled'),
            'hints_enabled' => $request->boolean('hints_enabled', true),
            'is_demo' => false,
        ]);

        $this->missions->generateCodes($session);

        return redirect()
            ->route('teacher.sessions.show', $session)
            ->with('success', 'Sesi berhasil dibuat. Bagikan kode '.$session->code.' kepada siswa.');
    }

    /**
     * Detail sesi: kode rahasia, tim, progres.
     */
    public function showSession(GameSession $session)
    {
        $missionList = Mission::query()->active()->ordered()->get();
        $codes = $this->missions->codesForSession($session);

        $teams = $session->teams()
            ->withCount('members')
            ->with(['progress', 'submissions'])
            ->get()
            ->map(function (Team $team) use ($missionList) {
                $progress = $team->progress->keyBy('mission_id');
                $completed = $team->progress
                    ->where('status', TeamProgress::STATUS_COMPLETED)
                    ->count();

                return [
                    'team' => $team,
                    'completed' => $completed,
                    'total' => $missionList->count(),
                    'progress' => $progress,
                ];
            });

        return view('teacher.sessions.show', compact('session', 'missionList', 'codes', 'teams'));
    }

    /**
     * Form edit sesi.
     */
    public function editSession(GameSession $session)
    {
        $durationOptions = config('tikmission.duration_options');
        $missionList = Mission::query()->active()->ordered()->get();
        $codes = $this->missions->codesForSession($session);

        return view('teacher.sessions.edit', compact('session', 'durationOptions', 'missionList', 'codes'));
    }

    /**
     * Update sesi + kode rahasia tiap misi.
     */
    public function updateSession(Request $request, GameSession $session)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('game_sessions', 'code')->ignore($session->id)],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'leaderboard_enabled' => ['nullable', 'boolean'],
            'hints_enabled' => ['nullable', 'boolean'],
            'codes' => ['nullable', 'array'],
            'codes.*' => ['nullable', 'string', 'max:40'],
        ], [
            'name.required' => 'Nama sesi wajib diisi.',
            'code.unique' => 'Kode sesi sudah dipakai. Gunakan kode lain.',
        ]);

        $session->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'duration_minutes' => $validated['duration_minutes'],
            'leaderboard_enabled' => $request->boolean('leaderboard_enabled'),
            'hints_enabled' => $request->boolean('hints_enabled'),
        ]);

        // Simpan kode rahasia per misi.
        foreach ($request->input('codes', []) as $missionId => $code) {
            if (! $code) {
                continue;
            }

            $mission = Mission::query()->find($missionId);
            if ($mission) {
                $this->missions->setCode($session, $mission, $code);
            }
        }

        return redirect()
            ->route('teacher.sessions.show', $session)
            ->with('success', 'Sesi berhasil diperbarui.');
    }

    /**
     * Akhiri sesi.
     */
    public function endSession(GameSession $session)
    {
        $session->update([
            'status' => GameSession::STATUS_ENDED,
            'end_time' => now(),
        ]);

        return back()->with('success', 'Sesi "'.$session->name.'" telah diakhiri.');
    }

    /**
     * Aktifkan kembali sesi yang sudah diakhiri.
     */
    public function reopenSession(GameSession $session)
    {
        $session->update([
            'status' => GameSession::STATUS_ACTIVE,
            'end_time' => null,
        ]);

        return back()->with('success', 'Sesi diaktifkan kembali.');
    }

    /**
     * Hapus sesi beserta seluruh kelompok di dalamnya.
     */
    public function destroySession(GameSession $session)
    {
        DB::transaction(function () use ($session) {
            foreach ($session->teams as $team) {
                $this->teams->delete($team, $this->submissions);
            }
            $session->missionCodes()->delete();
            $session->delete();
        });

        return redirect()
            ->route('teacher.sessions.index')
            ->with('success', 'Sesi berhasil dihapus.');
    }
}
