<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['game_session_id', 'name', 'token', 'xp', 'started_at', 'completed_at'])]
class Team extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'xp' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Token acak otomatis untuk identitas kelompok di session browser.
        static::creating(function (Team $team) {
            if (empty($team->token)) {
                $team->token = static::generateToken();
            }
        });
    }

    public static function generateToken(): string
    {
        return hash('sha256', Str::random(64).config('app.key').microtime(true));
    }

    // ---------------------------------------------------------------------
    // Relasi
    // ---------------------------------------------------------------------

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
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
    // Helper progres
    // ---------------------------------------------------------------------

    /**
     * Progres kelompok untuk satu misi.
     */
    public function progressFor(Mission $mission): ?TeamProgress
    {
        return $this->progress->firstWhere('mission_id', $mission->id);
    }

    /**
     * Jumlah misi yang sudah COMPLETED.
     */
    public function completedMissionsCount(): int
    {
        return $this->progress()->where('status', TeamProgress::STATUS_COMPLETED)->count();
    }

    /**
     * Jumlah misi aktif di sistem.
     */
    public function totalMissionsCount(): int
    {
        return Mission::query()->where('is_active', true)->count();
    }

    /**
     * Persentase progres 0-100.
     */
    public function progressPercent(): int
    {
        $total = $this->totalMissionsCount();

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->completedMissionsCount() / $total) * 100);
    }

    /**
     * Apakah semua misi sudah selesai.
     */
    public function hasFinishedAll(): bool
    {
        $total = $this->totalMissionsCount();

        return $total > 0 && $this->completedMissionsCount() >= $total;
    }

    /**
     * Total petunjuk yang dipakai seluruh misi.
     */
    public function totalHintsUsed(): int
    {
        return (int) $this->progress()->sum('hints_used');
    }

    /**
     * Hitung ulang XP kelompok dari data progres.
     */
    public function recalculateXp(): int
    {
        $this->xp = (int) $this->progress()->sum('xp');
        $this->save();

        return $this->xp;
    }
}
