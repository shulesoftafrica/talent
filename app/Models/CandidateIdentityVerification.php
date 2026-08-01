<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CandidateIdentityVerification extends Model
{
    protected $fillable = [
        'candidate_id', 'candidate_verification_item_id',
        'primary_doc_type', 'primary_doc_path', 'primary_doc_size_bytes',
        'tin_certificate_path', 'local_government_letter_path', 'pension_fund_number',
        'status', 'declaration_accepted_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
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
