<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order', 'title', 'slug', 'difficulty', 'story', 'objective', 'instructions',
    'code_prompt', 'hint_1', 'hint_2', 'reflection_question', 'questions', 'xp',
    'requires_pdf', 'is_active',
])]
class Mission extends Model
{
    protected function casts(): array
    {
        return [
            'instructions' => 'array',
            'questions' => 'array',
            'requires_pdf' => 'boolean',
            'is_active' => 'boolean',
            'xp' => 'integer',
            'order' => 'integer',
        ];
    }

    // ---------------------------------------------------------------------
    // Relasi
    // ---------------------------------------------------------------------

    public function codes(): HasMany
    {
        return $this->hasMany(MissionCode::class);
    }

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
     * Format tiap butir: ['pertanyaan' => string, 'petunjuk' => ?string]
     * Bila misi belum punya daftar pertanyaan, dipakai `reflection_question`
     * agar misi lama tetap berjalan.
     *
     * @return array<int, array{pertanyaan: string, petunjuk: ?string}>
     */
    public function questionList(): array
    {
        $questions = is_array($this->questions) ? $this->questions : [];

        $normalized = [];

        foreach ($questions as $q) {
            // Dukung bentuk singkat: daftar teks biasa.
            if (is_string($q)) {
                $normalized[] = ['pertanyaan' => $q, 'petunjuk' => null];

                continue;
            }

            if (is_array($q) && ! empty($q['pertanyaan'])) {
                $normalized[] = [
                    'pertanyaan' => (string) $q['pertanyaan'],
                    'petunjuk' => isset($q['petunjuk']) ? (string) $q['petunjuk'] : null,
                ];
            }
        }

        if ($normalized === [] && filled($this->reflection_question)) {
            $normalized[] = [
                'pertanyaan' => (string) $this->reflection_question,
                'petunjuk' => null,
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
}
