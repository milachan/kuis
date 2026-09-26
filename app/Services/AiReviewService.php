<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\Submission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penilaian otomatis jawaban refleksi siswa memakai model AI (DeepSeek).
 *
 * Catatan penting:
 * - AI hanya membaca TEKS (jawaban refleksi + konteks misi). Bukti screenshot
 *   tetap diverifikasi guru, karena model teks tidak bisa melihat gambar.
 * - Bila API key belum diisi atau jaringan gagal, penilaian AI dilewati dan
 *   aplikasi tetap berjalan normal (kiriman menunggu validasi guru).
 */
class AiReviewService
{
    /**
     * Apakah fitur AI siap dipakai (diaktifkan + API key tersedia).
     */
    public function isConfigured(): bool
    {
        return (bool) config('ai.enabled') && ! empty(config('ai.api_key'));
    }

    /**
     * Nilai jawaban refleksi sebuah kiriman.
     * Mengembalikan true bila skor AI berhasil disimpan.
     */
    public function review(Submission $submission): bool
    {
        if (! $this->isConfigured()) {
            $this->markSkipped($submission, 'Fitur AI tidak aktif atau API key belum diisi.');

            return false;
        }

        $answer = trim((string) $submission->answer);

        // Tanpa jawaban teks, tidak ada yang bisa dinilai AI.
        if ($answer === '') {
            $this->markSkipped($submission, 'Siswa tidak mengisi jawaban refleksi.');

            return false;
        }

        $mission = $submission->mission;

        try {
            $payload = [
                'model' => config('ai.model'),
                'temperature' => (float) config('ai.temperature'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->userPrompt($mission, $answer),
                    ],
                ],
                // Minta jawaban JSON murni agar mudah diurai.
                'response_format' => ['type' => 'json_object'],
            ];

            $response = Http::withToken((string) config('ai.api_key'))
                ->timeout((int) config('ai.timeout'))
                ->acceptJson()
                ->post(rtrim((string) config('ai.base_url'), '/').'/chat/completions', $payload);

            if ($response->failed()) {
                $this->markError($submission, 'AI membalas status '.$response->status().'.');

                return false;
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $parsed = $this->parseContent($content);

            if ($parsed === null) {
                $this->markError($submission, 'Jawaban AI tidak dapat dibaca.');

                return false;
            }

            $this->applyScore($submission, $parsed, (string) data_get($response->json(), 'model'));

            return true;
        } catch (Throwable $e) {
            // Jaringan mati / timeout: jangan sampai menghambat siswa.
            Log::warning('Penilaian AI gagal: '.$e->getMessage(), [
                'submission_id' => $submission->id,
            ]);

            $this->markError($submission, 'Tidak dapat menghubungi layanan AI.');

            return false;
        }
    }

    /**
     * Ubah hasil AI menjadi skor simpan + XP misi.
     *
     * @param  array{skor: int, umpan_balik: ?string, kriteria: array, keaslian: ?int, catatan_keaslian: ?string}  $parsed
     */
    protected function applyScore(Submission $submission, array $parsed, ?string $model): void
    {
        $score = max(0, min(100, (int) $parsed['skor']));

        // Skor keaslian dibatasi 1-5 sesuai kesepakatan prompt.
        $authenticity = $parsed['keaslian'] ?? null;

        if ($authenticity !== null) {
            $authenticity = max(1, min(5, (int) $authenticity));
        }

        $submission->forceFill([
            'ai_status' => Submission::AI_SCORED,
            'ai_score' => $score,
            'ai_feedback' => $parsed['umpan_balik'],
            'ai_details' => ['kriteria' => $parsed['kriteria']],
            'ai_model' => $model,
            'ai_reviewed_at' => now(),
            'authenticity_score' => $authenticity,
            'authenticity_note' => $parsed['catatan_keaslian'] ?? null,
            'own_words' => $parsed['bahasa_sendiri'] ?? null,
        ])->save();
    }

    protected function markSkipped(Submission $submission, string $reason): void
    {
        $submission->forceFill([
            'ai_status' => Submission::AI_SKIPPED,
            'ai_feedback' => $reason,
            'ai_reviewed_at' => now(),
        ])->save();
    }

    protected function markError(Submission $submission, string $reason): void
    {
        $submission->forceFill([
            'ai_status' => Submission::AI_ERROR,
            'ai_feedback' => $reason,
            'ai_reviewed_at' => now(),
        ])->save();
    }

