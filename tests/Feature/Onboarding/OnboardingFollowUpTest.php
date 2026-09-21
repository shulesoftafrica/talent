<?php

namespace Tests\Feature\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\OnboardingItemState;
use App\Models\OnboardingSubmission;
use App\Services\Notifications\UnifiedNotificationClient;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\OnboardingWatcher;
use App\Services\Onboarding\SharedEncrypter;
use Dotenv\Dotenv;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Records what would be sent; a channel listed in $down "fails". */
class RecordingNotificationClient extends UnifiedNotificationClient
{
    public array $sent = [];
    public array $down = [];

    public function send(array $payload): ?array
    {
        $this->sent[] = $payload;

        return in_array($payload['channel'], $this->down, true) ? null : ['success' => true];
    }
}

/**
 * REQ-HRX-09: returned items, notifications and reminders. Real shared Postgres; disposable
 * tenant 'zzzfollow01'. Times are East Africa Time (UTC+3): 07:00 UTC is 10:00 in Dar es Salaam.
 */
class OnboardingFollowUpTest extends TestCase
{
    private const TENANT = 'zzzfollow01';
    private const MODULE = 'shulesoft';

    private string $root;
    private int $jobId;
    private int $hrId;
    private Candidate $candidate;
    private Application $application;
    private RecordingNotificationClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $env = Dotenv::parse((string) file_get_contents(base_path('.env')));
        foreach (config('database.connections') as $name => $definition) {
            if (($definition['driver'] ?? null) === 'pgsql') {
                config(["database.connections.{$name}.database" => $env['DB_DATABASE']]);
                DB::purge($name);
            }
        }
        config(['database.default' => 'pgsql']);

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'talent_follow_'.uniqid();
        File::ensureDirectoryExists($this->root);
        config([
            'hr_onboarding.enabled' => true,
            'hr_onboarding.disk' => 'hr_onboarding',
            'hr_onboarding.encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
            'filesystems.disks.hr_onboarding' => [
                'driver' => 'local', 'root' => $this->root, 'serve' => true, 'url' => 'http://localhost/private-onboarding',
                'visibility' => 'private', 'throw' => true, 'report' => false,
            ],
        ]);
        Storage::forgetDisk('hr_onboarding');
        $this->app->forgetInstance(SharedEncrypter::class);

        $this->client = new RecordingNotificationClient();
        $this->app->instance(UnifiedNotificationClient::class, $this->client);

        // A fixed clock: Monday 10:00 in Dar es Salaam.
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:00:00', 'UTC'));

