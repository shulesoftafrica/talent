<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VerificationStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subject_type', 'subject_id', 'from_status', 'to_status',
        'actor_type', 'actor_id', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
