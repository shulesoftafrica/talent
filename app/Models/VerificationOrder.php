<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single generic "payment order" record for every paid feature in the
 * app — not just verification. `kind` is the product key (verification,
 * premium, and any future paid feature); `meta` carries whatever that
 * product's fulfillment handler needs. See App\Services\Billing\PaymentService,
 * which is the one entry point every checkout flow should call rather than
 * writing invoice-creation logic directly. The class/table name is legacy
 * from when this only handled verification purchases.
 */
class VerificationOrder extends Model
{
    protected $fillable = [
        'candidate_id', 'kind', 'total_amount', 'currency', 'status', 'meta',
        'billing_invoice_id', 'billing_ucn', 'billing_stripe_link', 'billing_flutterwave_link', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VerificationOrderItem::class);
    }
}
