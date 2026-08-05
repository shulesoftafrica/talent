<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use App\Models\Constant\ReferSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateTeachingSubject extends Model
{
    use HasUuidRouteKey;

    protected $fillable = ['candidate_id', 'subject_id', 'years_experience'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Cross-schema relation into constant.refer_subjects — read-only.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(ReferSubject::class, 'subject_id', 'subject_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(CandidateTeachingSubjectClass::class);
    }
}
