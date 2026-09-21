<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Last status Talent saw for one onboarding item, and its reminder count (REQ-HRX-09). */
class OnboardingItemState extends Model
{
    protected $table = 'onboarding_item_state';

    protected $fillable = ['source_schema', 'source_application_id', 'requirement_code', 'last_seen_status', 'reminders_sent', 'last_reminded_at'];

    protected function casts(): array
    {
        return ['last_reminded_at' => 'datetime'];
    }
}
