<?php

namespace App\Services\Onboarding;

use App\Models\Application;
use App\Models\OnboardingItemState;
use App\Models\OnboardingSubmission;
use App\Services\Notifications\UnifiedNotificationClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notices what happened to a new hire's checklist in the HR module and chases what they
 * have not done (REQ-HRX-09).
 *
 * syncStatuses() (every 10 minutes) compares each item's status with what Talent last saw
 * and creates the in-app notification: checklist opened, item returned (with the reviewer's
 * reason), item approved, everything approved. It does not message the new hire again: the HR
 * module already sends the WhatsApp / email / SMS at the moment it returns an item or
 * completes onboarding.
 *
 * sendReminders() (hourly) sends, by email and WhatsApp:
 *   - one reminder 2 days after the checklist opened if nothing has been submitted;
 *   - one reminder 2 days before the start date if required items are still incomplete;
 *   - a reminder each day, at most 3, for an item returned and left untouched for 2 days.
 * Nothing is sent between 20:00 and 07:00 East Africa Time; a reminder held back by quiet hours
 * is simply sent at the next run. Both methods read "now" from the clock, so tests can travel in time.
 */
class OnboardingWatcher
{
    public const TIMEZONE = 'Africa/Dar_es_Salaam';
    public const QUIET_FROM = 20;
    public const QUIET_UNTIL = 7;
    public const MAX_RETURN_REMINDERS = 3;

    public function __construct(private readonly HrOnboardingGateway $origin, private readonly UnifiedNotificationClient $client)
    {
    }

    public static function isQuietHour(CarbonInterface $now): bool
    {
        $hour = (int) $now->copy()->setTimezone(self::TIMEZONE)->format('G');

        return $hour >= self::QUIET_FROM || $hour < self::QUIET_UNTIL;
    }

    /** @return iterable<int, array{0: Application, 1: object}> Talent applications whose offer was accepted, with their HR row */
    private function accepted(): iterable
    {
        foreach (HrOnboardingGateway::MODULES as $module) {
            Application::query()->where('source_schema', $module)->whereNotNull('source_application_id')->whereNull('withdrawn_at')
                ->chunkById(200, function ($applications) use ($module, &$found) {
                    $rows = DB::connection($module)->table('applications')->whereIn('id', $applications->pluck('source_application_id'))->where('offer_status', 'accepted')->get()->keyBy('id');
                    foreach ($applications as $application) {
                        if ($row = $rows->get($application->source_application_id)) {
                            $found[] = [$application, $row];
                        }
                    }
                });
        }

        return $found ?? [];
    }

    private function state(Application $application, string $code, string $baseline = 'pending'): OnboardingItemState
    {
        return OnboardingItemState::query()->firstOrCreate(
            ['source_schema' => $application->source_schema, 'source_application_id' => $application->source_application_id, 'requirement_code' => $code],
            ['last_seen_status' => $baseline]
        );
    }

    private function notice(Application $application, string $title, string $body): void
    {
        $application->notifications()->create([
            'candidate_id' => $application->candidate_id, 'type' => 'onboarding', 'title' => $title, 'body' => $body,
            'action_url' => route('candidate.onboarding', $application),
        ]);
    }

    // ---- noticing changes ----------------------------------------------------------------------

    /** @return array{applications:int, notifications:int} */
    public function syncStatuses(): array
    {
        $applications = 0;
        $notifications = 0;

        foreach ($this->accepted() as [$application, $hr]) {
            $items = $this->origin->checklist($application)->reject(fn ($i) => $i->status === 'locked');
            if ($items->isEmpty()) {
                continue;
            }
            $applications++;
            $job = $application->jobPosting();

            if ($this->state($application, '_opened')->wasRecentlyCreated) {
                $this->notice($application, 'Your onboarding checklist is open', ($job->title ?? 'Your new job').' — upload the documents the school needs before your first day.');
                $notifications++;
            }

            foreach ($items as $item) {
                $state = $this->state($application, $item->requirement_code);
                if ($state->last_seen_status === $item->status) {
                    continue;
                }

                if ($item->status === 'returned') {
                    $this->notice($application, "Please update your {$item->label}", $item->review_note ? "Please update your {$item->label}: {$item->review_note}" : "The school sent your {$item->label} back.");
                    $notifications++;
                } elseif ($item->status === 'approved') {
                    $this->notice($application, "{$item->label} approved", "The school approved your {$item->label}.");
                    $notifications++;
                }
                $state->forceFill(['last_seen_status' => $item->status])->save();
            }

            if (($hr->onboarding_status ?? null) === 'completed' && $this->state($application, '_completed')->wasRecentlyCreated) {
                $this->notice($application, 'Onboarding complete — welcome!', 'The school approved all your onboarding documents.');
                $notifications++;
            }
        }

        return ['applications' => $applications, 'notifications' => $notifications];
    }

