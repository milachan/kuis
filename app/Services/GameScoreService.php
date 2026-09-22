<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;

/**
 * Penilaian hasil GAME ARCADE.
 *
 * Berbeda dari jawaban uraian yang dinilai AI, game ini memakai soal pilihan
 * dengan kunci jawaban, sehingga penilaiannya pasti dan bisa langsung dipakai
 * sebagai mekanik permainan (benar = tenaga bertambah, salah = nyawa berkurang).
 *
 * Skor game disimpan pada submission yang sama dengan jawaban uraian, sehingga
 * guru melihat satu baris hasil per kelompok per misi.
 */
class GameScoreService
{
    /**
     * Beri XP berdasarkan hasil permainan game.
     *
     * Rumus: (akurasi jawaban x bobot game) + bonus skor permainan.
     * XP tidak pernah melebihi XP maksimum misi.
     */
    public function award(TeamProgress $progress, Submission $submission, int $benar, int $salah): int
    {
        $mission = $progress->mission;

        $total = $benar + $salah;
        $akurasi = $total > 0 ? $benar / $total : 0.0;

        $maxAward = $this->maxAwardFor($progress);

        // Bobot game terhadap XP misi (default 80%).
        $bobot = max(0, min(100, (int) config('tikmission.game_xp_weight_percent', 80)));

        $earned = (int) round($maxAward * ($bobot / 100) * $akurasi);

        // Bonus skor: maksimal 20% dari XP misi, dari skor permainan.
        $skor = (int) $submission->game_score;
        if ($skor > 0) {
            $bonusMaks = (int) round($maxAward * 0.2);
            $earned += min($bonusMaks, (int) round($skor / 5));
        }

        // Bonus usaha: bila anak bermain dengan sungguh-sungguh (menjawab banyak).
        if ($benar >= 5) {
            $earned += (int) config('tikmission.own_words_bonus_xp');
        }

        $earned = max(0, min($earned, $maxAward));

        // Hanya boleh naik, tidak pernah menurunkan XP yang sudah ada.
        if ($earned > $progress->xp) {
            $progress->update(['xp' => $earned]);
            $progress->team->recalculateXp();
        }

        return $progress->xp;
    }

    /**
     * XP maksimum misi ini (dasar + bonus tanpa petunjuk - penalti petunjuk).
     */
    protected function maxAwardFor(TeamProgress $progress): int
    {
        $mission = $progress->mission;
        $base = $mission->xp ?: (int) config('tikmission.default_mission_xp');

        $max = $base;

        if ($progress->hints_used === 0) {
            $max += (int) config('tikmission.no_hint_bonus_xp');
        }

        $max -= $progress->hints_used * (int) config('tikmission.hint_penalty_xp');

        return max(0, $max);
    }

    /**
     * Simpan/ambil baris hasil untuk sebuah misi (dipakai bersama jawaban uraian).
     */
    public function submissionFor(Team $team, Mission $mission): Submission
    {
        return Submission::query()->firstOrCreate(
            ['team_id' => $team->id, 'mission_id' => $mission->id],
            [
                'status' => Submission::STATUS_WAITING,
                'submitted_at' => now(),
            ]
        );
    }
}
