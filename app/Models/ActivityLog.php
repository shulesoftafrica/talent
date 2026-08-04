<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'candidate_id', 'session_id', 'page_token', 'method', 'path', 'route_name',
        'referrer_path', 'ip_address', 'country_id', 'device_type', 'browser',
        'platform', 'user_agent', 'duration_ms', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
