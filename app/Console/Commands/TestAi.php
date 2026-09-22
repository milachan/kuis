<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Services\AiReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Periksa kesiapan penilaian AI: apakah API key terisi dan apakah
 * DeepSeek benar-benar menjawab. Jalankan: php artisan ai:test
 *
 * Perintah ini hanya memeriksa koneksi & format balasan; tidak menyimpan
 * apa pun ke database dan tidak mengubah data siswa.
 */
class TestAi extends Command
{
    protected $signature = 'ai:test {--jawaban= : Jawaban contoh untuk diuji}';

    protected $description = 'Uji koneksi penilaian AI DeepSeek (cek API key dan balasan)';

    public function handle(AiReviewService $ai): int
    {
        $this->newLine();
        $this->info('=== UJI PENILAIAN AI DEEPSEEK ===');
        $this->newLine();

        // 1. Konfigurasi dasar.
        $this->line('Konfigurasi yang terbaca Laravel:');
        $this->line('  AI_REVIEW_ENABLED       : '.var_export(config('ai.enabled'), true));
        $this->line('  AI_API_KEY              : '.$this->maskKey(config('ai.api_key')));
        $this->line('  AI_BASE_URL             : '.config('ai.base_url'));
        $this->line('  AI_MODEL                : '.config('ai.model'));
        $this->line('  AI_TIMEOUT (detik)      : '.config('ai.timeout'));
        $this->line('  AI_XP_WEIGHT_PERCENT    : '.config('ai.xp_weight_percent'));
        $this->newLine();

        if (blank(config('ai.api_key'))) {
            $this->error('GAGAL: AI_API_KEY masih kosong.');
            $this->line('Buka file .env, isi baris berikut, lalu jalankan lagi:');
            $this->line('    AI_API_KEY=sk-xxxxxxxxxxxxxxxxxxxx');
            $this->newLine();
            $this->line('Catatan: setelah mengubah .env, jalankan "php artisan config:clear".');

            return self::FAILURE;
        }

        if (! config('ai.enabled')) {
            $this->error('GAGAL: AI_REVIEW_ENABLED bernilai false. Ubah menjadi true di .env.');

            return self::FAILURE;
        }

        // 2. Siapkan konteks misi nyata bila ada.
        $mission = Mission::query()->active()->ordered()->first();

        if (! $mission) {
            $this->warn('Tidak ada misi aktif. Uji memakai konteks umum.');
        } else {
            $this->line('Memakai konteks misi: "'.$mission->title.'"');
        }

        $answer = $this->option('jawaban')
            ?: 'Fitur bold membuat teks menjadi lebih tebal supaya menonjol, biasanya dipakai untuk judul.';

        $this->newLine();
        $this->line('Jawaban contoh yang dikirim:');
        $this->line('  "'.$answer.'"');
        $this->newLine();
        $this->line('Menghubungi DeepSeek...');

        // 3. Panggil endpoint yang sama persis dengan yang dipakai aplikasi.
        $started = microtime(true);

        try {
            $response = Http::withToken((string) config('ai.api_key'))
                ->timeout((int) config('ai.timeout'))
                ->acceptJson()
                ->post(rtrim((string) config('ai.base_url'), '/').'/chat/completions', [
                    'model' => config('ai.model'),
                    'temperature' => (float) config('ai.temperature'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Kamu guru Informatika. Balas JSON: {"skor": <0-100>, "umpan_balik": "<singkat>"}'],
                        ['role' => 'user', 'content' => $answer],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (\Throwable $e) {
            $this->error('GAGAL: tidak dapat menghubungi DeepSeek.');
            $this->line('Pesan: '.$e->getMessage());
            $this->newLine();
            $this->complainCommonCauses();

            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $started, 2);

        if ($response->failed()) {
            $this->error('GAGAL: DeepSeek membalas status HTTP '.$response->status().' ('.$elapsed.' detik).');
            $this->line('Balasan: '.mb_substr($response->body(), 0, 400));
            $this->newLine();
            $this->complainCommonCauses($response->status());

            return self::FAILURE;
        }

        // 4. Tampilkan hasil.
        $this->info('BERHASIL terhubung ('.$elapsed.' detik, HTTP '.$response->status().').');

        $model = data_get($response->json(), 'model');
        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        $usage = data_get($response->json(), 'usage');

        $this->line('  Model dipakai    : '.($model ?: '-'));
        $this->line('  Balasan mentah   : '.mb_substr($content, 0, 300));

        if (is_array($usage)) {
            $this->line('  Token            : prompt '.($usage['prompt_tokens'] ?? '?')
                .' + jawaban '.($usage['completion_tokens'] ?? '?').' = '.($usage['total_tokens'] ?? '?'));
        }

        // 5. Uji parser yang dipakai aplikasi (tanpa menulis ke database).
        $parsed = $this->parseWithApp($content);

        $this->newLine();

        if ($parsed === null) {
            $this->warn('PERINGATAN: terhubung, tetapi balasan tidak dapat dibaca sebagai JSON.');
            $this->line('Aplikasi akan menandai kiriman sebagai "AI Gagal" untuk model ini.');
            $this->line('Coba ganti AI_MODEL (mis. deepseek-chat) atau periksa AI_BASE_URL.');

            return self::FAILURE;
        }

        $this->info('Parser aplikasi berhasil membaca skor: '.$parsed['skor'].'/100');
        if (! empty($parsed['umpan_balik'])) {
            $this->line('  Umpan balik      : '.mb_substr((string) $parsed['umpan_balik'], 0, 200));
        }

        $this->newLine();
        $this->info('SEMUA SIAP. Penilaian AI akan berjalan saat murid menekan kirim.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Jalankan parser milik AiReviewService lewat refleksi (protected).
     * Return null bila balasan tidak valid.
     */
    protected function parseWithApp(string $content): ?array
    {
        $service = app(AiReviewService::class);
        $reflection = new \ReflectionMethod($service, 'parseContent');
        $reflection->setAccessible(true);

        return $reflection->invoke($service, $content);
    }

    /**
     * Tampilkan sebagian key saja agar tidak bocor di layar saat mengajar.
     */
    protected function maskKey(?string $key): string
    {
        $key = (string) $key;

        if ($key === '') {
            return '(kosong — BELUM DIISI)';
        }

        if (strlen($key) <= 10) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 6).'...'.substr($key, -4).' (terisi)';
    }

    protected function complainCommonCauses(?int $status = null): void
    {
        $this->line('Kemungkinan penyebab:');
        $this->line('  - AI_API_KEY salah ketik atau sudah dihapus.');

        if ($status === 401) {
            $this->line('  - HTTP 401 = key ditolak. Buat key baru di platform.deepseek.com.');
        }

        if ($status === 402) {
            $this->line('  - HTTP 402 = saldo kredit DeepSeek habis. Isi ulang kredit.');
        }

        if ($status === 429) {
            $this->line('  - HTTP 429 = terlalu banyak permintaan. Coba lagi sebentar.');
        }

        if ($status === null) {
            $this->line('  - Tidak ada internet, atau firewall sekolah memblokir api.deepseek.com.');
        }

        $this->line('  - AI_BASE_URL salah (harus https://api.deepseek.com tanpa /chat/completions).');
        $this->newLine();
        $this->line('Setelah memperbaiki .env, selalu jalankan: php artisan config:clear');
    }
}
