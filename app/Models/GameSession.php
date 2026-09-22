<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'code', 'name', 'duration_minutes', 'start_time', 'end_time',
    'status', 'leaderboard_enabled', 'hints_enabled', 'is_demo',
    'current_round', 'round_status', 'round_started_at',
    'round_duration_minutes', 'lobby_locked',
])]
class GameSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    // Status ronde yang dikendalikan guru dari layar proyektor.
    public const ROUND_IDLE = 'idle';

    public const ROUND_RUNNING = 'running';

    public const ROUND_ENDED = 'ended';

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'leaderboard_enabled' => 'boolean',
            'hints_enabled' => 'boolean',
            'is_demo' => 'boolean',
            'duration_minutes' => 'integer',
            'current_round' => 'integer',
            'round_started_at' => 'datetime',
            'round_duration_minutes' => 'integer',
            'lobby_locked' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Relasi
    // ---------------------------------------------------------------------

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function missionCodes(): HasMany
    {
        return $this->hasMany(MissionCode::class);
    }

    // ---------------------------------------------------------------------
    // Scope
    // ---------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ---------------------------------------------------------------------
    // Helper status sesi
    // ---------------------------------------------------------------------

    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    public function hasTimer(): bool
    {
        return $this->duration_minutes > 0;
    }

    /**
     * Waktu berakhir efektif. Null jika tidak memakai timer.
     */
    public function deadline(): ?Carbon
    {
        if (! $this->hasTimer() || ! $this->start_time) {
            return null;
        }

        return $this->start_time->copy()->addMinutes($this->duration_minutes);
    }

    /**
     * Sisa waktu dalam detik. Null jika tanpa timer.
     */
    public function secondsRemaining(): ?int
    {
        $deadline = $this->deadline();

        if (! $deadline) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($deadline, false));
    }

    /**
     * Akhir ronde yang sedang berjalan.
     */
    public function roundDeadline(): ?Carbon
    {
        if ($this->round_status !== self::ROUND_RUNNING || ! $this->round_started_at) {
            return null;
        }

        // Timer ronde 0 detik = tanpa batas waktu.
        if ($this->round_duration_minutes <= 0) {
            return null;
        }

        return $this->round_started_at->copy()->addMinutes($this->round_duration_minutes);
    }

    /**
     * Sisa waktu ronde dalam detik. Null bila tanpa timer / ronde tidak jalan.
     */
    public function roundSecondsRemaining(): ?int
    {
        $deadline = $this->roundDeadline();

        if (! $deadline) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($deadline, false));
    }

    /**
     * Apakah waktu ronde sudah habis.
     */
    public function isRoundTimeUp(): bool
    {
        $remaining = $this->roundSecondsRemaining();

        return $remaining !== null && $remaining === 0;
    }

    /**
     * Apakah ronde sedang berjalan (dan waktunya belum habis).
     */
    public function isRoundRunning(): bool
    {
        return $this->round_status === self::ROUND_RUNNING && ! $this->isRoundTimeUp();
    }

    /**
     * Sisa waktu ronde terformat MM:SS.
     */
    public function formattedRoundRemaining(): ?string
    {
        $seconds = $this->roundSecondsRemaining();

        if ($seconds === null) {
            return null;
        }

        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Apakah waktu sesi sudah habis.
     */
    public function isTimeUp(): bool
    {
        if (! $this->hasTimer()) {
            return false;
        }

        return $this->secondsRemaining() === 0;
    }

    /**
     * Apakah siswa masih boleh mengirim tugas baru.
     */
    public function acceptsSubmissions(): bool
    {
        return ! $this->isEnded() && ! $this->isTimeUp();
    }

    /**
     * Format sisa waktu sebagai MM:SS atau HH:MM:SS.
     */
    public function formattedRemaining(): ?string
    {
        $seconds = $this->secondsRemaining();

        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}