    // ---- chasing -------------------------------------------------------------------------------

    /** @return array{sent:int, quiet:bool} */
    public function sendReminders(?CarbonInterface $now = null): array
    {
        $now ??= Carbon::now();
        if (self::isQuietHour($now)) {
            return ['sent' => 0, 'quiet' => true];
        }

        $sent = 0;
        foreach ($this->accepted() as [$application, $hr]) {
            if (($hr->onboarding_status ?? null) === 'completed') {
                continue;
            }
            $items = $this->origin->checklist($application)->reject(fn ($i) => $i->status === 'locked');
            if ($items->isEmpty()) {
                continue;
            }
            $sent += $this->remindNothingSubmitted($application, $hr, $items, $now);
            $sent += $this->remindStartDate($application, $hr, $items, $now);
            $sent += $this->remindReturned($application, $items, $now);
        }

        return ['sent' => $sent, 'quiet' => false];
    }

    private function remindNothingSubmitted(Application $application, object $hr, $items, CarbonInterface $now): int
    {
        if (! $hr->offer_responded_at || $now->lt(Carbon::parse($hr->offer_responded_at)->addDays(2))) {
            return 0;
        }
        $state = $this->state($application, '_nothing_submitted');
        if ($state->reminders_sent > 0) {
            return 0;
        }

        $anySubmitted = $items->contains(fn ($i) => in_array($i->status, ['submitted', 'approved', 'waived'], true))
            || OnboardingSubmission::query()->where('source_schema', $application->source_schema)->where('source_application_id', $hr->id)->where('status', 'submitted')->exists();
        if ($anySubmitted) {
            return 0;
        }

        return $this->remind($application, $state, 'Your onboarding is waiting', 'You accepted your offer but have not sent any onboarding documents yet. Please upload them so the school can prepare for your first day.', $now);
    }

    private function remindStartDate(Application $application, object $hr, $items, CarbonInterface $now): int
    {
        if (! $hr->offer_start_date || $now->lt(Carbon::parse($hr->offer_start_date)->subDays(2)->startOfDay())) {
            return 0;
        }
        $state = $this->state($application, '_start_date');
        if ($state->reminders_sent > 0) {
            return 0;
        }

        $submitted = OnboardingSubmission::query()->where('source_schema', $application->source_schema)->where('source_application_id', $hr->id)->where('status', 'submitted')->pluck('requirement_code');
        $incomplete = $items->filter(fn ($i) => $i->required)->contains(fn ($i) => ! in_array($i->status, ['submitted', 'approved', 'waived'], true) && ! $submitted->contains($i->requirement_code));
        if (! $incomplete) {
            return 0;
        }

        return $this->remind($application, $state, 'Your first day is close', 'Your start date is in two days and some required onboarding items are still incomplete. Please finish them now.', $now);
    }

    private function remindReturned(Application $application, $items, CarbonInterface $now): int
    {
        $sent = 0;
        foreach ($items->where('status', 'returned') as $item) {
            if (! $item->reviewed_at || $now->lt(Carbon::parse($item->reviewed_at)->addDays(2))) {
                continue;
            }
            $state = $this->state($application, $item->requirement_code, 'returned');
            if ($state->reminders_sent >= self::MAX_RETURN_REMINDERS || ($state->last_reminded_at && $now->lt($state->last_reminded_at->copy()->addDay()))) {
                continue;
            }

            $sent += $this->remind($application, $state, "Please update your {$item->label}", "Your {$item->label} was sent back and is still waiting for you".($item->review_note ? ": {$item->review_note}" : '.'), $now);
        }

        return $sent;
    }

    /** Sends the reminder by email and WhatsApp, and records it. Returns 1 when at least one channel took it. */
    private function remind(Application $application, OnboardingItemState $state, string $subject, string $body, CarbonInterface $now): int
    {
        $candidate = $application->candidate;
        $message = "Hello {$candidate->full_name},\n\n{$body}\n\nOpen your onboarding: ".route('candidate.onboarding', $application);
        $schema = config('services.notification.schema_name', 'talent');
        $delivered = false;

        try {
            if ($candidate->email) {
                $delivered = $this->client->send(['schema_name' => $schema, 'channel' => 'email', 'to' => $candidate->email, 'subject' => $subject, 'message' => $message]) !== null || $delivered;
            }
            if ($candidate->phone) {
                $delivered = $this->client->send(['schema_name' => $schema, 'channel' => 'whatsapp', 'to' => $candidate->phone, 'message' => $message]) !== null || $delivered;
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingWatcher: reminder failed', ['application_id' => $application->id, 'error' => $e->getMessage()]);
        }

        // A reminder nobody received is tried again at the next run.
        if (! $delivered) {
            return 0;
        }
        $state->forceFill(['reminders_sent' => $state->reminders_sent + 1, 'last_reminded_at' => $now])->save();

        return 1;
    }
}
