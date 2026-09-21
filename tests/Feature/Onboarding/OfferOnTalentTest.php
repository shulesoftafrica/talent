<?php

namespace Tests\Feature\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\OfferResponse;
use App\Services\Applications\ApplicationStatusMapper;
use App\Services\Applications\NotificationService;
use App\Services\Notifications\OtpService;
use App\Services\Onboarding\OfferAnswerRejectedException;
use App\Services\Onboarding\OfferService;
use App\Services\Onboarding\SharedEncrypter;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REQ-HRX-04: the offer on Talent -- status labels, the offer card, answering
 * only through Talent's own `offer_responses`, the letter through a temporary
 * link, and the claim flow for careers-page applicants. Real shared Postgres;
 * everything lives under the disposable tenant 'zzzoffer01' and is deleted again.
 */
class OfferOnTalentTest extends TestCase
{
    private const TENANT = 'zzzoffer01';
    private const MODULE = 'shulesoft';
    private const TOKEN = 'b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1';

    private string $root;
    private int $jobId;
    private int $hrId;
    private Candidate $candidate;

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

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'talent_offer_'.uniqid();
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

        $this->cleanDatabase();

        $this->candidate = Candidate::query()->create(['full_name' => 'ZZZ Jane Candidate', 'email' => 'zzzoffer01@x.com', 'phone' => '+255754123456']);
        $this->jobId = (int) DB::connection(self::MODULE)->table('job_postings')->insertGetId([
            'title' => 'ZZZ Accountant', 'slug' => 'zzz-offer-'.uniqid(), 'description' => 'x', 'requirements' => 'x',
            'department' => 'Finance', 'status' => 'active', 'schema_name' => self::TENANT, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hrId = $this->hrApplication();
    }

    protected function tearDown(): void
    {
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
        DB::table('notifications')->whereIn('candidate_id', DB::table('candidates')->where('email', 'like', 'zzzoffer01%')->pluck('id'))->delete();
        DB::table('offer_responses')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('applications')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('candidates')->where('email', 'like', 'zzzoffer01%')->orWhere('full_name', 'like', 'ZZZ Offer%')->delete();
        foreach (['hr_audit_logs', 'applications', 'job_postings'] as $table) {
            $this->hr()->table($table)->where('schema_name', self::TENANT)->delete();
        }
    }

    private function hrApplication(array $overrides = []): int
    {
        return (int) $this->hr()->table('applications')->insertGetId(array_merge([
            'job_posting_id' => $this->jobId, 'name' => 'ZZZ Jane Candidate', 'email' => 'zzzoffer01@x.com', 'phone' => '255754123456',
            'status' => 'offer', 'schema_name' => self::TENANT, 'offer_status' => 'sent', 'offer_version' => 1,
            'offer_token' => self::TOKEN, 'offer_token_expires_at' => now()->addDays(7), 'offer_salary' => 900000,
            'offer_start_date' => now()->addMonth()->toDateString(), 'offer_probation_months' => 3, 'onboarding_status' => 'locked',
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    private function talentApplication(): Application
    {
        return Application::query()->create([
            'candidate_id' => $this->candidate->id, 'source_schema' => self::MODULE, 'source_application_id' => $this->hrId,
            'source_job_posting_id' => $this->jobId, 'source_channel' => 'offer_claim', 'applied_at' => now(),
        ]);
    }

    private function offers(): OfferService
    {
        return $this->app->make(OfferService::class);
    }

    private function accept(Application $application, array $input = []): OfferResponse
    {
        return $this->offers()->respond($application, $this->candidate, 'accept', $input + ['signed_name' => 'ZZZ Jane Candidate', 'confirmed' => '1'], '10.0.0.9', 'TestBrowser/1.0');
    }

    private function hrRow(): object
    {
        return $this->hr()->table('applications')->find($this->hrId);
    }

    // ---- status labels ------------------------------------------------------------

    public function test_a_candidate_in_offer_sees_offer_received_and_the_offer_card_not_applied(): void
    {
        $application = $this->talentApplication();
        $meta = $application->statusMeta();

        $this->assertSame('Offer received', $meta['display_label']);
        $this->assertSame('attention', $meta['urgency']);

        $this->actingAs($this->candidate, 'candidate')
            ->get(route('candidate.applications.index', ['selected' => $application->uuid]))
            ->assertOk()->assertSee('Offer received')->assertSee('View and answer your offer')->assertDontSee('Current stage — Applied');

        $this->get(route('candidate.applications.offer', $application))
            ->assertOk()->assertSee('ZZZ Accountant')->assertSee('900,000')->assertSee('Accept offer')->assertSee('I have read and accept the terms of this offer');
    }

    public function test_the_onboarding_state_changes_the_label_after_acceptance(): void
    {
        $cases = [
            ['not_started', 'Offer accepted', 'Complete your onboarding'],
            ['in_progress', 'Offer accepted', 'Complete your onboarding'],
            ['submitted', 'Onboarding under review', 'Wait for the school to review'],
            ['changes_requested', 'Onboarding: changes requested', 'Fix the returned items'],
            ['completed', 'Onboarding complete', 'Welcome aboard'],
        ];
        $application = $this->talentApplication();
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['status' => 'hired', 'offer_status' => 'accepted']);

        foreach ($cases as [$onboarding, $label, $action]) {
            $this->hr()->table('applications')->where('id', $this->hrId)->update(['onboarding_status' => $onboarding]);
            $meta = Application::query()->find($application->id)->statusMeta();
            $this->assertSame($label, $meta['display_label'], $onboarding);
            $this->assertSame($action, $meta['next_action_label'], $onboarding);
        }
    }

    public function test_expired_and_declined_offers_have_their_own_labels(): void
    {
        $application = $this->talentApplication();

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['status' => 'offer_expired', 'offer_status' => 'expired']);
        $this->assertSame('Offer expired', Application::query()->find($application->id)->statusMeta()['display_label']);

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['status' => 'rejected', 'offer_status' => 'declined']);
        $this->assertSame('Offer declined', Application::query()->find($application->id)->statusMeta()['display_label']);

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => null]);
        $this->assertSame('Not Selected', Application::query()->find($application->id)->statusMeta()['display_label'], 'an ordinary rejection is unchanged');
    }

