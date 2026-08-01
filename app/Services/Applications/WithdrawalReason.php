<?php

namespace App\Services\Applications;

/**
 * Fixed withdrawal-reason keys a candidate picks from. Keys (not labels) are
 * what gets stored/validated — labels are translated at display time via
 * lang/*\/applications.php's `reason_{key}` entries.
 */
class WithdrawalReason
{
    public const KEYS = [
        'accepted_other_offer',
        'no_longer_interested',
        'salary_mismatch',
        'location_unsuitable',
        'applied_by_mistake',
        'career_goals_changed',
        'other',
    ];
}
