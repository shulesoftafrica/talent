<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateEducation extends Model
{
    use HasUuidRouteKey;

    // Explicit — Eloquent's pluralizer treats "Education" as uncountable and
    // would otherwise guess "candidate_education" (no trailing 's').
    protected $table = 'candidate_educations';

    protected $fillable = [
        'candidate_id', 'degree', 'school', 'start_year', 'end_year', 'status', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
