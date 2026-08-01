<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CandidateEducationVerificationDoc extends Model
{
    protected $fillable = [
        'candidate_verification_item_id', 'candidate_education_id',
        'certificate_path', 'transcript_path', 'status', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function verificationItem(): BelongsTo
    {
        return $this->belongsTo(CandidateVerificationItem::class);
    }

    public function education(): BelongsTo
    {
        return $this->belongsTo(CandidateEducation::class, 'candidate_education_id');
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(VerificationStatusHistory::class, 'subject')->latest('created_at');
    }
}
