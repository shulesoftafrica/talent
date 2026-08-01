<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CandidateVerificationItem extends Model
{
    protected $fillable = [
        'candidate_id', 'verification_type_id', 'status', 'price', 'currency', 'payer',
        'verified_on', 'valid_until', 'method', 'submitted_at', 'reviewed_by', 'reviewed_at', 'officer_notes',
        'declaration_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_on' => 'date',
            'valid_until' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'declaration_accepted_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function verificationType(): BelongsTo
    {
        return $this->belongsTo(VerificationType::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(OfficerActionLog::class)->latest();
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(VerificationStatusHistory::class, 'subject')->latest('created_at');
    }

    public function identityVerification(): HasOne
    {
        return $this->hasOne(CandidateIdentityVerification::class);
    }

    public function licenseVerification(): HasOne
    {
        return $this->hasOne(CandidateLicenseVerification::class);
    }

    public function educationDocs(): HasMany
    {
        return $this->hasMany(CandidateEducationVerificationDoc::class);
    }

    public function employmentDocs(): HasMany
    {
        return $this->hasMany(CandidateEmploymentVerificationDoc::class);
    }

    public function backgroundVerification(): HasOne
    {
        return $this->hasOne(CandidateBackgroundVerification::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(CandidateVerificationReference::class);
    }
}
