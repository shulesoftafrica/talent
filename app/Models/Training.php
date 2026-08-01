<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    protected $fillable = [
        'title', 'profession', 'priority_label', 'why', 'duration', 'organizer',
        'price_label', 'issues_certificate', 'next_training_date', 'seats_available', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['issues_certificate' => 'boolean'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CandidateTraining::class);
    }
}