    /**
     * Instruksi tetap untuk model: penilai yang adil dan konsisten.
     */
    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
        Kamu adalah guru Informatika SMP di Indonesia yang menilai jawaban siswa kelas 8
        pada Bab Sistem Komputer (kelas VIII MTs): komponen sistem komputer (perangkat
        keras, perangkat lunak, pengguna), perangkat input & output, prosesor, media
        penyimpanan & komputasi awan, sistem operasi, dasar pemrograman, dan bilangan
        heksadesimal.

        Tugasmu: menilai JAWABAN URAIAN PENDEK siswa terhadap beberapa pertanyaan sebuah misi,
        sekaligus memberi indikator keaslian jawaban.

        ATURAN PENILAIAN:
        1. Nilai HANYA berdasarkan teks jawaban siswa dibandingkan tujuan misi dan daftar pertanyaan.
        2. Setiap pertanyaan dinilai 0-100, lalu beri skor keseluruhan sebagai rata-rata berbobot.
        3. Jawaban siswa memang DIMINTA SINGKAT (1-2 kalimat atau beberapa kata kunci).
           JANGAN mengurangi nilai hanya karena jawaban pendek, selama isinya tepat.
           Yang salah adalah jawaban pendek yang TIDAK tepat, atau menjawab hal lain.
        4. Pedoman skor per pertanyaan:
           - 0-39: tidak menjawab / tidak relevan / hanya mengulang pertanyaan.
           - 40-59: ada usaha, tetapi konsep masih salah atau sangat dangkal.
           - 60-79: jawaban benar dan jelas, istilah teknis sebagian tepat.
           - 80-100: jawaban tepat dan singkat, memakai istilah Informatika yang benar.
        5. Bahasa siswa boleh santai dan penuh salah tulis; nilai MAKNA-nya, bukan ejaannya.
        6. Bila siswa tidak menjawab satu pertanyaan, beri nilai 0 dan sebutkan di umpan balik.
        7. Umpan balik ditulis dalam bahasa Indonesia, ramah, 2-3 kalimat: sebutkan apa yang sudah
           benar, bagian mana yang masih lemah, dan satu saran perbaikan konkret. Jangan merendahkan siswa.

        INDIKATOR KEASLIAN (penting, sebagai SINYAL bukan tuduhan):
        Beri "keaslian" bernilai 1-5 tentang seberapa besar jawaban ini tampak ditulis sendiri
        oleh siswa SMP, bukan hasil salin-tempel dari AI atau sumber lain:
        - 5 = sangat khas tulisan murid sendiri: ada salah ketik/singkatan wajar, gaya bahasa
              sederhana, menyebut pengalaman atau hal konkret yang mereka alami.
        - 4 = cenderung asli: sederhana dan wajar, hanya sedikit terasa rapi.
        - 3 = ragu/ambigu: campuran, tidak bisa dipastikan.
        - 2 = mirip salinan: bahasa sangat rapi dan seragam, struktur kaku, tidak ada salah ketik,
              tidak ada detail pengalaman, memakai istilah di luar level SMP.
        - 1 = sangat mirip salinan AI: sangat rapi, panjang dan seragam, berpola "Berikut adalah...",
              "Secara keseluruhan...", atau langsung menjawab seperti ensiklopedia.
        Tulis alasan singkat (maksimal 2 kalimat) pada "catatan_keaslian", dalam bahasa Indonesia
        yang NETRAL dan TIDAK menuduh. Contoh: "Jawaban sangat rapi tanpa salah ketik dan tanpa
        detail pengalaman pribadi, sebaiknya ditanya lisan." Jangan menulis kata "curang"/"mencontek".
        Ingat: tulisan rapi BUKAN bukti menyalin. Bila ragu, pilih 3.

        BAHASA SENDIRI (untuk bonus usaha):
        Isi "bahasa_sendiri" dengan true bila siswa jelas berusaha menjawab dengan
        PIKIRAN DAN KATA-KATANYA SENDIRI, walau isi jawabannya kurang tepat atau salah.
        Isi false bila jawaban hasil salinan, atau siswa hanya menulis ulang pertanyaan,
        atau mengosongkan jawaban.
        PENTING: "bahasa_sendiri" bernilai true MESKIPUN jawabannya salah, selama siswa
        benar-benar mencoba menjelaskan dengan bahasanya sendiri. Ini untuk menghargai usaha.

