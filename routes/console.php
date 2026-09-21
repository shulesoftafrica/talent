<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Onboarding follow-up for new hires (REQ-HRX-09). The server must run `php artisan schedule:run`
// every minute (see README).
Schedule::command('onboarding:sync-statuses')->everyTenMinutes()->withoutOverlapping();
Schedule::command('onboarding:reminders')->hourly()->withoutOverlapping();
