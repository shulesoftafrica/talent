<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CandidateLicenseVerification extends Model
{
    protected $fillable = [
        'candidate_id', 'candidate_verification_item_id',
        'license_name', 'license_number', 'duplicate_number_flag', 'issuing_authority',
        'expiry_date', 'verification_url', 'certificate_path',
        'status', 'declaration_accepted_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'duplicate_number_flag' => 'boolean',
            'declaration_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function verificationItem(): BelongsTo
    {
        return $this->belongsTo(CandidateVerificationItem::class);
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(VerificationStatusHistory::class, 'subject')->latest('created_at');
    }
}
