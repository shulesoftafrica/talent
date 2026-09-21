<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A candidate's answer to an offer, written on Talent and applied by the HR
 * module (REQ-HRX-04/05). Never updated after creation by Talent.
 */
class OfferResponse extends Model
{
    protected $fillable = [
        'candidate_id', 'application_id', 'source_schema', 'source_application_id', 'offer_version', 'action',
        'decline_reason', 'decline_detail', 'signed_name', 'ip', 'user_agent', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $response) {
            $response->uuid ??= (string) Str::uuid();
        });
    }
}
