<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CandidateEmploymentVerificationDoc extends Model
{
    protected $fillable = [
        'candidate_verification_item_id', 'candidate_experience_id',
        'employer_name', 'employer_address', 'employer_website',
        'hr_email', 'hr_email_low_confidence',
        'supervisor_name', 'supervisor_email', 'supervisor_email_low_confidence', 'supervisor_phone',
        'status', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'hr_email_low_confidence' => 'boolean',
            'supervisor_email_low_confidence' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function verificationItem(): BelongsTo
    {
        return $this->belongsTo(CandidateVerificationItem::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(CandidateExperience::class, 'candidate_experience_id');
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(VerificationStatusHistory::class, 'subject')->latest('created_at');
    }
}
