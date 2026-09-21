<?php

namespace App\Console\Commands;

use App\Services\Onboarding\OnboardingWatcher;
use Illuminate\Console\Command;

/**
 * REQ-HRX-09: reminds new hires who have not done their onboarding, by email and WhatsApp.
 * Scheduled hourly. Sends nothing between 20:00 and 07:00 East Africa Time.
 */
class OnboardingReminders extends Command
{
    protected $signature = 'onboarding:reminders';
    protected $description = 'Send onboarding reminders (not started after 2 days, 2 days before the start date, returned items) outside quiet hours.';

    public function handle(OnboardingWatcher $watcher): int
    {
        $result = $watcher->sendReminders();
        $this->info($result['quiet'] ? 'quiet hours: nothing sent' : "sent={$result['sent']}");

        return self::SUCCESS;
    }
}
