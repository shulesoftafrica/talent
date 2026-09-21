<?php

namespace Tests\Feature\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use App\Services\Onboarding\HrOnboardingGateway;
use App\Services\Onboarding\OfferAnswerRejectedException;
use App\Services\Onboarding\SharedEncrypter;
use App\Services\Onboarding\UploadRejectedException;
use Dotenv\Dotenv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REQ-HRX-03/04 (Talent side): the candidate's answer to an offer and the
 * onboarding submissions, against the real shared Postgres database. Every
 * row lives under the disposable tenant 'zzztalent01' and is deleted again.
 */
class HrOnboardingGatewayTest extends TestCase
{
    private const TENANT = 'zzztalent01';
    private const MODULE = 'shulesoft';

    private string $root;
    private int $jobId;
    private int $hrId;
    private Candidate $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml points the default database at sqlite memory; these tests need the real shared Postgres.
        $env = Dotenv::parse((string) file_get_contents(base_path('.env')));
        foreach (config('database.connections') as $name => $definition) {
            if (($definition['driver'] ?? null) === 'pgsql') {
                config(["database.connections.{$name}.database" => $env['DB_DATABASE']]);
                DB::purge($name);
            }
        }
        config(['database.default' => 'pgsql']);

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'talent_gateway_'.uniqid();
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

