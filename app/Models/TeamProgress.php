<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id', 'mission_id', 'status', 'xp', 'hints_used',
    'unlocked_at', 'completed_at',
])]
class TeamProgress extends Model
{
    public const STATUS_LOCKED = 'locked';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_VALIDATION = 'waiting_validation';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Label status berbahasa Indonesia untuk tampilan.
     */
    public const STATUS_LABELS = [
        self::STATUS_LOCKED => 'Terkunci',
        self::STATUS_AVAILABLE => 'Tersedia',
        self::STATUS_IN_PROGRESS => 'Sedang Dikerjakan',
        self::STATUS_WAITING_VALIDATION => 'Menunggu Validasi',
        self::STATUS_COMPLETED => 'Selesai',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'completed_at' => 'datetime',
            'xp' => 'integer',
            'hints_used' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Apakah misi ini bisa dibuka/dikerjakan siswa.
     */
    public function isPlayable(): bool
    {
        return in_array($this->status, [
            self::STATUS_AVAILABLE,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_VALIDATION,
        ], true);
    }

    /**
     * Apakah siswa masih boleh mengirim jawaban.
     */
    public function canSubmit(): bool
    {
        return in_array($this->status, [
            self::STATUS_AVAILABLE,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_VALIDATION,
        ], true);
    }
}
