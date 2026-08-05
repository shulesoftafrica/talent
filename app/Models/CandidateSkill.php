<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateSkill extends Model
{
    use HasUuidRouteKey;

    protected $fillable = ['candidate_id', 'name', 'is_verified'];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
