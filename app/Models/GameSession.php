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
    'current_round', 'round_status', 'round_started_at', 'open_rounds',
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
            'open_rounds' => 'array',
        ];
    }

    // ---------------------------------------------------------------------
    // Relasi
    // ---------------------------------------------------------------------

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
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
     * Nomor ronde yang sedang terbuka, urut dari kecil ke besar.
     *
     * Guru boleh membuka beberapa ronde sekaligus (mis. [1, 3, 5]). Bila kolom
     * `open_rounds` belum pernah diisi (sesi lama), ronde aktif tunggal dipakai.
     *
     * @return array<int, int>
     */
    public function openRoundNumbers(): array
    {
        if ($this->open_rounds === null) {
            return $this->current_round > 0 ? [(int) $this->current_round] : [];
        }

        $nomor = array_map('intval', (array) $this->open_rounds);
        $nomor = array_values(array_unique(array_filter($nomor, fn (int $n) => $n > 0)));

        sort($nomor);

        return $nomor;
    }

    /**
     * Apakah lebih dari satu ronde terbuka pada saat bersamaan.
     */
    public function hasMultipleOpenRounds(): bool
    {
        return count($this->openRoundNumbers()) > 1;
    }

    /**
     * Akhir ronde yang sedang berjalan.
     */
    public function roundDeadline(): ?Carbon
    {
        if ($this->round_status !== self::ROUND_RUNNING || ! $this->round_started_at) {
            return null;
        }

        // Bila beberapa ronde terbuka sekaligus, timer ronde tidak dipakai:
        // kelompok bekerja dengan tempo masing-masing, sehingga satu hitung
        // mundur bersama tidak lagi bermakna. Batas waktu per siswa tetap ada.
        if ($this->hasMultipleOpenRounds()) {
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
     * Apakah jam kelas sedang berjalan (ada batas waktu yang aktif).
     *
     * `false` bila sesi tanpa timer (durasi 0) atau jamnya belum dinyalakan:
     * jam baru mulai berjalan ketika guru membuka ronde pertama, bukan saat
     * sesi dibuat.
     */
    public function timeLimitActive(): bool
    {
        return $this->deadline() !== null;
    }

    /**
     * Apakah siswa masih boleh mengirim tugas baru.
     */
    public function acceptsSubmissions(): bool
    {
        return ! $this->isEnded() && ! $this->isTimeUp();
    }

    /**
     * Apakah KELOMPOK ini masih boleh mengirim tugas baru.
     *
     * Aturan kelas tetap berlaku, tetapi jam kelas tidak boleh memblokir
     * kelompok yang baru mulai mengerjakan rondenya SETELAH jam itu lewat —
     * misalnya murid yang baru bergabung karena terlambat. Jatah waktu mereka
     * adalah batas waktu ronde miliknya sendiri (dihitung sejak halaman ronde
     * dibuka), sama seperti `TeamProgress::workSecondsRemaining()`.
     */
    public function acceptsSubmissionsFor(?Team $team = null, ?TeamProgress $progress = null): bool
    {
        if ($this->isEnded()) {
            return false;
        }

        // Jam kelas masih jalan (atau memang tanpa batas): tidak ada yang menutup.
        if (! $this->isTimeUp()) {
            return true;
        }

        if ($team === null) {
            return false;
        }

        if ($progress !== null) {
            return $progress->isFreshWorkWindow($this->deadline());
        }

        return $this->hasFreshWorkWindow($team);
    }

    /**
     * Apakah kelompok ini punya misi yang jatah waktunya baru mulai berjalan
     * setelah batas waktu kelas lewat.
     *
     * Dipakai saat tidak ada satu baris progres tertentu yang bisa diperiksa
     * (mis. header siswa dan halaman tunggu).
     */
    public function hasFreshWorkWindow(Team $team): bool
    {
        $deadline = $this->deadline();

        if ($deadline === null) {
            return true;
        }

        return TeamProgress::query()
            ->where('team_id', $team->id)
            ->where(function ($query) use ($deadline) {
                $query->whereNull('work_started_at')
                    ->orWhere('work_started_at', '>=', $deadline);
            })
            ->whereIn('status', [
                TeamProgress::STATUS_AVAILABLE,
                TeamProgress::STATUS_IN_PROGRESS,
            ])
            ->exists();
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
