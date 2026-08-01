<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationOrderItem extends Model
{
    protected $fillable = ['verification_order_id', 'candidate_verification_item_id', 'price'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(VerificationOrder::class, 'verification_order_id');
    }

    public function verificationItem(): BelongsTo
    {
        return $this->belongsTo(CandidateVerificationItem::class, 'candidate_verification_item_id');
    }
}
