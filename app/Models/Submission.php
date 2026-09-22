<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'team_id', 'mission_id', 'answer', 'evidence_path', 'file_path',
    'status', 'teacher_comment', 'submitted_at', 'validated_at',
    'ai_status', 'ai_score', 'ai_feedback', 'ai_details', 'ai_model',
    'ai_reviewed_at', 'ai_overridden', 'authenticity_score', 'authenticity_note',
    'own_words', 'own_words_bonus', 'game_score', 'game_correct', 'game_wrong',
    'game_played_at', 'game_missed',
])]
class Submission extends Model
{
    public const STATUS_WAITING = 'waiting_validation';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVISION = 'revision';

    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'Menunggu Validasi',
        self::STATUS_APPROVED => 'Lulus',
        self::STATUS_REVISION => 'Perlu Perbaikan',
    ];

    // Status penilaian AI atas jawaban refleksi siswa.
    public const AI_PENDING = 'pending';

    public const AI_SCORED = 'scored';

    public const AI_ERROR = 'error';

    public const AI_SKIPPED = 'skipped';

    public const AI_STATUS_LABELS = [
        self::AI_PENDING => 'Menunggu AI',
        self::AI_SCORED => 'Dinilai AI',
        self::AI_ERROR => 'AI Gagal',
        self::AI_SKIPPED => 'AI Dilewati',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'ai_reviewed_at' => 'datetime',
            'ai_details' => 'array',
            'ai_score' => 'integer',
            'ai_overridden' => 'boolean',
            'authenticity_score' => 'integer',
            'own_words' => 'boolean',
            'own_words_bonus' => 'integer',
            'game_score' => 'integer',
            'game_correct' => 'integer',
            'game_wrong' => 'integer',
            'game_played_at' => 'datetime',
            'game_missed' => 'array',
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

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    // ---------------------------------------------------------------------
    // Helper penilaian AI
    // ---------------------------------------------------------------------

    /**
     * Apakah jawaban sudah dinilai AI.
     */
    public function hasAiScore(): bool
    {
        return $this->ai_status === self::AI_SCORED && $this->ai_score !== null;
    }

    /**
     * Label status AI untuk tampilan.
     */
    public function aiStatusLabel(): string
    {
        return self::AI_STATUS_LABELS[$this->ai_status] ?? $this->ai_status;
    }

    /**
     * Apakah jawaban dianggap lulus oleh AI.
     */
    public function passedAi(): bool
    {
        return $this->hasAiScore()
            && $this->ai_score >= (int) config('ai.passing_score');
    }

    /**
     * Daftar kriteria hasil penilaian AI (bila ada).
     *
     * @return array<int, array{kriteria?: string, nilai?: mixed, catatan?: string}>
     */
    public function aiCriteria(): array
    {
        $details = $this->ai_details ?? [];

        return is_array($details['kriteria'] ?? null) ? $details['kriteria'] : [];
    }

    // ---------------------------------------------------------------------
    // Helper indikator keaslian
    // ---------------------------------------------------------------------

    /**
     * Apakah AI sudah memberi indikator keaslian.
     */
    public function hasAuthenticity(): bool
    {
        return $this->authenticity_score !== null;
    }

    /**
     * Label tingkat keaslian (1-5) untuk tampilan guru.
     */
    public function authenticityLabel(): ?string
    {
        return match ($this->authenticity_score) {
            5 => 'Sangat Asli',
            4 => 'Cenderung Asli',
            3 => 'Perlu Ditelusuri',
            2 => 'Mirip Salinan',
            1 => 'Sangat Mirip Salinan',
            default => null,
        };
    }

    /**
     * Kelas warna Tailwind untuk badge keaslian.
     */
    public function authenticityColor(): string
    {
        return match ($this->authenticity_score) {
            5 => 'bg-emerald-500/15 text-emerald-300',
            4 => 'bg-teal-500/15 text-teal-200',
            3 => 'bg-amber-500/15 text-amber-200',
            2, 1 => 'bg-rose-500/15 text-rose-200',
            default => 'bg-white/5 text-white/50',
        };
    }

    /**
     * Apakah jawaban ini sebaiknya diperiksa guru lebih teliti.
     * Ini SINYAL, bukan kesimpulan: siswa tidak dituduh curang.
     */
    public function needsAuthenticityCheck(): bool
    {
        return $this->authenticity_score !== null && $this->authenticity_score <= 2;
    }

    // ---------------------------------------------------------------------
    // Helper file bukti
    // ---------------------------------------------------------------------

    public function evidenceUrl(): ?string
    {
        return $this->evidence_path ? Storage::disk('public')->url($this->evidence_path) : null;
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    /**
     * Apakah bukti berupa gambar sehingga bisa ditampilkan sebagai preview.
     */
    public function evidenceIsImage(): bool
    {
        if (! $this->evidence_path) {
            return false;
        }

        $ext = strtolower(pathinfo($this->evidence_path, PATHINFO_EXTENSION));

        return in_array($ext, config('tikmission.image_extensions'), true);
    }

    /**
     * Nama file asli (setelah prefix acak) untuk ditampilkan ke guru.
     */
    public function evidenceOriginalName(): ?string
    {
        if (! $this->evidence_path) {
            return null;
        }

        // Format penyimpanan: evidence/{team_id}/{random}_{original}
        $base = basename($this->evidence_path);
        $pos = strpos($base, '_');

        return $pos === false ? $base : substr($base, $pos + 1);
    }

    public function fileOriginalName(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        $base = basename($this->file_path);
        $pos = strpos($base, '_');

        return $pos === false ? $base : substr($base, $pos + 1);
    }
}
