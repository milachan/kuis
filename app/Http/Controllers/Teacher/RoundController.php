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
     * SATU aksi untuk membuka ronde berikutnya.
     *
     * Ini cara utama guru memindahkan permainan: ronde yang sedang berjalan
     * ditutup otomatis, lalu ronde berikutnya dibuka. Guru tidak perlu
     * menekan "akhiri ronde" lebih dulu, sehingga tidak bingung.
     */
    public function next(Request $request, GameSession $session)
    {
        $validated = $request->validate([
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
        ]);

        if ($session->isEnded()) {
            return back()->with('error', 'Sesi sudah berakhir. Aktifkan kembali sesi dulu.');
        }

        $berikutnya = $this->rounds->nextRoundNumber($session);

        if ($berikutnya === null) {
            return back()->with('error', 'Semua ronde sudah selesai dijalankan.');
        }

        $mission = $this->rounds->missionForRound($berikutnya);

        if (! $mission) {
            return back()->with('error', 'Misi untuk ronde '.$berikutnya.' tidak ditemukan.');
        }

        // Ronde lama ditutup, ronde baru dibuka, timer mulai.
        $this->rounds->start(
            $session,
            $berikutnya,
            $validated['duration_minutes'] ?? null
        );

        return back()->with('success', 'Ronde '.$berikutnya.' dibuka: "'.$mission->title.'". '
            .'Ronde sebelumnya sudah ditutup otomatis.');
    }

    /**
     * Buka BEBERAPA ronde sekaligus.
     *
     * Guru mencentang ronde mana saja yang harus terbuka (mis. 1, 3, dan 5).
     * Semua ronde itu langsung bisa dikerjakan oleh setiap kelompok, dan ronde
     * yang tidak dicentang ditutup otomatis. XP yang sudah terkumpul aman.
     */
    public function openRounds(Request $request, GameSession $session)
    {
        $validated = $request->validate([
            'rounds' => ['required', 'array', 'min:1'],
            'rounds.*' => ['integer', 'min:1', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [
            'rounds.required' => 'Pilih minimal satu ronde untuk dibuka.',
            'rounds.min' => 'Pilih minimal satu ronde untuk dibuka.',
            'rounds.*.integer' => 'Nomor ronde tidak dikenali.',
        ]);

        if ($session->isEnded()) {
            return back()->with('error', 'Sesi sudah berakhir. Aktifkan kembali sesi dulu.');
        }

        $rounds = collect($validated['rounds'])
            ->map(fn ($r) => (int) $r)
            ->unique()
            ->sort()
            ->values();

        // Bila ada satu nomor saja yang tidak punya misi, batalkan SELURUH
        // permintaan supaya guru tidak salah kira ronde lain sudah terbuka.
        $tidakAda = $rounds->reject(fn (int $r) => $this->rounds->missionForRound($r) !== null);

        if ($tidakAda->isNotEmpty()) {
            return back()->with('error',
                'Ronde '.$tidakAda->implode(', ').' tidak ditemukan. Pilihan ronde tidak diubah.');
        }

        $this->rounds->openRounds($session, $rounds->all(), $validated['duration_minutes'] ?? null);

        if ($rounds->count() === 1) {
            return back()->with('success', 'Ronde '.$rounds->first().' dibuka. Ronde lain ditutup otomatis.');
        }

        return back()->with('success', $rounds->count().' ronde dibuka sekaligus (ronde '
            .$rounds->implode(', ').'). Setiap kelompok bebas memilih mau mengerjakan yang mana.');
    }

    /**
     * Buka ronde tertentu yang dipilih guru dari daftar ronde.
     *
     * Berguna untuk MUNDUR ke ronde sebelumnya atau melompat ke ronde lain.
     * XP dan jawaban yang sudah terkumpul tetap aman.
     */
    public function openRound(Request $request, GameSession $session)
    {
        $validated = $request->validate([
            'round' => ['required', 'integer', 'min:1', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [
            'round.required' => 'Nomor ronde tidak dikenali.',
        ]);

        if ($session->isEnded()) {
            return back()->with('error', 'Sesi sudah berakhir. Aktifkan kembali sesi dulu.');
        }

        $round = (int) $validated['round'];
        $mission = $this->rounds->missionForRound($round);

        if (! $mission) {
            return back()->with('error', 'Misi untuk ronde '.$round.' tidak ditemukan.');
        }

        $this->rounds->openRound($session, $round, $validated['duration_minutes'] ?? null);

        return back()->with('success', 'Ronde '.$round.' dibuka: "'.$mission->title.'". '
            .'Ronde lain ditutup otomatis. XP yang sudah terkumpul tetap aman.');
    }

    /**
     * Tutup SEMUA ronde yang sedang terbuka.
     *
     * Berbeda dari end(): semua misi yang masih bisa dikerjakan ikut ditutup,
     * sehingga siswa berhenti mengirim jawaban. Ketikan siswa tetap tersimpan
     * di browser mereka, jadi membuka ronde lagi tidak menghilangkan pekerjaan.
     */
    public function closeRounds(GameSession $session)
    {
        $this->rounds->closeAllRounds($session);

        return back()->with('success', 'Semua ronde ditutup. Buka ronde lagi bila kelompok masih perlu melanjutkan.');
    }

    /**
     * Tambah jatah waktu sesi (default 15 menit).
     *
     * Dipakai ketika kelas masih mengerjakan sementara jam kelas hampir habis
     * atau sudah habis — tanpa ini semua kiriman ditolak tanpa tombol apapun
     * yang bisa ditekan guru.
     */
    public function extendTime(Request $request, GameSession $session)
    {
        $validated = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        if (! $session->hasTimer()) {
            return back()->with('error', 'Sesi ini memang tanpa batas waktu, jadi tidak ada yang perlu ditambah.');
        }

        $menit = (int) ($validated['minutes'] ?? 15);

        $this->rounds->extendTime($session, $menit);

        return back()->with('success', 'Waktu sesi ditambah '.$menit.' menit. '
            .'Kelompok yang sedang mengerjakan bisa lanjut mengirim jawaban.');
    }

    /**
     * Matikan batas waktu sesi (tanpa batas sampai guru mengakhiri sesi).
     */
    public function removeTimeLimit(GameSession $session)
    {
        $this->rounds->removeTimeLimit($session);

        return back()->with('success', 'Batas waktu sesi dimatikan. Semua kelompok bisa mengirim jawaban '
            .'sampai guru mengakhiri sesi atau menutup ronde.');
    }

    /**
     * Akhiri ronde yang sedang berjalan (tanpa membuka ronde baru).
     */
    public function end(GameSession $session)
    {
        $this->rounds->end($session);

        return back()->with('success', 'Ronde diakhiri. Papan skor tetap tampil di layar proyektor.');
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
                // Ronde yang sedang terbuka (bisa lebih dari satu) supaya layar
                // proyektor menampilkan daftar yang sama dengan halaman sesi.
                'open_rounds' => $this->rounds->openRoundInfo($session),
                // Timer ronde hanya bermakna bila tepat satu ronde dibuka.
                'timer_active' => ! $session->hasMultipleOpenRounds(),
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