    public function test_every_status_the_hr_module_can_hold_has_a_real_label(): void
    {
        $definition = DB::selectOne("select pg_get_constraintdef(oid) d from pg_constraint where conname = 'applications_status_check' and connamespace = 'shulesoft'::regnamespace")->d;
        preg_match_all("/'([a-z_]+)'::text/", $definition, $found);
        $statuses = array_unique($found[1]);
        $this->assertContains('offer_expired', $statuses);

        foreach ($statuses as $status) {
            $meta = ApplicationStatusMapper::resolve($status);
            $this->assertNotSame('Applied', $meta['display_label'] === 'Applied' && $status !== 'new' ? 'Applied' : 'ok', "'{$status}' falls back to 'Applied'");
            $this->assertArrayHasKey('next_action_label', $meta);
        }
    }

    // ---- answering ----------------------------------------------------------------

    public function test_accepting_writes_one_response_with_the_evidence_and_nothing_into_the_hr_schema(): void
    {
        $application = $this->talentApplication();
        $before = $this->hrRow();

        $response = $this->accept($application);

        $this->assertSame(1, OfferResponse::query()->where('source_application_id', $this->hrId)->count());
        $this->assertSame('accept', $response->action);
        $this->assertSame('ZZZ Jane Candidate', $response->signed_name);
        $this->assertSame('10.0.0.9', $response->ip);
        $this->assertSame('TestBrowser/1.0', $response->user_agent);
        $this->assertSame(1, (int) $response->offer_version);
        $this->assertNotNull($response->uuid);
        $this->assertNull($response->processed_at, 'the HR module applies it, not Talent');

        $after = $this->hrRow();
        $this->assertEquals((array) $before, (array) $after, 'no write into the HR schema');
    }

