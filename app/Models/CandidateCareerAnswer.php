<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateCareerAnswer extends Model
{
    protected $fillable = ['candidate_id', 'field_key', 'field_value'];

    protected function casts(): array
    {
        return ['field_value' => 'array'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
