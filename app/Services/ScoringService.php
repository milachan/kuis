<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Submission;
use App\Models\TeamProgress;

/**
 * Perhitungan XP: XP misi, bonus, dan pengurangan karena petunjuk.
 * Skor hanya elemen game — wajib sederhana dan mudah dijelaskan.
 */
class ScoringService
{
    /**
     * Hitung dan simpan XP untuk sebuah progres misi.
     *
     * Rumus:
     *   XP dasar misi
     *   + bonus tanpa petunjuk (jika hints_used = 0)
     *   + bonus tepat waktu (jika sesi memakai timer dan belum habis)
     *   - penalti petunjuk (hints_used * hint_penalty_xp)
     *
     * XP tidak pernah negatif.
     */
    public function awardForCompletion(TeamProgress $progress, GameSession $session): int
    {
        $base = $progress->mission->xp ?: (int) config('tikmission.default_mission_xp');
        $xp = $base;

        // Bonus tanpa bantuan/petunjuk.
        if ($progress->hints_used === 0) {
            $xp += (int) config('tikmission.no_hint_bonus_xp');
        }

        // Bonus tepat waktu — hanya bila sesi memakai timer dan masih ada sisa waktu.
        if ($session->hasTimer() && ! $session->isTimeUp()) {
            $xp += (int) config('tikmission.on_time_bonus_xp');
        }

        // Penalti petunjuk.
        $xp -= $progress->hints_used * (int) config('tikmission.hint_penalty_xp');

        $xp = max(0, $xp);

        $progress->update([
            'xp' => $xp,
            'status' => TeamProgress::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        // Sinkronkan total XP kelompok.
        $progress->team->recalculateXp();

        return $xp;
    }

    /**
     * XP sementara dari hasil penilaian AI atas jawaban refleksi.
     *
     * Memakai rumus yang sama dengan awardForCompletion() (XP dasar + bonus
     * tanpa petunjuk + bonus tepat waktu - penalti petunjuk), lalu dikalikan
     * skor AI dan bobot config. Dengan begitu angka di layar proyektor tidak
     * "melompat turun" saat guru menyatakan lulus.
     *
     * XP tidak pernah melebihi XP final misi dan tidak pernah menurunkan XP
     * yang sudah tercatat.
     */
    public function awardFromAi(TeamProgress $progress, Submission $submission): int
    {
        if (! $submission->hasAiScore()) {
            return 0;
        }

        $mission = $progress->mission;
        $base = $mission->xp ?: (int) config('tikmission.default_mission_xp');
        $score = max(0, min(100, (int) $submission->ai_score));

        // Nilai maksimum misi ini memakai rumus yang sama dengan validasi guru.
        $maxAward = $base;
        if ($progress->hints_used === 0) {
            $maxAward += (int) config('tikmission.no_hint_bonus_xp');
        }
        $maxAward -= $progress->hints_used * (int) config('tikmission.hint_penalty_xp');
        $maxAward = max(0, $maxAward);

        $weight = max(0, min(100, (int) config('ai.xp_weight_percent')));
        $earned = (int) round($maxAward * ($weight / 100) * ($score / 100));

        // BONUS USAHA: bila siswa jelas menjawab dengan bahasanya sendiri,
        // beri bonus XP walaupun isi jawabannya kurang tepat. Tujuannya
        // menghargai keberanian berpikir dan menulis sendiri.
        $bonus = 0;
        if ($submission->own_words === true) {
            $bonus = (int) config('tikmission.own_words_bonus_xp');
        }

        $earned += $bonus;

        // Bonus usaha tidak boleh melebihi XP maksimum misi ini.
        $earned = min($earned, $maxAward);

        // Catat berapa bonus yang benar-benar diberikan (untuk tampilan guru).
        if ((int) $submission->own_words_bonus !== $bonus) {
            $submission->forceFill(['own_words_bonus' => $bonus])->save();
        }

        // Hanya boleh naik, tidak pernah menurunkan XP yang sudah ada.
        if ($earned > $progress->xp) {
            $progress->update(['xp' => $earned]);
            $progress->team->recalculateXp();
        }

        return $progress->xp;
    }

    /**
     * XP yang akan diterima jika misi ini lulus sekarang (untuk preview).
     */
    public function previewFor(TeamProgress $progress, GameSession $session): int
    {
        $base = $progress->mission->xp ?: (int) config('tikmission.default_mission_xp');
        $xp = $base;

        if ($progress->hints_used === 0) {
            $xp += (int) config('tikmission.no_hint_bonus_xp');
        }

        if ($session->hasTimer() && ! $session->isTimeUp()) {
            $xp += (int) config('tikmission.on_time_bonus_xp');
        }

        $xp -= $progress->hints_used * (int) config('tikmission.hint_penalty_xp');

        return max(0, $xp);
    }

    /**
     * Kurangi XP karena membuka petunjuk.
     * Karena XP baru diberikan saat validasi, pengurangan cukup dicatat
     * lewat `hints_used`, dan langsung memotong XP yang sudah ada (jika misi
     * sudah selesai, hal ini tidak terjadi karena petunjuk ditutup).
     */
    public function registerHintUse(TeamProgress $progress): void
    {
        $progress->increment('hints_used');

        // Jika misi sudah pernah diberi XP (kasus guru membuka kembali),
        // potong langsung agar konsisten.
        if ($progress->xp > 0) {
            $penalty = (int) config('tikmission.hint_penalty_xp');
            $progress->update(['xp' => max(0, $progress->xp - $penalty)]);
            $progress->team->recalculateXp();
        }
    }
}
