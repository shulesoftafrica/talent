<?php

namespace Tests\Feature\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\OnboardingSubmission;
use App\Services\Onboarding\OfferAnswerRejectedException;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\SharedEncrypter;
use App\Services\Onboarding\UploadRejectedException;
use Dotenv\Dotenv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * REQ-HRX-07: the self-onboarding checklist on Talent. Real shared Postgres; every row lives
 * under the disposable tenant 'zzzonb01' and is deleted again. Talent must write only its own
 * `onboarding_submissions` -- the HR-side rows are compared before and after.
 */
class OnboardingChecklistTest extends TestCase
{
    private const TENANT = 'zzzonb01';
    private const MODULE = 'shulesoft';

    private string $root;
    private int $jobId;
    private int $hrId;
    private Candidate $candidate;
    private Application $application;
    private string $avatar = '';

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

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'talent_onb_'.uniqid();
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

        $this->candidate = Candidate::query()->create(['full_name' => 'ZZZ Onb Candidate', 'email' => 'zzzonb01@x.com', 'phone' => '+255754123400']);
        $this->jobId = (int) $this->hr()->table('job_postings')->insertGetId([
            'title' => 'ZZZ Onb Role', 'slug' => 'zzz-onb-'.uniqid(), 'description' => 'x', 'requirements' => 'x',
            'department' => 'Finance', 'status' => 'active', 'schema_name' => self::TENANT, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hrId = (int) $this->hr()->table('applications')->insertGetId([
            'job_posting_id' => $this->jobId, 'name' => 'ZZZ Onb Candidate', 'email' => 'zzzonb01@x.com', 'phone' => '255754123400', 'status' => 'hired',
            'schema_name' => self::TENANT, 'offer_status' => 'accepted', 'offer_version' => 1, 'offer_token' => str_repeat('d', 40),
            'offer_token_expires_at' => now()->addDays(3), 'onboarding_status' => 'not_started', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->items('pending');
        $this->application = Application::query()->create([
            'candidate_id' => $this->candidate->id, 'source_schema' => self::MODULE, 'source_application_id' => $this->hrId,
            'source_job_posting_id' => $this->jobId, 'source_channel' => 'offer_claim', 'applied_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->avatar !== '') {
            Storage::disk('local')->delete($this->avatar);
        }
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
        DB::table('onboarding_submissions')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('applications')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('candidates')->where('email', 'like', 'zzzonb01%')->delete();
        foreach (['hr_onboarding_items', 'hr_audit_logs', 'applications', 'job_postings'] as $table) {
            $this->hr()->table($table)->where('schema_name', self::TENANT)->delete();
        }
    }

    /** The seven default items, as the HR module creates them. */
    private function items(string $status): void
    {
        $doc = ['application/pdf', 'image/jpeg', 'image/png'];
        $rows = [
            ['birth_certificate', 'Birth certificate', 'file', 1, 5120, $doc, true, 'Upload a clear copy of your birth certificate.'],
            ['medical_assessment', 'Medical assessment form', 'form_return', 1, 10240, $doc, true, 'Download the medical form, take it to a hospital, then upload it.'],
            ['profile_picture', 'Profile picture', 'photo', 1, 3072, ['image/jpeg', 'image/png'], true, 'A clear, front-facing photo of you.'],
            ['academic_certificates', 'Academic certificates and results', 'multi_file', 10, 5120, $doc, true, 'Upload your certificates: at least 1 and up to 10 files.'],
            ['pension_fund', 'Pension fund membership', 'pension_declaration', 1, 5120, $doc, true, 'Are you a member of a pension fund?'],
            ['identity_document', 'Identity card', 'identity_document', 2, 5120, $doc, true, 'Choose the document type and upload a copy.'],
            ['tin', 'Tax Identification Number (TIN)', 'tin', 1, 5120, $doc, true, 'Enter your 9-digit TIN.'],
        ];
        foreach ($rows as $i => [$code, $label, $type, $max, $size, $accepts, $required, $instructions]) {
            $this->hr()->table('hr_onboarding_items')->insert([
                'schema_name' => self::TENANT, 'application_id' => $this->hrId, 'requirement_code' => $code, 'label' => $label, 'instructions' => $instructions, 'type' => $type,
                'required' => DB::raw($required ? 'true' : 'false'), 'sort_order' => $i, 'accepts' => json_encode($accepts), 'max_files' => $max, 'max_size_kb' => $size,
                'status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function onboarding(): OnboardingService
    {
        return $this->app->make(OnboardingService::class);
    }

    private function itemId(string $code): int
    {
        return (int) $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->where('requirement_code', $code)->value('id');
    }

    private function hrItems(): array
    {
        return $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        $objects = ['<</Type/Catalog/Pages 2 0 R>>', '<</Type/Pages/Kids[3 0 R]/Count 1>>', '<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>'];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<</Root 1 0 R/Size ".(count($objects) + 1).">>\nstartxref\n".$xref."\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($name, $pdf);
    }

    private function png(string $name = 'me.png'): UploadedFile
    {
        $image = imagecreatetruecolor(40, 40);
        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    /** Saves every default item as a complete draft. */
    private function fillEverything(): void
    {
        $o = $this->onboarding();
        $c = $this->candidate;
        $a = $this->application;
        $o->save($a, $c, $this->itemId('birth_certificate'), [], [$this->pdf('birth.pdf')]);
        $o->save($a, $c, $this->itemId('medical_assessment'), [], [$this->pdf('medical.pdf')]);
        $o->save($a, $c, $this->itemId('profile_picture'), [], [$this->png()]);
        $o->save($a, $c, $this->itemId('academic_certificates'), [], [$this->pdf('f4.pdf'), $this->pdf('f6.pdf')], ['Form Four certificate', 'Form Six certificate']);
        $o->save($a, $c, $this->itemId('pension_fund'), ['member' => 'yes', 'fund' => 'NSSF', 'membership_number' => 'NS-1234']);
        $o->save($a, $c, $this->itemId('identity_document'), ['doc_type' => 'passport', 'number' => 'ab 123456'], [$this->pdf('id.pdf')]);
        $o->save($a, $c, $this->itemId('tin'), ['tin' => '123-456-789']);
    }

    // ---- what the new hire sees -----------------------------------------------------

    public function test_after_acceptance_is_processed_the_new_hire_sees_the_seven_items_with_instructions(): void
    {
        $this->actingAs($this->candidate, 'candidate')->get(route('candidate.onboarding', $this->application))
            ->assertOk()->assertSee('0 of 7 complete')->assertSee('Birth certificate')->assertSee('Medical assessment form')->assertSee('Profile picture')
            ->assertSee('Academic certificates and results')->assertSee('Pension fund membership')->assertSee('Identity card')->assertSee('Tax Identification Number (TIN)')
            ->assertSee('Upload a clear copy of your birth certificate.')->assertSee('Submit for review');
    }

    public function test_the_page_is_closed_until_the_offer_is_accepted_and_the_checklist_opened(): void
    {
        $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->update(['status' => 'locked']);
        $this->actingAs($this->candidate, 'candidate')->get(route('candidate.onboarding', $this->application))
            ->assertOk()->assertSee('Your onboarding checklist is opening')->assertDontSee('Birth certificate');
        $this->assertNull($this->onboarding()->overview($this->application));

        $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->update(['status' => 'pending']);
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'sent']);
        $this->get(route('candidate.onboarding', $this->application))->assertRedirect(route('candidate.applications.offer', $this->application));
    }

    public function test_mobile_friendly_inputs_are_present_camera_capture_and_phone_keypad(): void
    {
        $this->actingAs($this->candidate, 'candidate')->get(route('candidate.onboarding', $this->application))
            ->assertOk()->assertSee('capture="user"', false)->assertSee('inputmode="numeric"', false)->assertSee('accept=".jpg,.jpeg,.png"', false);
    }

    // ---- the medical form -----------------------------------------------------------

    public function test_the_medical_form_downloads_from_the_employers_template_and_the_completed_form_uploads(): void
    {
        Storage::disk('hr_onboarding')->put('templates/'.self::TENANT.'/medical.pdf', 'template');
        $id = $this->itemId('medical_assessment');
        $this->hr()->table('hr_onboarding_items')->where('id', $id)->update(['template_path' => 'templates/'.self::TENANT.'/medical.pdf']);

        $this->actingAs($this->candidate, 'candidate');
        $page = $this->get(route('candidate.onboarding', $this->application));
        $page->assertOk()->assertSee('Download the medical assessment form form')->assertSee('signed and stamped');

        $link = $this->get(route('candidate.onboarding.template', [$this->application, $id]));
        $link->assertRedirect();
        $this->assertStringContainsString('signature=', $link->headers->get('Location'));

        $this->post(route('candidate.onboarding.save', [$this->application, $id]), ['intent' => 'submit', 'files' => [$this->pdf('completed-medical.pdf')]])->assertRedirect();
        $row = OnboardingSubmission::query()->where('requirement_code', 'medical_assessment')->where('source_application_id', $this->hrId)->first();
        $this->assertSame('submitted', $row->status);
        $this->assertSame('completed-medical.pdf', $row->files[0]['original_name']);
        $this->assertStringStartsWith('onboarding/'.self::MODULE.'/'.$this->hrId.'/medical_assessment/', $row->files[0]['path']);
        $this->assertTrue(Storage::disk('hr_onboarding')->exists($row->files[0]['path']));
    }

    // ---- typed items ----------------------------------------------------------------

    public function test_pension_yes_makes_fund_and_number_required_and_no_does_not(): void
    {
        $id = $this->itemId('pension_fund');
        $o = $this->onboarding();
        $view = fn () => $o->overview($this->application)['items']->firstWhere('id', $id);

        $o->save($this->application, $this->candidate, $id, ['member' => 'yes']);
        $this->assertFalse($view()->complete, 'yes without a fund and number is not complete');

        $o->save($this->application, $this->candidate, $id, ['member' => 'yes', 'fund' => 'NSSF']);
        $this->assertFalse($view()->complete, 'a number is still needed');

        $o->save($this->application, $this->candidate, $id, ['member' => 'yes', 'fund' => 'other', 'membership_number' => 'X1']);
        $this->assertFalse($view()->complete, 'the fund name is needed for Other');

        $o->save($this->application, $this->candidate, $id, ['member' => 'yes', 'fund' => 'other', 'fund_other' => 'Local Fund', 'membership_number' => 'X1']);
        $this->assertTrue($view()->complete);

        $o->save($this->application, $this->candidate, $id, ['member' => 'no']);
        $this->assertTrue($view()->complete, 'no membership needs nothing else');
        $this->assertSame(['member' => false], $view()->data, 'the hidden fund fields are not kept');

        $this->expectException(ValidationException::class);
        $o->save($this->application, $this->candidate, $id, ['member' => 'yes', 'fund' => 'BOGUS']);
    }

    public function test_identity_and_tin_values_are_stored_encrypted_and_validated(): void
    {
        $o = $this->onboarding();
        $tin = $this->itemId('tin');
        $identity = $this->itemId('identity_document');

        $o->save($this->application, $this->candidate, $tin, ['tin' => '123-456-789']);
        $o->save($this->application, $this->candidate, $identity, ['doc_type' => 'passport', 'number' => 'ab 123456'], [$this->pdf('id.pdf')]);

        foreach (['tin' => '123456789', 'identity_document' => 'AB123456'] as $code => $secret) {
            $raw = (string) OnboardingSubmission::query()->where('requirement_code', $code)->where('source_application_id', $this->hrId)->value('data');
            $this->assertNotSame('', $raw);
            $this->assertStringNotContainsString($secret, $raw, "{$code} is ciphertext");
            $this->assertStringContainsString($secret, json_encode(SharedEncrypter::fromConfig()->decryptString($raw)) ?: '', 'and decrypts with the shared key');
        }
        $data = $o->overview($this->application)['items']->firstWhere('id', $tin)->data;
        $this->assertSame(['tin' => '123456789'], $data, 'stored without separators');

        foreach ([['8 digits' => '12345678'], ['letters' => '12345678A'], ['10 digits' => '1234567890']] as $case) {
            try {
                $o->save($this->application, $this->candidate, $tin, ['tin' => reset($case)]);
                $this->fail(key($case).' must be refused');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('9 digits', $e->errors()["item_{$tin}"][0]);
            }
        }

        try {
            $o->save($this->application, $this->candidate, $identity, ['doc_type' => 'national_id', 'number' => '1234']);
            $this->fail('a NIDA number has 20 digits');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('20 digits', $e->errors()["item_{$identity}"][0]);
        }
        $o->save($this->application, $this->candidate, $identity, ['doc_type' => 'national_id', 'number' => '19900101-12345-00001-23']);
        $this->assertSame('19900101123450000123', $o->overview($this->application)['items']->firstWhere('id', $identity)->data['number']);
    }

    public function test_saving_files_alone_never_wipes_the_typed_values(): void
    {
        $o = $this->onboarding();
        $id = $this->itemId('identity_document');
        $o->save($this->application, $this->candidate, $id, ['doc_type' => 'passport', 'number' => 'AB123456'], [$this->pdf('front.pdf')]);
        $file = $o->overview($this->application)['items']->firstWhere('id', $id)->files[0];

        $o->save($this->application, $this->candidate, $id, [], [$this->pdf('back.pdf')]);
        $this->assertSame(['doc_type' => 'passport', 'number' => 'AB123456'], $o->overview($this->application)['items']->firstWhere('id', $id)->data);

        $o->save($this->application, $this->candidate, $id, [], [], [], [$file['id']]);
        $after = $o->overview($this->application)['items']->firstWhere('id', $id);
        $this->assertCount(1, $after->files);
        $this->assertFalse(Storage::disk('hr_onboarding')->exists($file['path']), 'a removed file is deleted from the bucket');
        $this->assertSame('AB123456', $after->data['number']);

        $this->expectException(ValidationException::class);
        $o->save($this->application, $this->candidate, $id, [], [$this->pdf('c.pdf'), $this->pdf('d.pdf')]);
    }

    // ---- files ----------------------------------------------------------------------

    public function test_multiple_certificates_carry_labels_and_a_photo_is_replaced_not_added(): void
    {
        $o = $this->onboarding();
        $certs = $this->itemId('academic_certificates');
        $o->save($this->application, $this->candidate, $certs, [], [$this->pdf('a.pdf'), $this->pdf('b.pdf')], ['Form Four certificate', 'Form Six certificate']);
        $files = $o->overview($this->application)['items']->firstWhere('id', $certs)->files;
        $this->assertSame(['Form Four certificate', 'Form Six certificate'], array_column($files, 'label'));

        $photo = $this->itemId('profile_picture');
        $o->save($this->application, $this->candidate, $photo, [], [$this->png('one.png')]);
        $first = $o->overview($this->application)['items']->firstWhere('id', $photo)->files[0];
        $o->save($this->application, $this->candidate, $photo, [], [$this->png('two.png')]);
        $now = $o->overview($this->application)['items']->firstWhere('id', $photo)->files;
        $this->assertCount(1, $now);
        $this->assertSame('two.png', $now[0]['original_name']);
        $this->assertFalse(Storage::disk('hr_onboarding')->exists($first['path']));
    }

    public function test_a_disallowed_file_is_rejected_and_nothing_is_saved(): void
    {
        $id = $this->itemId('birth_certificate');
        try {
            $this->onboarding()->save($this->application, $this->candidate, $id, [], [UploadedFile::fake()->createWithContent('run.php', '<?php echo 1;')]);
            $this->fail('a script must not be accepted');
        } catch (UploadRejectedException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
        $this->assertSame(0, OnboardingSubmission::query()->where('source_application_id', $this->hrId)->count());
    }

    // ---- submitting -----------------------------------------------------------------

    public function test_submit_for_review_stays_disabled_until_every_required_item_is_complete(): void
    {
        $this->actingAs($this->candidate, 'candidate');
        $this->assertFalse($this->onboarding()->overview($this->application)['can_submit_all']);
        $this->get(route('candidate.onboarding', $this->application))->assertOk()->assertSee('Available when every required item is complete.');

        $this->post(route('candidate.onboarding.submit-all', $this->application))->assertSessionHasErrors('submit_all');
        $this->assertSame(0, OnboardingSubmission::query()->where('source_application_id', $this->hrId)->where('status', 'submitted')->count());

        $o = $this->onboarding();
        $o->save($this->application, $this->candidate, $this->itemId('birth_certificate'), [], [$this->pdf()]);
        $this->assertFalse($o->overview($this->application)['can_submit_all'], 'one item is not enough');

        $this->fillEverything();
        $overview = $o->overview($this->application);
        $this->assertSame(7, $overview['done']);
        $this->assertTrue($overview['can_submit_all']);
        $this->get(route('candidate.onboarding', $this->application))->assertOk()->assertSee('7 of 7 complete')->assertDontSee('Available when every required item is complete.');
    }

    public function test_submitting_sends_every_saved_item_and_makes_them_read_only(): void
    {
        $this->fillEverything();
        $hrBefore = $this->hrItems();

        $this->actingAs($this->candidate, 'candidate')->post(route('candidate.onboarding.submit-all', $this->application))->assertRedirect(route('candidate.onboarding', $this->application));

        $rows = OnboardingSubmission::query()->where('source_application_id', $this->hrId)->get();
        $this->assertCount(7, $rows);
        $this->assertSame(['submitted'], $rows->pluck('status')->unique()->values()->all());
        $this->assertSame([1], $rows->pluck('version')->unique()->values()->all());
        $this->assertNotNull($rows->first()->submitted_at);
        $this->assertNull($rows->first()->synced_at, 'the HR module marks it synced');

        $this->assertEquals($hrBefore, $this->hrItems(), 'Talent wrote nothing into the HR schema');

        // read-only now
        $this->expectException(OfferAnswerRejectedException::class);
        $this->onboarding()->save($this->application, $this->candidate, $this->itemId('tin'), ['tin' => '987654321']);
    }

    public function test_an_item_can_be_submitted_on_its_own_and_an_incomplete_one_is_refused(): void
    {
        $o = $this->onboarding();
        $tin = $this->itemId('tin');

        try {
            $o->submitItem($this->application, $this->candidate, $this->itemId('pension_fund'), ['member' => 'yes']);
            $this->fail('an incomplete item cannot be submitted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not complete', $e->errors()['item_'.$this->itemId('pension_fund')][0]);
        }

        $o->submitItem($this->application, $this->candidate, $tin, ['tin' => '123456789']);
        $this->assertSame('submitted', OnboardingSubmission::query()->where('requirement_code', 'tin')->where('source_application_id', $this->hrId)->value('status'));
        $this->assertSame('submitted', $o->overview($this->application)['items']->firstWhere('id', $tin)->display);
    }

    public function test_a_returned_item_reopens_with_the_reason_and_resubmitting_raises_the_version(): void
    {
        $o = $this->onboarding();
        $tin = $this->itemId('tin');
        $o->submitItem($this->application, $this->candidate, $tin, ['tin' => '123456789']);

        // the HR module marks it submitted, then the reviewer returns it
        $this->hr()->table('hr_onboarding_items')->where('id', $tin)->update(['status' => 'returned', 'review_note' => 'TIN does not match certificate']);

        $item = $o->overview($this->application)['items']->firstWhere('id', $tin);
        $this->assertSame('returned', $item->display);
        $this->assertTrue($item->editable);
        $this->actingAs($this->candidate, 'candidate')->get(route('candidate.onboarding', $this->application))
            ->assertOk()->assertSee('The school sent this back: TIN does not match certificate');

        $o->submitItem($this->application, $this->candidate, $tin, ['tin' => '987654321']);
        $row = OnboardingSubmission::query()->where('requirement_code', 'tin')->where('source_application_id', $this->hrId)->first();
        $this->assertSame(2, (int) $row->version);
        $this->assertSame('submitted', $row->status);
        $this->assertSame(['tin' => '987654321'], SharedEncrypter::fromConfig()->decryptJson($row->data));

        // once HR approves it, it is final
        $this->hr()->table('hr_onboarding_items')->where('id', $tin)->update(['status' => 'approved']);
        $this->expectException(OfferAnswerRejectedException::class);
        $o->save($this->application, $this->candidate, $tin, ['tin' => '111111111']);
    }

    // ---- ownership ------------------------------------------------------------------

    public function test_another_candidate_cannot_open_or_change_someone_elses_checklist(): void
    {
        Storage::disk('hr_onboarding')->put('templates/'.self::TENANT.'/t.pdf', 'x');
        $id = $this->itemId('medical_assessment');
        $this->hr()->table('hr_onboarding_items')->where('id', $id)->update(['template_path' => 'templates/'.self::TENANT.'/t.pdf']);
        $stranger = Candidate::query()->create(['full_name' => 'ZZZ Onb Stranger', 'email' => 'zzzonb01-other@x.com', 'phone' => '+255700000055']);
        $this->actingAs($stranger, 'candidate');

        $this->get(route('candidate.onboarding', $this->application))->assertForbidden();
        $this->post(route('candidate.onboarding.save', [$this->application, $id]), ['intent' => 'save'])->assertForbidden();
        $this->post(route('candidate.onboarding.submit-all', $this->application))->assertForbidden();
        $this->post(route('candidate.onboarding.remove-file', [$this->application, $id, 'x']))->assertForbidden();
        $this->get(route('candidate.onboarding.template', [$this->application, $id]))->assertForbidden();
        $this->assertSame(0, OnboardingSubmission::query()->where('source_application_id', $this->hrId)->count());
    }

    public function test_uploads_are_throttled_and_no_value_travels_in_a_url(): void
    {
        $routes = Route::getRoutes();
        $this->assertContains('throttle:30,1', $routes->getByName('candidate.onboarding.save')->gatherMiddleware());
        $this->assertContains('throttle:10,1', $routes->getByName('candidate.onboarding.submit-all')->gatherMiddleware());

        foreach (['candidate.onboarding.save', 'candidate.onboarding.submit-all', 'candidate.onboarding.verified-id'] as $name) {
            $this->assertSame(['POST'], array_values(array_diff($routes->getByName($name)->methods(), ['HEAD'])), "{$name} is POST only, so its data is in the body");
        }
    }

    // ---- reusing what the candidate already has --------------------------------------

    public function test_the_profile_photo_can_be_reused_and_a_missing_verified_id_is_refused(): void
    {
        $o = $this->onboarding();
        $photo = $this->itemId('profile_picture');

        try {
            $o->useProfilePhoto($this->application, $this->candidate, $photo);
            $this->fail('there is no profile photo yet');
        } catch (OfferAnswerRejectedException $e) {
            $this->assertStringContainsString('profile photo', $e->getMessage());
        }
        try {
            $o->useVerifiedId($this->application, $this->candidate, $this->itemId('identity_document'));
            $this->fail('there is no verified ID');
        } catch (OfferAnswerRejectedException $e) {
            $this->assertStringContainsString('verified', $e->getMessage());
        }

        $this->avatar = 'zzz-avatars/'.uniqid().'.png';
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($image);
        Storage::disk('local')->put($this->avatar, (string) ob_get_clean());
        $this->candidate->forceFill(['avatar_path' => $this->avatar])->save();

        $o->useProfilePhoto($this->application, $this->candidate, $photo);
        $files = $o->overview($this->application)['items']->firstWhere('id', $photo)->files;
        $this->assertCount(1, $files);
        $this->assertSame('profile_photo', $files[0]['source']);
        $this->assertTrue(Storage::disk('hr_onboarding')->exists($files[0]['path']));
    }
    public function test_a_banner_points_to_the_offer_then_the_onboarding_until_everything_is_submitted(): void
    {
        $offers = $this->app->make(\App\Services\Onboarding\OfferService::class);

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'sent']);
        $banner = $offers->pendingFor($this->candidate);
        $this->assertCount(1, $banner);
        $this->assertSame('You have a job offer', $banner[0]['title']);
        $this->assertSame(route('candidate.applications.offer', $this->application), $banner[0]['url']);

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'accepted']);
        $banner = $offers->pendingFor($this->candidate);
        $this->assertSame('Complete your onboarding', $banner[0]['title']);
        $this->assertSame(route('candidate.onboarding', $this->application), $banner[0]['url']);
        $this->actingAs($this->candidate, 'candidate')->get(route('candidate.applications.index'))->assertOk()->assertSee('Complete your onboarding');

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['onboarding_status' => 'submitted']);
        $this->assertSame([], $offers->pendingFor($this->candidate), 'nothing waiting once it is all submitted');
    }
}
