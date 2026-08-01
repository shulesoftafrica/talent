<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerActionLog extends Model
{
    protected $fillable = ['candidate_verification_item_id', 'officer_id', 'officer_name', 'action', 'notes'];

    public function verificationItem(): BelongsTo
    {
        return $this->belongsTo(CandidateVerificationItem::class, 'candidate_verification_item_id');
    }
}
