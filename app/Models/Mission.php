<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order', 'title', 'slug', 'game_type', 'difficulty', 'story', 'objective', 'instructions',
    'hint_1', 'hint_2', 'reflection_question', 'questions', 'game_questions', 'xp',
    'requires_pdf', 'is_active',
])]
class Mission extends Model
{
    protected function casts(): array
    {
        return [
            'instructions' => 'array',
            'questions' => 'array',
            'game_questions' => 'array',
            'requires_pdf' => 'boolean',
            'is_active' => 'boolean',
            'xp' => 'integer',
            'order' => 'integer',
        ];
    }

    // ---------------------------------------------------------------------
    // Relasi
    // ---------------------------------------------------------------------

    public function progress(): HasMany
    {
        return $this->hasMany(TeamProgress::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    // ---------------------------------------------------------------------
    // Scope
    // ---------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------

    /**
     * Misi aktif berikutnya berdasarkan urutan.
     */
    public function nextMission(): ?self
    {
        return static::query()
            ->active()
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Apakah misi ini misi terakhir.
     */
    public function isFinal(): bool
    {
        return $this->nextMission() === null;
    }

    /**
     * Nomor misi terformat, mis. "01".
     */
    public function numberLabel(): string
    {
        return str_pad((string) $this->order, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Daftar pertanyaan uraian misi ini.
     *
     * Format tiap butir: ['pertanyaan' => string, 'petunjuk' => ?string, 'jenis' => ?string]
     * Bila misi belum punya daftar pertanyaan, dipakai `reflection_question`
     * agar misi lama tetap berjalan.
     *
     * @return array<int, array{pertanyaan: string, petunjuk: ?string, jenis: ?string}>
     */
    public function questionList(): array
    {
        $questions = is_array($this->questions) ? $this->questions : [];

        $normalized = [];

        foreach ($questions as $q) {
            // Dukung bentuk singkat: daftar teks biasa.
            if (is_string($q)) {
                $normalized[] = ['pertanyaan' => $q, 'petunjuk' => null, 'jenis' => null];

                continue;
            }

            if (is_array($q) && ! empty($q['pertanyaan'])) {
                $normalized[] = [
                    'pertanyaan' => (string) $q['pertanyaan'],
                    'petunjuk' => isset($q['petunjuk']) ? (string) $q['petunjuk'] : null,
                    'jenis' => isset($q['jenis']) ? (string) $q['jenis'] : null,
                ];
            }
        }

        if ($normalized === [] && filled($this->reflection_question)) {
            $normalized[] = [
                'pertanyaan' => (string) $this->reflection_question,
                'petunjuk' => null,
                'jenis' => null,
            ];
        }

        return $normalized;
    }

    /**
     * Jumlah pertanyaan uraian misi ini.
     */
    public function questionCount(): int
    {
        return count($this->questionList());
    }

    // ---------------------------------------------------------------------
    // Game arcade
    // ---------------------------------------------------------------------

    /**
     * Daftar game arcade yang didukung beserta label ramah anak.
     *
     * @var array<string, array{label: string, icon: string, how: string}>
     */
    public const GAMES = [
        'snake' => [
            'label' => 'Ular Pintar',
            'icon' => '🐍',
            'how' => 'Jawab benar untuk memanjangkan ular. Salah = ular kehilangan nyawa.',
        ],
        'breakout' => [
            'label' => 'Pecahkan Target',
            'icon' => '🧱',
            'how' => 'Jawab benar untuk memecahkan bata. Salah = bola hilang satu.',
        ],
        'flappy' => [
            'label' => 'Terbang Tinggi',
            'icon' => '🕊️',
            'how' => 'Jawab benar untuk terbang melewati rintangan. Salah = jatuh.',
        ],
    ];

    /**
     * Apakah misi ini memakai game arcade.
     */
    public function hasGame(): bool
    {
        return filled($this->game_type) && array_key_exists($this->game_type, self::GAMES);
    }

    /**
     * Info game misi ini (label, ikon, cara main).
     *
     * @return array{label: string, icon: string, how: string}|null
     */
    public function gameInfo(): ?array
    {
        return $this->game_type ? (self::GAMES[$this->game_type] ?? null) : null;
    }

    /**
     * Bank soal cepat untuk mekanik game.
     *
     * Format tiap butir: ['pertanyaan' => string, 'pilihan' => array, 'jawaban' => int]
     *
     * @return array<int, array{pertanyaan: string, pilihan: array<int, string>, jawaban: int}>
     */
    public function gameQuestionList(): array
    {
        $items = is_array($this->game_questions) ? $this->game_questions : [];

        $bersih = [];

        foreach ($items as $q) {
            $pilihan = is_array($q['pilihan'] ?? null) ? array_values($q['pilihan']) : [];

            if (empty($q['pertanyaan']) || count($pilihan) < 2) {
                continue;
            }

            $jawaban = (int) ($q['jawaban'] ?? 0);

            // Jaga agar indeks jawaban selalu valid.
            if ($jawaban < 0 || $jawaban >= count($pilihan)) {
                $jawaban = 0;
            }

            $bersih[] = [
                'pertanyaan' => (string) $q['pertanyaan'],
                'pilihan' => array_map('strval', $pilihan),
                'jawaban' => $jawaban,
            ];
        }

        return $bersih;
    }

    /**
     * Jumlah soal game misi ini.
     */
    public function gameQuestionCount(): int
    {
        return count($this->gameQuestionList());
    }
}
