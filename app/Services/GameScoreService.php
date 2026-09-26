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
 * dengan kunci jawaban, sehingga penilaiannya pasti.
 *
 * PENTING (keamanan): seluruh penilaian terjadi di SERVER. Browser hanya
 * mengirim SOAL MANA yang sedang ditampilkan dan PILIHAN MANA yang ditekan;
 * benar/salah/skor dihitung di sini memakai kunci jawaban misi. Dengan begitu
 * siswa tidak bisa memalsukan hasil permainan lewat DevTools atau curl.
 *
 * Skor game disimpan pada submission yang sama dengan jawaban uraian, sehingga
 * guru melihat satu baris hasil per kelompok per misi.
 */
class GameScoreService
{
    /**
     * Nilai satu jawaban pilihan.
     *
     * @return array{tepat: bool, kunci: ?int, benar: int, salah: int}
     */
    public function gradeAnswer(Mission $mission, ?string $question, int $choice): array
    {
        $soal = collect($mission->gameQuestionList())
            ->first(fn (array $q) => $q['pertanyaan'] === $question);

        // Soal tidak dikenali: hitung salah, jangan beri kunci.
        if ($soal === null) {
            return ['tepat' => false, 'kunci' => null, 'benar' => 0, 'salah' => 1];
        }

        $kunci = (int) $soal['jawaban'];
        $tepat = $choice === $kunci;

        return [
            'tepat' => $tepat,
            'kunci' => $kunci,
            'benar' => $tepat ? 1 : 0,
            'salah' => $tepat ? 0 : 1,
        ];
    }

    /**
     * Evaluasi utama hasil satu sesi permainan.
     *
     * Browser mengirim daftar jawaban yang benar-benar ditekan
     * (`{pertanyaan, pilihan}`). Server menilai ulang semuanya dengan kunci
     * misi, lalu menyimpan hasil & memberi XP. `benar`/`salah`/`skor` yang
     * dikirim klien DIAIBAIKAN sepenuhnya.
     *
     * @param  array<int, array{pertanyaan?: string, pilihan?: mixed}>  $jawaban
     * @return array{benar: int, salah: int, skor: int, total: int, xp: int, xp_gain: int, total_xp: int}
     */
    public function evaluate(TeamProgress $progress, Submission $submission, Mission $mission, array $jawaban): array
    {
        $soal = collect($mission->gameQuestionList())->keyBy('pertanyaan');

        $benar = 0;
        $salah = 0;
        $missed = [];

        foreach ($jawaban as $item) {
            $pertanyaan = (string) ($item['pertanyaan'] ?? '');
            $pilihan = $item['pilihan'] ?? null;

            // Soal harus berasal dari bank soal misi ini.
            $kunciSoal = $soal->get($pertanyaan);

            if ($kunciSoal === null) {
                continue;
            }

            $pilihanTeks = is_string($pilihan) ? $pilihan : null;

            $tepat = $pilihanTeks !== null
                && ($kunciSoal['pilihan'][(int) $kunciSoal['jawaban']] ?? null) === $pilihanTeks;

            if ($tepat) {
                $benar += 1;
            } else {
                $salah += 1;
                $missed[] = ['pertanyaan' => $pertanyaan, 'dijawab' => $pilihanTeks ?? '—'];
            }
        }

        $total = $benar + $salah;

        // Skor hanya berasal dari jawaban benar yang TERVERIFIKASI server.
        $skor = $benar * (int) config('tikmission.game_score_per_correct', 15);

        $xpSebelum = (int) $progress->xp;

        $submission->forceFill([
            'game_correct' => $benar,
            'game_wrong' => $salah,
            'game_score' => $skor,
            'game_played_at' => $submission->game_played_at ?: now(),
            'game_missed' => array_slice($missed, -20),
            'answer' => $submission->answer ?: $this->ringkasanTeks($benar, $salah, $mission),
            'submitted_at' => $submission->submitted_at ?: now(),
            'status' => Submission::STATUS_WAITING,
        ])->save();

        $xp = $this->award($progress, $submission, $benar, $salah);

        return [
            'benar' => $benar,
            'salah' => $salah,
            'skor' => $skor,
            'total' => $total,
            'xp' => $xp,
            'xp_gain' => max(0, $xp - $xpSebelum),
            'total_xp' => (int) $progress->team->xp,
        ];
    }

    /**
     * Beri XP berdasarkan hasil permainan game yang SUDAH diverifikasi server.
     *
     * Rumus: (akurasi jawaban x bobot game) + bonus skor permainan.
     * XP tidak pernah melebihi XP maksimum misi.
     */
    public function award(TeamProgress $progress, Submission $submission, int $benar, int $salah): int
    {
        $total = $benar + $salah;
        $akurasi = $total > 0 ? $benar / $total : 0.0;

        $maxAward = $this->maxAwardFor($progress);

        // Bobot game terhadap XP misi.
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
     * XP maksimum misi ini.
     *
     * Dihitung lewat ScoringService::maxXpFor() supaya rumus XP hanya ada di
     * SATU tempat dan tidak pernah berbeda dengan validasi guru.
     */
    protected function maxAwardFor(TeamProgress $progress): int
    {
        return app(ScoringService::class)->maxXpFor($progress);
    }

    /**
     * Ringkasan teks hasil game, agar guru melihat sesuatu di kolom jawaban.
     */
    public function ringkasanTeks(int $benar, int $salah, Mission $mission): string
    {
        $total = $benar + $salah;
        $akurasi = $total > 0 ? round(($benar / $total) * 100) : 0;

        $info = $mission->gameInfo();

        // Awalannya memakai konstanta model supaya teks ini dan pemeriksaan
        // "jawaban masih cuma ringkasan game" tidak pernah berbeda.
        return sprintf(
            Submission::GAME_SUMMARY_PREFIX.' %s] Jawaban benar %d, salah %d dari %d soal (akurasi %d%%).',
            $info['label'] ?? 'arcade',
            $benar,
            $salah,
            $total,
            $akurasi
        );
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
