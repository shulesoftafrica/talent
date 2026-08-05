<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CandidateVerificationReference extends Model
{
    use HasUuidRouteKey;

    protected $fillable = [
        'candidate_verification_item_id', 'candidate_experience_id',
        'full_name', 'position', 'relationship', 'corporate_email', 'phone',
        'worked_together_from', 'worked_together_to', 'low_confidence_email', 'status',
    ];

    protected function casts(): array
    {
        return [
            'worked_together_from' => 'date',
            'worked_together_to' => 'date',
            'low_confidence_email' => 'boolean',
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
