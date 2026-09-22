<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Submission;
use App\Services\AiReviewService;
use App\Services\RoundService;
use Illuminate\Http\Request;

/**
 * Layar utama guru (proyektor/TV) dan kendali ronde permainan.
 *
 * Halaman "screen" ditampilkan lewat proyektor dan memuat sendiri (polling)
 * data skor terbaru, sehingga guru cukup menekan tombol Mulai/Akhiri Ronde.
 */
class RoundController extends Controller
{
    public function __construct(
        protected RoundService $rounds,
    ) {}

    /**
     * Layar proyektor: leaderboard besar, timer ronde, kode sesi.
     */
    public function screen(GameSession $session)
    {
        return view('teacher.screen', [
            'session' => $session,
            'totalRounds' => $this->rounds->totalRounds(),
        ]);
    }

    /**
     * Endpoint JSON yang dipanggil layar proyektor secara berkala.
     * Berisi status ronde, timer, dan papan skor terbaru.
     */
    public function live(GameSession $session)
    {
        $session->refresh();

        return response()->json($this->livePayload($session));
    }

    /**
     * Mulai sebuah ronde dari layar guru.
     */
    public function start(Request $request, GameSession $session)
    {
        // Batas atas dibuat longgar (bukan jumlah misi aktif saat itu) supaya
        // guru tidak terkunci ketika sebuah misi dinonaktifkan di tengah
        // permainan. Keberadaan misi divalidasi lewat missionForRound().
        $validated = $request->validate([
            'round' => ['required', 'integer', 'min:1', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [
            'round.required' => 'Nomor ronde tidak dikenali.',
            'round.min' => 'Nomor ronde minimal 1.',
        ]);

        $round = (int) $validated['round'];
        $mission = $this->rounds->missionForRound($round);

        if (! $mission) {
            return back()->with('error', 'Misi untuk ronde '.$round.' tidak ditemukan.');
        }

        $this->rounds->start($session, $round, $validated['duration_minutes'] ?? null);

        return back()->with('success', 'Ronde '.$round.' dimulai: "'.$mission->title.'". '
            .'Misi ini sekarang terbuka untuk semua kelompok. Semoga seru!');
    }

    /**
     * Akhiri ronde yang sedang berjalan.
     */
    public function end(GameSession $session)
    {
        $this->rounds->end($session);

        return back()->with('success', 'Ronde diakhiri. Papan skor sementara ditampilkan di layar.');
    }

    /**
     * Kembalikan sesi ke kondisi lobi (ronde nol).
     */
    public function reset(GameSession $session)
    {
        $this->rounds->reset($session);

        return back()->with('success', 'Sesi dikembalikan ke mode lobi. Kelompok baru sudah bisa bergabung.');
    }

    /**
     * Buka/kunci lobi secara manual.
     */
    public function toggleLobby(GameSession $session)
    {
        if ($session->lobby_locked) {
            $this->rounds->openLobby($session);

            return back()->with('success', 'Lobi dibuka. Kelompok baru bisa bergabung.');
        }

        $this->rounds->closeLobby($session);

        return back()->with('success', 'Lobi dikunci. Kelompok baru tidak bisa bergabung.');
    }

    /**
     * Susun muatan JSON untuk layar proyektor.
     *
     * @return array<string, mixed>
     */
    protected function livePayload(GameSession $session): array
    {
        $board = $this->rounds->leaderboard($session);

        $currentMission = $session->current_round > 0
            ? $this->rounds->missionForRound($session->current_round)
            : null;

        return [
            'server_time' => now()->toIso8601String(),
            'session' => [
                'id' => $session->id,
                'name' => $session->name,
                'code' => $session->code,
                'status' => $session->status,
                'is_ended' => $session->isEnded(),
                'lobby_locked' => $session->lobby_locked,
            ],
            'round' => [
                'current' => $session->current_round,
                'total' => $this->rounds->totalRounds(),
                'status' => $session->round_status,
                'is_running' => $session->isRoundRunning(),
                'is_time_up' => $session->isRoundTimeUp(),
                'seconds_remaining' => $session->roundSecondsRemaining(),
                'formatted_remaining' => $session->formattedRoundRemaining(),
                'duration_minutes' => $session->round_duration_minutes,
                'mission_title' => $currentMission?->title,
                'mission_objective' => $currentMission?->objective,
            ],
            'teams' => $board,
            'ai_active' => app(AiReviewService::class)->isConfigured(),
            'stats' => [
                'teams' => count($board),
                'total_xp' => array_sum(array_column($board, 'xp')),
                // Hitung KIRIMAN yang sudah dinilai AI, bukan baris hasil join,
                // agar satu kelompok dengan banyak misi tidak terhitung berulang.
                'scored_by_ai' => Submission::query()
                    ->whereIn('team_id', $session->teams()->select('id'))
                    ->where('ai_status', Submission::AI_SCORED)
                    ->count(),
            ],
        ];
    }
}
