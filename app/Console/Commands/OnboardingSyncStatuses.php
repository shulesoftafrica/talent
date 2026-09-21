<?php

namespace App\Console\Commands;

use App\Services\Onboarding\OnboardingWatcher;
use Illuminate\Console\Command;

/** REQ-HRX-09: notices onboarding items the HR module returned or approved. Scheduled every 10 minutes. */
class OnboardingSyncStatuses extends Command
{
    protected $signature = 'onboarding:sync-statuses';
    protected $description = 'Create in-app notifications for onboarding items HR returned or approved, and for a completed onboarding.';

    public function handle(OnboardingWatcher $watcher): int
    {
        $result = $watcher->syncStatuses();
        $this->info("applications={$result['applications']} notifications={$result['notifications']}");

        return self::SUCCESS;
    }
}
