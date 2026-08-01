<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateOtp extends Model
{
    protected $fillable = [
        'phone_or_email', 'code', 'purpose', 'channel', 'expires_at', 'verified_at', 'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