        $this->cleanDatabase();
        $this->candidate = Candidate::query()->create(['full_name' => 'ZZZ Follow Candidate', 'email' => 'zzzfollow01@x.com', 'phone' => '+255754123411']);
        $this->jobId = (int) $this->hr()->table('job_postings')->insertGetId([
            'title' => 'ZZZ Follow Role', 'slug' => 'zzz-follow-'.uniqid(), 'description' => 'x', 'requirements' => 'x',
            'department' => 'Finance', 'status' => 'active', 'schema_name' => self::TENANT, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hrId = (int) $this->hr()->table('applications')->insertGetId([
            'job_posting_id' => $this->jobId, 'name' => 'ZZZ Follow Candidate', 'email' => 'zzzfollow01@x.com', 'phone' => '255754123411', 'status' => 'hired',
            'schema_name' => self::TENANT, 'offer_status' => 'accepted', 'offer_version' => 1, 'offer_token' => str_repeat('e', 40),
            'offer_token_expires_at' => now()->addDays(3), 'offer_responded_at' => now(), 'offer_start_date' => now()->addDays(20)->toDateString(),
            'onboarding_status' => 'not_started', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['birth_certificate', 'Birth certificate', 'file', true], ['tin', 'TIN', 'tin', true], ['profile_picture', 'Profile picture', 'photo', false]] as $i => [$code, $label, $type, $required]) {
            $this->hr()->table('hr_onboarding_items')->insert([
                'schema_name' => self::TENANT, 'application_id' => $this->hrId, 'requirement_code' => $code, 'label' => $label, 'type' => $type,
                'required' => DB::raw($required ? 'true' : 'false'), 'sort_order' => $i, 'accepts' => json_encode(['application/pdf']), 'max_files' => 1, 'max_size_kb' => 5120,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->application = Application::query()->create([
            'candidate_id' => $this->candidate->id, 'source_schema' => self::MODULE, 'source_application_id' => $this->hrId,
            'source_job_posting_id' => $this->jobId, 'source_channel' => 'offer_claim', 'applied_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->cleanDatabase();
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function hr()
    {
        return DB::connection(self::MODULE);
    }

    private function cleanDatabase(): void
    {
        $ids = $this->hr()->table('applications')->where('schema_name', self::TENANT)->pluck('id');
        DB::table('onboarding_item_state')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('onboarding_submissions')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('notifications')->whereIn('candidate_id', DB::table('candidates')->where('email', 'like', 'zzzfollow01%')->pluck('id'))->delete();
        DB::table('applications')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('candidates')->where('email', 'like', 'zzzfollow01%')->delete();
        foreach (['hr_onboarding_items', 'applications', 'job_postings'] as $table) {
            $this->hr()->table($table)->where('schema_name', self::TENANT)->delete();
        }
    }

    private function watcher(): OnboardingWatcher
    {
        return $this->app->make(OnboardingWatcher::class);
    }

    private function setItem(string $code, array $columns): void
    {
        $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->where('requirement_code', $code)->update($columns);
    }

    private function notes(): array
    {
        return $this->candidate->notifications()->orderBy('id')->get()->map(fn ($n) => $n->title)->all();
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
    }

    // ---- noticing HR's decisions --------------------------------------------------------------

    public function test_the_new_hire_is_notified_once_when_the_checklist_opens(): void
    {
        $this->assertSame(['applications' => 1, 'notifications' => 1], $this->watcher()->syncStatuses());
        $this->assertSame(['Your onboarding checklist is open'], $this->notes());

        $this->assertSame(0, $this->watcher()->syncStatuses()['notifications'], 'not again');
        $this->assertSame(route('candidate.onboarding', $this->application), $this->candidate->notifications()->first()->action_url);
    }

    public function test_a_returned_item_notifies_with_the_reason_and_an_approved_one_says_so(): void
    {
        $this->watcher()->syncStatuses();

        $this->setItem('tin', ['status' => 'returned', 'review_note' => 'TIN does not match certificate', 'reviewed_at' => now()]);
        $this->assertSame(1, $this->watcher()->syncStatuses()['notifications']);
        $returned = $this->candidate->notifications()->latest('id')->first();
        $this->assertSame('Please update your TIN', $returned->title);
        $this->assertSame('Please update your TIN: TIN does not match certificate', $returned->body);

        $this->assertSame(0, $this->watcher()->syncStatuses()['notifications'], 'a change is noticed once');

        $this->setItem('birth_certificate', ['status' => 'approved']);
        $this->watcher()->syncStatuses();
        $this->assertContains('Birth certificate approved', $this->notes());

        // resubmitted: a quiet change, no notification
        $this->setItem('tin', ['status' => 'submitted', 'review_note' => null]);
        $this->assertSame(0, $this->watcher()->syncStatuses()['notifications']);
    }

    public function test_completed_onboarding_says_welcome_once(): void
    {
        $this->watcher()->syncStatuses();
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['onboarding_status' => 'completed']);

        $this->assertSame(1, $this->watcher()->syncStatuses()['notifications']);
        $this->assertContains('Onboarding complete — welcome!', $this->notes());
        $this->assertSame(0, $this->watcher()->syncStatuses()['notifications']);
    }

    public function test_a_returned_item_can_be_edited_and_resubmitted_and_is_eligible_for_hr_again(): void
    {
        Storage::disk('hr_onboarding')->makeDirectory('onboarding');
        $service = $this->app->make(OnboardingService::class);
        $service->submitItem($this->application, $this->candidate, (int) $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->where('requirement_code', 'tin')->value('id'), ['tin' => '123456789']);
        $row = OnboardingSubmission::query()->where('requirement_code', 'tin')->where('source_application_id', $this->hrId)->first();
        $row->forceFill(['synced_at' => now()])->save();      // the HR module processed it
        $firstSubmittedAt = $row->submitted_at;

        $this->setItem('tin', ['status' => 'returned', 'review_note' => 'Wrong number', 'reviewed_at' => now()]);
        Carbon::setTestNow(now()->addHour());
        $service->submitItem($this->application, $this->candidate, (int) $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->where('requirement_code', 'tin')->value('id'), ['tin' => '987654321']);

        $row->refresh();
        $this->assertSame('submitted', $row->status);
        $this->assertSame(2, (int) $row->version);
        $this->assertTrue($row->submitted_at->gt($firstSubmittedAt));
        $this->assertTrue($row->synced_at->lt($row->submitted_at), 'newer than the last sync, so the HR module picks it up again');
    }

    // ---- reminders ------------------------------------------------------------------------------

    public function test_a_new_hire_who_submitted_nothing_gets_one_reminder_after_two_days(): void
    {
        $this->at('2026-10-06 07:00:00');   // one day later, 10:00 EAT
        $this->assertSame(0, $this->watcher()->sendReminders()['sent']);

        $this->at('2026-10-07 07:00:00');   // two days later, 10:00 EAT
        $this->assertSame(1, $this->watcher()->sendReminders()['sent']);

        $this->assertSame(['email', 'whatsapp'], array_column($this->client->sent, 'channel'));
        $this->assertSame('zzzfollow01@x.com', $this->client->sent[0]['to']);
        $this->assertSame('+255754123411', $this->client->sent[1]['to']);
        $this->assertStringContainsString(route('candidate.onboarding', $this->application), $this->client->sent[1]['message']);

        $this->at('2026-10-09 07:00:00');
        $this->assertSame(0, $this->watcher()->sendReminders()['sent'], 'only one');
    }

    public function test_no_reminder_goes_out_between_20_00_and_07_00_and_it_waits_for_the_morning(): void
    {
        $watcher = $this->watcher();

        // 17:00 UTC is 20:00 EAT; 03:59 UTC is 06:59 EAT; 04:00 UTC is 07:00 EAT
        $this->assertTrue(OnboardingWatcher::isQuietHour(Carbon::parse('2026-10-07 17:00:00', 'UTC')), '20:00 EAT is quiet');
        $this->assertFalse(OnboardingWatcher::isQuietHour(Carbon::parse('2026-10-07 16:59:00', 'UTC')), '19:59 EAT is not');
        $this->assertTrue(OnboardingWatcher::isQuietHour(Carbon::parse('2026-10-07 03:59:00', 'UTC')), '06:59 EAT is quiet');
        $this->assertFalse(OnboardingWatcher::isQuietHour(Carbon::parse('2026-10-07 04:00:00', 'UTC')), '07:00 EAT is not');

        $this->at('2026-10-07 18:00:00');   // 21:00 EAT
        $result = $watcher->sendReminders();
        $this->assertSame(['sent' => 0, 'quiet' => true], $result);
        $this->assertSame([], $this->client->sent);

        $this->at('2026-10-08 00:30:00');   // 03:30 EAT
        $this->assertSame(0, $watcher->sendReminders()['sent']);

        $this->at('2026-10-08 04:00:00');   // 07:00 EAT
        $this->assertSame(1, $watcher->sendReminders()['sent'], 'held back by quiet hours, sent in the morning');
    }

    public function test_nothing_is_sent_once_the_new_hire_has_submitted_something(): void
    {
        $this->setItem('birth_certificate', ['status' => 'submitted']);
        $this->at('2026-10-08 07:00:00');

        $this->assertSame(0, $this->watcher()->sendReminders()['sent']);
    }

    public function test_a_reminder_goes_out_two_days_before_the_start_date_if_required_items_are_incomplete(): void
    {
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_start_date' => '2026-11-01', 'offer_responded_at' => '2026-10-05 07:00:00']);
        // a document was sent, so the "nothing submitted" reminder does not apply
        $this->setItem('birth_certificate', ['status' => 'submitted']);
        $this->at('2026-10-29 07:00:00');   // three days before
        $this->assertSame(0, $this->watcher()->sendReminders()['sent']);

        $this->at('2026-10-30 07:00:00');   // two days before
        $this->assertSame(1, $this->watcher()->sendReminders()['sent']);
        $this->assertStringContainsString('Your first day is close', $this->client->sent[0]['subject']);
        $this->assertSame(0, $this->watcher()->sendReminders()['sent'], 'once');
    }

    public function test_no_start_date_reminder_when_every_required_item_is_in(): void
    {
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_start_date' => '2026-11-01']);
        $this->setItem('birth_certificate', ['status' => 'submitted']);
        $this->setItem('tin', ['status' => 'approved']);
        $this->at('2026-10-30 07:00:00');

        $this->assertSame(0, $this->watcher()->sendReminders()['sent'], 'only the optional photo is missing');
    }

    public function test_a_returned_item_left_untouched_is_chased_daily_at_most_three_times(): void
    {
        $this->setItem('birth_certificate', ['status' => 'submitted']);   // so the other reminders stay out of the way
        $this->setItem('tin', ['status' => 'returned', 'review_note' => 'Blurry', 'reviewed_at' => '2026-10-05 07:00:00']);
        $watcher = $this->watcher();

        $this->at('2026-10-06 07:00:00');
        $this->assertSame(0, $watcher->sendReminders()['sent'], 'not yet two days');

        $counts = [];
        foreach (['2026-10-07 07:00:00', '2026-10-07 09:00:00', '2026-10-08 07:00:00', '2026-10-09 07:00:00', '2026-10-10 07:00:00', '2026-10-11 07:00:00'] as $when) {
            $this->at($when);
            $counts[] = $watcher->sendReminders()['sent'];
        }

        $this->assertSame([1, 0, 1, 1, 0, 0], $counts, 'one a day, never more than three');
        $this->assertSame(3, OnboardingItemState::query()->where('source_application_id', $this->hrId)->where('requirement_code', 'tin')->value('reminders_sent'));
        $this->assertStringContainsString('Blurry', $this->client->sent[0]['message']);
    }

    public function test_an_undelivered_reminder_is_tried_again_and_a_completed_onboarding_is_left_alone(): void
    {
        $this->client->down = ['email', 'whatsapp'];
        $this->at('2026-10-07 07:00:00');
        $this->assertSame(0, $this->watcher()->sendReminders()['sent']);
        $this->assertSame(0, (int) OnboardingItemState::query()->where('source_application_id', $this->hrId)->where('requirement_code', '_nothing_submitted')->value('reminders_sent'));

        $this->client->down = [];
        $this->assertSame(1, $this->watcher()->sendReminders()['sent'], 'delivered on the next run');

        $this->client->sent = [];
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['onboarding_status' => 'completed']);
        $this->at('2026-10-20 07:00:00');
        $this->assertSame(0, $this->watcher()->sendReminders()['sent']);
    }

    public function test_offers_that_were_not_accepted_are_ignored(): void
    {
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'sent']);
        $this->at('2026-10-07 07:00:00');

        $this->assertSame(['applications' => 0, 'notifications' => 0], $this->watcher()->syncStatuses());
        $this->assertSame(0, $this->watcher()->sendReminders()['sent']);
    }

    // ---- scheduling -------------------------------------------------------------------------------

    public function test_the_commands_are_registered_on_the_schedule_and_documented(): void
    {
        $events = collect($this->app->make(Schedule::class)->events());
        $find = fn (string $command) => $events->first(fn ($e) => str_contains($e->command, $command));

        $this->assertSame('*/10 * * * *', $find('onboarding:sync-statuses')->expression);
        $this->assertSame('0 * * * *', $find('onboarding:reminders')->expression);
        $this->assertTrue($find('onboarding:reminders')->withoutOverlapping);

        $readme = (string) file_get_contents(base_path('README.md'));
        $this->assertStringContainsString('schedule:run', $readme);
        $this->assertStringContainsString('onboarding:sync-statuses', $readme);
        $this->assertStringContainsString('onboarding:reminders', $readme);
        $this->assertStringContainsString('20:00 and 07:00', $readme);
    }

    public function test_the_commands_run(): void
    {
        $this->artisan('onboarding:sync-statuses')->assertSuccessful();
        $this->at('2026-10-07 18:00:00');
        $this->artisan('onboarding:reminders')->expectsOutput('quiet hours: nothing sent')->assertSuccessful();
    }
}