    public function test_a_second_answer_for_the_same_offer_is_refused(): void
    {
        $application = $this->talentApplication();
        $this->accept($application);

        foreach ([
            fn () => $this->accept($application),
            fn () => $this->offers()->respond($application, $this->candidate, 'decline', ['reason' => 'salary'], null, null),
        ] as $second) {
            try {
                $second();
                $this->fail('a second answer must be refused');
            } catch (OfferAnswerRejectedException $e) {
                $this->assertStringContainsString('already been answered', $e->getMessage());
            }
        }
        $this->assertSame(1, OfferResponse::query()->where('source_application_id', $this->hrId)->count());
    }

    public function test_the_typed_name_may_match_the_profile_or_the_application_and_the_tick_is_required(): void
    {
        $application = $this->talentApplication();

        foreach ([['signed_name' => 'Somebody Else'], ['signed_name' => 'Jane'], ['confirmed' => '0']] as $bad) {
            try {
                $this->accept($application, $bad);
                $this->fail('refused');
            } catch (OfferAnswerRejectedException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertSame(0, OfferResponse::query()->where('source_application_id', $this->hrId)->count());

        // matches the name on the HR application even when the Talent profile differs
        $this->candidate->forceFill(['full_name' => 'ZZZ Offer Different'])->save();
        $this->accept($application, ['signed_name' => '  zzz JANE   candidate ']);
        $this->assertSame(1, OfferResponse::query()->where('source_application_id', $this->hrId)->count());
    }

    public function test_declining_needs_a_reason_from_the_list_and_other_needs_text(): void
    {
        $application = $this->talentApplication();

        foreach ([['reason' => null], ['reason' => 'boredom'], ['reason' => 'other']] as $bad) {
            try {
                $this->offers()->respond($application, $this->candidate, 'decline', $bad, null, null);
                $this->fail('refused');
            } catch (OfferAnswerRejectedException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $response = $this->offers()->respond($application, $this->candidate, 'decline', ['reason' => 'location', 'reason_detail' => 'Too far'], null, null);
        $this->assertSame('decline', $response->action);
        $this->assertSame('location', $response->decline_reason);
        $this->assertSame('Too far', $response->decline_detail);
    }

    public function test_an_expired_withdrawn_or_missing_offer_cannot_be_answered(): void
    {
        $application = $this->talentApplication();

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_token_expires_at' => now()->subMinute()]);
        try {
            $this->accept($application);
            $this->fail('expired');
        } catch (OfferAnswerRejectedException $e) {
            $this->assertStringContainsString('expired', $e->getMessage());
        }

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'withdrawn']);
        try {
            $this->accept($application);
            $this->fail('withdrawn');
        } catch (OfferAnswerRejectedException $e) {
            $this->assertStringContainsString('withdrawn', $e->getMessage());
        }

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => null]);
        $this->expectException(OfferAnswerRejectedException::class);
        $this->accept($application);
    }

