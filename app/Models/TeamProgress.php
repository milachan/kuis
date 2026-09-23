<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'team_id', 'mission_id', 'status', 'xp', 'hints_used',
    'unlocked_at', 'work_started_at', 'late_entry', 'completed_at',
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
            'work_started_at' => 'datetime',
            'late_entry' => 'boolean',
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

    /**
     * Batas waktu mengerjakan milik siswa ini (per kelompok), dihitung dari
     * saat dia membuka halaman ronde. Null bila tidak dibatasi.
     */
    public function workDeadline(?int $durationMinutes): ?Carbon
    {
        if ($this->work_started_at === null || ! $durationMinutes || $durationMinutes <= 0) {
            return null;
        }

        return $this->work_started_at->copy()->addMinutes($durationMinutes);
    }

    /**
     * Sisa waktu mengerjakan siswa dalam detik. Null bila tidak dibatasi.
     */
    public function workSecondsRemaining(?int $durationMinutes): ?int
    {
        $deadline = $this->workDeadline($durationMinutes);

        if (! $deadline) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($deadline, false));
    }

    /**
     * Apakah waktu mengerjakan siswa sudah habis.
     */
    public function isWorkTimeUp(?int $durationMinutes): bool
    {
        $remaining = $this->workSecondsRemaining($durationMinutes);

        return $remaining !== null && $remaining === 0;
    }

    /**
     * Apakah jatah waktu kelompok ini baru mulai berjalan setelah batas waktu
     * kelas lewat (mis. murid yang baru masuk karena terlambat)?
     *
     * Kalau ya, jam kelas yang sudah habis TIDAK boleh memblokir mereka:
     * mereka memakai jatah waktu ronde miliknya sendiri.
     */
    public function isFreshWorkWindow(?Carbon $classDeadline): bool
    {
        if ($classDeadline === null) {
            return true;
        }

        return $this->work_started_at === null
            || $this->work_started_at->greaterThanOrEqualTo($classDeadline);
    }
}