        $this->candidate = Candidate::query()->create(['full_name' => 'ZZZ Jane Candidate', 'email' => 'zzztalent01@x.com', 'phone' => '+255754123456']);
        $this->jobId = (int) DB::connection(self::MODULE)->table('job_postings')->insertGetId([
            'title' => 'ZZZ Accountant', 'slug' => 'zzz-talent-'.uniqid(), 'description' => 'x', 'requirements' => 'x',
            'department' => 'Finance', 'status' => 'active', 'schema_name' => self::TENANT, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hrId = $this->hrApplication();
        $this->items();
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
        DB::table('applications')->where('source_schema', self::MODULE)->whereIn('source_application_id', $ids)->delete();
        DB::table('candidates')->where('email', 'like', 'zzztalent01%')->delete();
        foreach (['hr_onboarding_submissions', 'hr_onboarding_items', 'hr_audit_logs', 'applications', 'job_postings'] as $table) {
            $this->hr()->table($table)->where('schema_name', self::TENANT)->delete();
        }
    }

    private function hrApplication(array $overrides = []): int
    {
        return (int) $this->hr()->table('applications')->insertGetId(array_merge([
            'job_posting_id' => $this->jobId, 'name' => 'ZZZ Jane Candidate', 'email' => 'zzztalent01@x.com', 'phone' => '255754123456',
            'status' => 'offer', 'schema_name' => self::TENANT, 'offer_status' => 'sent', 'offer_version' => 1,
            'offer_token' => str_repeat('a', 40), 'offer_token_expires_at' => now()->addDays(7), 'offer_salary' => 900000,
            'onboarding_status' => 'locked', 'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    private function items(): void
    {
        $rows = [
            ['birth_certificate', 'Birth certificate', 'file', true, 1, ['application/pdf', 'image/jpeg', 'image/png']],
            ['pension_fund', 'Pension fund membership', 'pension_declaration', true, 1, ['application/pdf']],
            ['identity_document', 'Identity card', 'identity_document', true, 2, ['application/pdf', 'image/png']],
            ['tin', 'TIN', 'tin', false, 1, ['application/pdf']],
        ];
        foreach ($rows as $i => [$code, $label, $type, $required, $max, $accepts]) {
            $this->hr()->table('hr_onboarding_items')->insert([
                'schema_name' => self::TENANT, 'application_id' => $this->hrId, 'requirement_code' => $code, 'label' => $label, 'type' => $type,
                'required' => $required ? DB::raw('true') : DB::raw('false'), 'sort_order' => $i, 'accepts' => json_encode($accepts), 'max_files' => $max,
                'max_size_kb' => 5120, 'status' => 'locked', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function gateway(): HrOnboardingGateway
    {
        return $this->app->make(HrOnboardingGateway::class);
    }

    private function talentApplication(): Application
    {
        return $this->gateway()->claim($this->candidate, self::MODULE, str_repeat('a', 40));
    }

    private function row(string $table = 'applications', ?int $id = null): object
    {
        return $this->hr()->table($table)->find($id ?? $this->hrId);
    }

    private function item(string $code): object
    {
        return $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->where('requirement_code', $code)->first();
    }

    private function accepted(): Application
    {
        $application = $this->talentApplication();
        // The HR module applies an acceptance; simulate its result.
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => 'accepted', 'status' => 'hired', 'onboarding_status' => 'not_started']);
        $this->hr()->table('hr_onboarding_items')->where('application_id', $this->hrId)->where('status', 'locked')->update(['status' => 'pending']);

        return $application;
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

    // ---- linking the candidate to the offer -----------------------------------------

    public function test_the_offer_link_finds_the_person_it_was_made_to_and_links_the_two_accounts(): void
    {
        $application = $this->talentApplication();

        $this->assertNotNull($application);
        $this->assertSame($this->candidate->id, $application->candidate_id);
        $this->assertSame($this->hrId, (int) $application->source_application_id);
        $this->assertSame((int) $application->id, (int) $this->row()->talent_application_id, 'HR now knows the Talent application');
        $this->assertSame($application->id, $this->talentApplication()->id, 'claiming again finds the same one');
    }

    public function test_someone_else_cannot_claim_the_offer(): void
    {
        $stranger = Candidate::query()->create(['full_name' => 'ZZZ Stranger', 'email' => 'zzztalent01-other@x.com', 'phone' => '+255700000001']);

        $this->assertNull($this->gateway()->claim($stranger, self::MODULE, str_repeat('a', 40)));
        $this->assertNull($this->gateway()->claim($this->candidate, self::MODULE, str_repeat('b', 40)), 'an unknown token');
        $this->assertSame(0, DB::table('applications')->where('source_application_id', $this->hrId)->count());
    }

    public function test_the_candidate_is_matched_by_phone_when_the_email_differs(): void
    {
        $this->hr()->table('applications')->where('id', $this->hrId)->update(['email' => 'someone.else@x.com']);

        $this->assertNotNull($this->talentApplication(), 'the last nine digits of the phone match');
    }

    // ---- onboarding submissions -----------------------------------------------------

    public function test_nothing_can_be_submitted_before_the_offer_is_accepted(): void
    {
        $application = $this->talentApplication();
        $item = $this->gateway()->item($application, $this->item('birth_certificate')->id);

        $this->expectException(OfferAnswerRejectedException::class);
        $this->gateway()->submit($application, $item, [$this->pdf()], []);
    }

    public function test_a_file_is_stored_privately_and_the_item_moves_to_submitted(): void
    {
        $application = $this->accepted();
        $item = $this->gateway()->item($application, $this->item('birth_certificate')->id);

        $this->gateway()->submit($application, $item, [$this->pdf('birth.pdf')], [], '10.0.0.9', 'TestBrowser');

        $stored = $this->hr()->table('hr_onboarding_submissions')->where('item_id', $item->id)->first();
        $this->assertStringStartsWith('onboarding/'.self::MODULE.'/'.$this->hrId.'/birth_certificate/', $stored->file_path);
        $this->assertSame('birth.pdf', $stored->original_name);
        $this->assertSame('application/pdf', $stored->mime);
        $this->assertTrue(Storage::disk('hr_onboarding')->exists($stored->file_path));
        $this->assertSame(hash('sha256', Storage::disk('hr_onboarding')->get($stored->file_path)), $stored->sha256);
        $this->assertSame($this->hrId, (int) $stored->application_id);
        $this->assertSame(self::TENANT, $stored->schema_name);

        $this->assertSame('submitted', $this->item('birth_certificate')->status);
        $this->assertNotNull($this->item('birth_certificate')->submitted_at);
        $this->assertSame('in_progress', $this->row()->onboarding_status);
    }

    public function test_a_disallowed_file_type_is_rejected_and_changes_nothing(): void
    {
        $application = $this->accepted();
        $item = $this->gateway()->item($application, $this->item('birth_certificate')->id);

        try {
            $this->gateway()->submit($application, $item, [UploadedFile::fake()->createWithContent('run.php', '<?php echo 1;')], []);
            $this->fail('a script must not be accepted');
        } catch (UploadRejectedException $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame('pending', $this->item('birth_certificate')->status);
        $this->assertSame(0, $this->hr()->table('hr_onboarding_submissions')->where('application_id', $this->hrId)->count());
    }

    public function test_typed_answers_are_stored_encrypted_and_read_back(): void
    {
        $application = $this->accepted();
        $item = $this->gateway()->item($application, $this->item('pension_fund')->id);

        $this->gateway()->submit($application, $item, [], ['member' => true, 'fund' => 'NSSF', 'membership_number' => 'NS-99887766']);

        $raw = $this->hr()->table('hr_onboarding_submissions')->where('item_id', $item->id)->value('value_encrypted');
        $this->assertStringNotContainsString('NS-99887766', $raw);
        $this->assertStringNotContainsString('NSSF', $raw);
        $back = $this->gateway()->item($application, $item->id);
        $this->assertSame(['member' => true, 'fund' => 'NSSF', 'membership_number' => 'NS-99887766'], $back->values);
        $this->assertSame('submitted', $back->status);
    }

    public function test_replacing_files_or_answers_keeps_the_other_and_keeps_the_history(): void
    {
        $application = $this->accepted();
        $item = $this->gateway()->item($application, $this->item('identity_document')->id);

        $this->gateway()->submit($application, $item, [$this->pdf('id-front.pdf')], ['doc_type' => 'passport', 'number' => 'AB123456']);
        $item = $this->gateway()->item($application, $item->id);

        // change only the number: the uploaded file stays current
        $this->gateway()->submit($application, $item, [], ['doc_type' => 'passport', 'number' => 'AB999999']);
        $item = $this->gateway()->item($application, $item->id);
        $this->assertCount(1, $item->files);
        $this->assertSame('id-front.pdf', $item->files[0]->original_name);
        $this->assertSame('AB999999', $item->values['number']);

        // replace only the file: the answer stays current, the old file row is kept as history
        $this->gateway()->submit($application, $item, [$this->pdf('id-new.pdf')], []);
        $item = $this->gateway()->item($application, $item->id);
        $this->assertSame('id-new.pdf', $item->files[0]->original_name);
        $this->assertSame('AB999999', $item->values['number']);
        $this->assertSame(1, $this->hr()->table('hr_onboarding_submissions')->where('item_id', $item->id)->whereNotNull('superseded_at')->whereNotNull('file_path')->count());
    }

    public function test_an_approved_item_can_no_longer_be_changed_but_a_returned_one_can(): void
    {
        $application = $this->accepted();
        $item = $this->gateway()->item($application, $this->item('birth_certificate')->id);
        $this->gateway()->submit($application, $item, [$this->pdf()], []);

        $this->hr()->table('hr_onboarding_items')->where('id', $item->id)->update(['status' => 'approved']);
        try {
            $this->gateway()->submit($application, $this->gateway()->item($application, $item->id), [$this->pdf()], []);
            $this->fail('approved items are final');
        } catch (OfferAnswerRejectedException $e) {
            $this->assertStringContainsString('approved', $e->getMessage());
        }

        $this->hr()->table('hr_onboarding_items')->where('id', $item->id)->update(['status' => 'returned', 'review_note' => 'Blurry']);
        $this->gateway()->submit($application, $this->gateway()->item($application, $item->id), [$this->pdf()], []);
        $back = $this->item('birth_certificate');
        $this->assertSame('submitted', $back->status);
        $this->assertNull($back->review_note, 'the return note is cleared when the candidate resends');
    }

    public function test_the_state_becomes_submitted_when_every_required_item_is_handed_in(): void
    {
        $application = $this->accepted();
        $g = $this->gateway();

        $g->submit($application, $g->item($application, $this->item('birth_certificate')->id), [$this->pdf()], []);
        $g->submit($application, $g->item($application, $this->item('pension_fund')->id), [], ['member' => false]);
        $this->assertSame('in_progress', $this->row()->onboarding_status);

        $g->submit($application, $g->item($application, $this->item('identity_document')->id), [$this->pdf()], ['doc_type' => 'national_id', 'number' => '1990010112345']);
        $this->assertSame('submitted', $this->row()->onboarding_status, 'the optional TIN does not hold it back');
    }

    public function test_the_offer_appears_only_where_the_school_has_made_one(): void
    {
        $application = $this->talentApplication();
        $this->assertTrue($this->gateway()->hasOffer($application));

        $this->hr()->table('applications')->where('id', $this->hrId)->update(['offer_status' => null]);
        $this->assertFalse($this->gateway()->hasOffer($application));
    }
}
