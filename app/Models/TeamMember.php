<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'name'])]
class TeamMember extends Model
{
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
