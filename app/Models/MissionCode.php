<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_session_id', 'mission_id', 'code'])]
class MissionCode extends Model
{
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Cek kode rahasia tanpa membedakan huruf besar/kecil dan spasi tepi.
     */
    public function matches(string $input): bool
    {
        return strcasecmp(trim($input), trim($this->code)) === 0;
    }
}