    public function test_the_card_tells_the_candidate_their_answer_is_being_confirmed_then_shows_the_confirmed_state(): void
    {
        $application = $this->talentApplication();
        $this->actingAs($this->candidate, 'candidate');

        $this->post(route('candidate.applications.offer.accept', $application), ['signed_name' => 'ZZZ Jane Candidate', 'confirmed' => '1'])
            ->assertRedirect(route('candidate.applications.offer', $application));
        $this->get(route('candidate.applications.offer', $application))
            ->assertOk()->assertSee('Accepted — your onboarding checklist will open in a moment.')->assertDontSee('Accept offer');

        // the HR module applies it
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'accepted', 'status' => 'hired', 'onboarding_status' => 'not_started', 'offer_response_channel' => 'talent']);
        $this->get(route('candidate.applications.offer', $application))
            ->assertOk()->assertSee('You accepted this offer')->assertSee('Complete your onboarding')->assertDontSee('Accept offer');
    }

    public function test_an_offer_accepted_by_email_shows_as_accepted_with_no_buttons(): void
    {
        $application = $this->talentApplication();
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'accepted', 'status' => 'hired', 'offer_response_channel' => 'email', 'offer_responded_at' => now()]);

        $this->actingAs($this->candidate, 'candidate')->get(route('candidate.applications.offer', $application))
            ->assertOk()->assertSee('You accepted this offer')->assertSee('by email')->assertDontSee('Accept offer')->assertDontSee('Decline this offer');
    }

    public function test_the_page_validates_the_form_and_explains_a_refusal(): void
    {
        $application = $this->talentApplication();
        $this->actingAs($this->candidate, 'candidate');

        $this->post(route('candidate.applications.offer.accept', $application), ['signed_name' => 'ZZZ Jane Candidate'])->assertSessionHasErrors('confirmed');
        $this->post(route('candidate.applications.offer.accept', $application), ['signed_name' => 'Wrong Person', 'confirmed' => '1'])->assertSessionHasErrors('signed_name');
        $this->post(route('candidate.applications.offer.decline', $application), [])->assertSessionHasErrors('reason');
        $this->post(route('candidate.applications.offer.decline', $application), ['reason' => 'other'])->assertSessionHasErrors('reason_detail');
        $this->assertSame(0, OfferResponse::query()->where('source_application_id', $this->hrId)->count());
    }

    // ---- ownership and the letter -------------------------------------------------

    public function test_only_the_owning_candidate_can_see_answer_or_open_the_letter(): void
    {
        $application = $this->talentApplication();
        $stranger = Candidate::query()->create(['full_name' => 'ZZZ Offer Stranger', 'email' => 'zzzoffer01-other@x.com', 'phone' => '+255700000009']);
        $this->actingAs($stranger, 'candidate');

        $this->get(route('candidate.applications.offer', $application))->assertForbidden();
        $this->get(route('candidate.applications.offer.letter', $application))->assertForbidden();
        $this->post(route('candidate.applications.offer.accept', $application), ['signed_name' => 'ZZZ Offer Stranger', 'confirmed' => '1'])->assertForbidden();
        $this->post(route('candidate.applications.offer.decline', $application), ['reason' => 'salary'])->assertForbidden();
        $this->assertSame(0, OfferResponse::query()->where('source_application_id', $this->hrId)->count());
    }

    public function test_the_letter_opens_through_a_temporary_signed_link_for_the_owner(): void
    {
        $application = $this->talentApplication();
        $path = 'offers/'.self::TENANT.'/'.$this->hrId.'/offer-v1.pdf';
        Storage::disk('hr_onboarding')->put($path, '%PDF-1.4 test');
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_letter_path' => $path]);

        $this->actingAs($this->candidate, 'candidate');
        $this->get(route('candidate.applications.offer', $application))->assertOk()->assertSee('View offer letter');

        $response = $this->get(route('candidate.applications.offer.letter', $application));
        $response->assertRedirect();
        $this->assertStringContainsString('signature=', $response->headers->get('Location'));
        $this->assertStringContainsString('expires=', $response->headers->get('Location'));

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_letter_path' => null]);
        $this->get(route('candidate.applications.offer.letter', $application))->assertNotFound();
    }

    // ---- claim flow ---------------------------------------------------------------

    public function test_the_claim_page_hides_everything_for_an_invalid_or_expired_link(): void
    {
        $this->get('/offers/claim/'.self::MODULE.'/'.str_repeat('z', 40))->assertOk()->assertSee('This offer link is not valid')->assertDontSee('ZZZ')->assertDontSee('•••');

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_token_expires_at' => now()->subMinute()]);
        $this->get('/offers/claim/'.self::MODULE.'/'.self::TOKEN)->assertOk()->assertSee('This offer link is not valid');
        $this->get('/offers/claim/nowhere/'.self::TOKEN)->assertNotFound();
    }

    public function test_a_careers_page_applicant_claims_the_offer_with_a_code_sent_to_the_application_phone(): void
    {
        // No Talent account yet.
        $this->candidate->delete();
        $sent = [];
        $this->mock(OtpService::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('send')->andReturnUsing(function ($to, $purpose, $email) use (&$sent) {
                $sent[] = [$to, $purpose, $email];

                return true;
            });
            $mock->shouldReceive('verify')->andReturnUsing(fn ($to, $code) => $code === '123456' ? 'success' : 'invalid_code');
        });

        $this->get('/offers/claim/'.self::MODULE.'/'.self::TOKEN)->assertOk()->assertSee('Send me a code')->assertSee('••••456')->assertDontSee('255754123456');

        $this->post('/offers/claim/'.self::MODULE.'/'.self::TOKEN.'/send')->assertRedirect();
        $this->assertSame([['255754123456', 'login', 'zzzoffer01@x.com']], $sent, 'the code goes to the phone on the application');

        $this->post('/offers/claim/'.self::MODULE.'/'.self::TOKEN.'/verify', ['code' => '000000'])->assertRedirect()->assertSessionHas('claim_error');
        $this->assertGuest('candidate');
        $this->assertSame(0, Candidate::query()->where('email', 'zzzoffer01@x.com')->count(), 'nothing is created before the code is right');

        $response = $this->post('/offers/claim/'.self::MODULE.'/'.self::TOKEN.'/verify', ['code' => '123456']);
        $candidate = Candidate::query()->where('email', 'zzzoffer01@x.com')->first();
        $this->assertNotNull($candidate, 'the Talent account is created');
        $this->assertSame('ZZZ Jane Candidate', $candidate->full_name);
        $this->assertAuthenticatedAs($candidate, 'candidate');

        $application = Application::query()->where('source_schema', self::MODULE)->where('source_application_id', $this->hrId)->first();
        $this->assertNotNull($application);
        $this->assertSame('offer_claim', $application->source_channel);
        $response->assertRedirect(route('candidate.applications.offer', $application));
        $this->assertSame((int) $application->id, (int) $this->hrRow()->talent_application_id);

        // claiming again neither duplicates the account nor the application
        $this->post('/offers/claim/'.self::MODULE.'/'.self::TOKEN.'/verify', ['code' => '123456']);
        $this->assertSame(1, Candidate::query()->where('email', 'zzzoffer01@x.com')->count());
        $this->assertSame(1, Application::query()->where('source_schema', self::MODULE)->where('source_application_id', $this->hrId)->count());
    }

    public function test_an_existing_candidate_is_matched_by_phone_and_not_duplicated(): void
    {
        $this->mock(OtpService::class, fn ($mock) => $mock->shouldReceive('verify')->andReturn('success'));
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['email' => 'someone.else@x.com']);

        $this->post('/offers/claim/'.self::MODULE.'/'.self::TOKEN.'/verify', ['code' => '123456'])->assertRedirect();

        $this->assertAuthenticatedAs($this->candidate, 'candidate');
        $this->assertSame(0, Candidate::query()->where('email', 'someone.else@x.com')->count());
    }

    public function test_a_signed_in_candidate_skips_the_code_but_only_for_their_own_offer(): void
    {
        $this->actingAs($this->candidate, 'candidate');
        $this->get('/offers/claim/'.self::MODULE.'/'.self::TOKEN)->assertRedirect();
        $this->assertSame(1, Application::query()->where('source_application_id', $this->hrId)->count());

        $stranger = Candidate::query()->create(['full_name' => 'ZZZ Offer Stranger', 'email' => 'zzzoffer01-other@x.com', 'phone' => '+255700000009']);
        $this->app['auth']->guard('candidate')->logout();
        Application::query()->where('source_application_id', $this->hrId)->delete();
        $this->actingAs($stranger, 'candidate');
        $this->get('/offers/claim/'.self::MODULE.'/'.self::TOKEN)->assertOk()->assertSee('This offer is for someone else');
        $this->assertSame(0, Application::query()->where('source_application_id', $this->hrId)->count());
    }

    // ---- notification --------------------------------------------------------------

    public function test_the_candidate_gets_an_in_app_notification_when_an_offer_arrives_once(): void
    {
        $application = $this->talentApplication();
        $application->forceFill(['last_seen_status' => 'interviewed'])->save();
        $service = $this->app->make(NotificationService::class);

        $service->syncForCandidate($this->candidate);
        $service->syncForCandidate($this->candidate);

        $notes = $this->candidate->notifications()->get();
        $this->assertCount(1, $notes);
        $this->assertStringContainsString('You have received a job offer', $notes[0]->title);
        $this->assertSame(route('candidate.applications.offer', $application), $notes[0]->action_url);
    }
}
