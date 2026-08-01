<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePreference extends Model
{
    protected $fillable = [
        'candidate_id', 'employment_type', 'countries_willing', 'preferred_cities',
        'min_salary', 'max_salary', 'max_travel_km', 'open_to_relocation', 'languages_spoken',
    ];

    protected function casts(): array
    {
        return [
            'countries_willing' => 'array',
            'preferred_cities' => 'array',
            'languages_spoken' => 'array',
            'open_to_relocation' => 'boolean',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