        Balas HANYA dengan JSON valid tanpa penjelasan tambahan, memakai struktur:
        {"skor": <angka 0-100 keseluruhan>, "umpan_balik": "<2-3 kalimat>", "kriteria": [{"kriteria": "<nama>", "nilai": <0-100>, "catatan": "<singkat>"}], "keaslian": <1-5>, "catatan_keaslian": "<maks 2 kalimat>", "bahasa_sendiri": <true|false>}
        Isi "kriteria" dengan satu butir per pertanyaan (pakai nomor pertanyaan pada nama kriteria),
        lalu tambahkan satu butir penutup bernama "Keseluruhan".
        PROMPT;
    }

    /**
     * Data misi + jawaban siswa yang dikirim ke AI.
     */
    protected function userPrompt(?Mission $mission, string $answer): string
    {
        $maxChars = (int) config('ai.max_answer_chars');
        if ($maxChars > 0 && mb_strlen($answer) > $maxChars) {
            $answer = mb_substr($answer, 0, $maxChars).' ...(dipotong)';
        }

        $title = $mission?->title ?? '(misi tidak diketahui)';
        $objective = $mission?->objective ?? '-';

        // Materi yang diuji, agar AI tahu konteks bab-nya.
        $story = $mission?->story ?? '-';

        // Daftar pertanyaan bernomor supaya AI bisa menilai satu per satu.
        $questions = collect($mission?->questionList() ?? [])
            ->map(function (array $q, int $i) {
                $line = ($i + 1).'. '.$q['pertanyaan'];

                if (! empty($q['petunjuk'])) {
                    $line .= "\n   (petunjuk: ".$q['petunjuk'].')';
                }

                return $line;
            })
            ->implode("\n");

        if ($questions === '') {
            $questions = '(tidak ada daftar pertanyaan)';
        }

        // Materi pendukung dari langkah praktik, bila ada.
        $instructions = collect($mission?->instructions ?? [])
            ->map(fn ($step, $i) => ($i + 1).'. '.(string) $step)
            ->implode("\n");

        $instructionsBlock = $instructions !== ''
            ? "MATERI / LANGKAH YANG DIPELAJARI:\n{$instructions}\n"
            : '';

        return <<<TEXT
        JUDUL MISI: {$title}

        KONTEKS MATERI:
        {$story}

        TUJUAN PEMBELAJARAN:
        {$objective}

        {$instructionsBlock}
        PERTANYAAN YANG HARUS DIJAWAB SISWA:
        {$questions}

        CATATAN: soal-soal ini memang dirancang agar siswa menjawab SINGKAT (1-2 kalimat atau
        beberapa kata kunci). Jangan menurunkan nilai hanya karena jawabannya pendek.

        JAWABAN SISWA (dijawab berurutan sesuai nomor pertanyaan):
        """
        {$answer}
        """

        Nilailah setiap pertanyaan sesuai aturan, berikan skor keseluruhan, lalu beri indikator
        keaslian (1-5) beserta alasannya. Balas dalam JSON.
        TEXT;
    }

    /**
     * Urai balasan AI menjadi array bersih. Null bila tidak valid.
     *
     * @return array{skor: int, umpan_balik: ?string, kriteria: array<int, array<string, mixed>>, keaslian: ?int, catatan_keaslian: ?string}|null
     */
    protected function parseContent(string $content): ?array
    {
        $content = trim($content);

        // Bersihkan pagar markdown ```json ... ``` bila model menambahkannya.
        // preg_replace dapat mengembalikan null bila terjadi galat PCRE, jadi
        // hasilnya diperiksa agar balasan AI yang valid tidak ikut terbuang.
        if (str_starts_with($content, '```')) {
            $stripped = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $content);

            if (is_string($stripped)) {
                $content = trim($stripped);
            }
        }

        $data = json_decode($content, true);

        // Cadangan: ambil objek JSON pertama yang muncul dalam teks.
        if (! is_array($data)) {
            if (preg_match('/\{.*\}/s', $content, $m) === 1) {
                $data = json_decode($m[0], true);
            }
        }

        if (! is_array($data) || ! isset($data['skor'])) {
            return null;
        }

        return [
            'skor' => (int) $data['skor'],
            'umpan_balik' => isset($data['umpan_balik'])
                ? (string) $data['umpan_balik']
                : (isset($data['feedback']) ? (string) $data['feedback'] : null),
            'kriteria' => is_array($data['kriteria'] ?? null) ? $data['kriteria'] : [],
            // Indikator keaslian (opsional; model lama mungkin tidak mengirimnya).
            'keaslian' => isset($data['keaslian']) && is_numeric($data['keaslian'])
                ? (int) $data['keaslian']
                : null,
            'catatan_keaslian' => isset($data['catatan_keaslian'])
                ? (string) $data['catatan_keaslian']
                : null,
            // Apakah siswa menjawab dengan bahasanya sendiri (untuk bonus usaha).
            'bahasa_sendiri' => isset($data['bahasa_sendiri'])
                ? filter_var($data['bahasa_sendiri'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : null,
        ];
    }
}
