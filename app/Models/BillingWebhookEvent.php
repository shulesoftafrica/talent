<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per received billing webhook delivery, keyed by
 * (idempotency_key, event_type) — lets BillingWebhookController detect and
 * skip duplicate deliveries (the platform retries on timeout/non-2xx) and
 * gives an audit trail for every payment notification received.
 */
class BillingWebhookEvent extends Model
{
    protected $fillable = [
        'idempotency_key', 'event_type', 'payload', 'signature', 'source_ip',
        'processing_status', 'error_message', 'verification_order_id', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(VerificationOrder::class, 'verification_order_id');
    }
}
