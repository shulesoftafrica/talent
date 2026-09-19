<?php

namespace Tests\Feature\Onboarding;

use App\Services\Onboarding\OnboardingVault;
use App\Services\Onboarding\SharedEncrypter;
use App\Services\Onboarding\UploadRejectedException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REQ-HRX-10 (Talent side). Uses a temporary private local disk; never
 * touches the database.
 */
class OnboardingVaultTest extends TestCase
{
    private string $root;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'talent_vault_'.uniqid();
        File::ensureDirectoryExists($this->root);
        $this->key = 'base64:'.base64_encode(random_bytes(32));

        config([
            'hr_onboarding.enabled' => true,
            'hr_onboarding.disk' => 'hr_onboarding',
            'hr_onboarding.encryption_key' => $this->key,
            'filesystems.disks.hr_onboarding' => [
                'driver' => 'local', 'root' => $this->root, 'serve' => true, 'url' => 'http://localhost/private-onboarding',
                'visibility' => 'private', 'throw' => true, 'report' => false,
            ],
        ]);
        Storage::forgetDisk('hr_onboarding');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function vault(): OnboardingVault
    {
        return $this->app->make(OnboardingVault::class);
    }

    /** A minimal but structurally valid PDF (real xref table) so the parser accepts it. */
    private function buildPdf(string $pageExtra = ''): string
    {
        $objects = [
            '<</Type/Catalog/Pages 2 0 R>>',
            '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            '<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]'.$pageExtra.'>>',
        ];

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

        return $pdf."trailer\n<</Root 1 0 R/Size ".(count($objects) + 1).">>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function cleanPdf(): string
    {
        return $this->buildPdf();
    }

    private function pdfWithJavascript(): string
    {
        return $this->buildPdf('/AA<</O<</S/JavaScript/JS(app.alert(1))>>>>');
    }

    private function jpegWithMetadata(): string
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        // Inject an EXIF-style APP1 segment right after the SOI marker.
        $payload = "Exif\0\0GPSLATITUDE-SECRET";
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    private function upload(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_the_feature_is_off_unless_switch_storage_and_key_are_all_valid(): void
    {
        $evaluate = function (array $env): bool {
            $names = ['HR_ONBOARDING_ENABLED', 'HR_ONBOARDING_DISK_DRIVER', 'HR_ONBOARDING_DISK_KEY', 'HR_ONBOARDING_DISK_SECRET',
                'HR_ONBOARDING_DISK_REGION', 'HR_ONBOARDING_DISK_BUCKET', 'HR_ONBOARDING_LOCAL_ROOT', 'HR_ONBOARDING_ENCRYPTION_KEY', 'APP_ENV'];
            $before = [];
            foreach ($names as $name) {
                $before[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
            }
            foreach ($env as $name => $value) {
                $_ENV[$name] = $_SERVER[$name] = $value;
                putenv("$name=$value");
            }
            try {
                return (bool) (require config_path('hr_onboarding.php'))['enabled'];
            } finally {
                foreach ($names as $name) {
                    unset($_ENV[$name], $_SERVER[$name]);
                    putenv($name);
                    [$e, $s, $g] = $before[$name];
                    if ($e !== null) { $_ENV[$name] = $e; }
                    if ($s !== null) { $_SERVER[$name] = $s; }
                    if ($g !== false && $g !== null) { putenv("$name=$g"); }
                }
            }
        };

        $good = ['HR_ONBOARDING_ENABLED' => 'true', 'HR_ONBOARDING_DISK_DRIVER' => 'local', 'HR_ONBOARDING_LOCAL_ROOT' => $this->root,
            'HR_ONBOARDING_ENCRYPTION_KEY' => $this->key, 'APP_ENV' => 'testing'];

        $this->assertTrue($evaluate($good));
        $this->assertFalse($evaluate([...$good, 'HR_ONBOARDING_ENABLED' => 'false']), 'switch off');
        $this->assertFalse($evaluate([...$good, 'HR_ONBOARDING_ENCRYPTION_KEY' => '']), 'no key');
        $this->assertFalse($evaluate([...$good, 'HR_ONBOARDING_ENCRYPTION_KEY' => 'base64:'.base64_encode('short')]), 'bad key');
        $this->assertFalse($evaluate([...$good, 'APP_ENV' => 'production']), 'local disk must be refused in production');
        $this->assertFalse($evaluate([...$good, 'HR_ONBOARDING_DISK_DRIVER' => 's3']), 's3 without bucket settings');
    }

    public function test_a_pdf_containing_javascript_is_rejected_and_nothing_is_stored(): void
    {
        $path = $this->vault()->pathFor('shulesoft', 5, 'birth_certificate', 'pdf');

        try {
            $this->vault()->store($this->upload('cert.pdf', $this->pdfWithJavascript()), $path, 5 * 1048576, ['application/pdf']);
            $this->fail('a PDF with JavaScript must be rejected');
        } catch (UploadRejectedException $e) {
            $this->assertStringContainsString('security', strtolower($e->getMessage()));
        }

        $this->assertFalse(Storage::disk('hr_onboarding')->exists($path));
    }

    public function test_a_clean_pdf_is_stored_privately_with_a_matching_sha256(): void
    {
        $content = $this->cleanPdf();
        $path = $this->vault()->pathFor('safaribook', 9, 'birth_certificate', 'pdf');

        $meta = $this->vault()->store($this->upload('birth.pdf', $content), $path, 5 * 1048576, ['application/pdf']);

        $this->assertSame($path, $meta['path']);
        $this->assertSame('application/pdf', $meta['mime']);
        $this->assertSame(hash('sha256', Storage::disk('hr_onboarding')->get($path)), $meta['sha256']);
        $this->assertMatchesRegularExpression('#^onboarding/safaribook/9/birth_certificate/[0-9a-f-]{36}\.pdf$#', $path);
    }

    public function test_oversize_and_wrong_type_uploads_are_rejected(): void
    {
        $path = $this->vault()->pathFor('shulesoft', 5, 'tin', 'pdf');

        $this->expectException(UploadRejectedException::class);
        $this->vault()->store($this->upload('big.pdf', $this->cleanPdf()), $path, 10, ['application/pdf']);
    }

    public function test_a_file_type_not_allowed_for_the_item_is_rejected(): void
    {
        $path = $this->vault()->pathFor('shulesoft', 5, 'tin', 'txt');

        $this->expectException(UploadRejectedException::class);
        $this->vault()->store($this->upload('notes.txt', 'just text'), $path, 1048576, ['application/pdf', 'image/jpeg']);
    }

    public function test_image_metadata_is_stripped_when_stored(): void
    {
        $original = $this->jpegWithMetadata();
        $this->assertStringContainsString('GPSLATITUDE-SECRET', $original);

        $path = $this->vault()->pathFor('shulesoft', 5, 'profile_picture', 'jpg');
        $meta = $this->vault()->store($this->upload('me.jpg', $original), $path, 3 * 1048576, ['image/jpeg', 'image/png']);

        $stored = Storage::disk('hr_onboarding')->get($path);
        $this->assertStringNotContainsString('GPSLATITUDE-SECRET', $stored);
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertNotFalse(@imagecreatefromstring($stored), 'the re-encoded image must still open');
        $this->assertSame(hash('sha256', $stored), $meta['sha256']);
    }

    public function test_temporary_url_is_signed_and_expires_after_ten_minutes(): void
    {
        $path = $this->vault()->pathFor('shulesoft', 5, 'tin', 'pdf');
        $this->vault()->store($this->upload('tin.pdf', $this->cleanPdf()), $path, 1048576, ['application/pdf']);

        $url = $this->vault()->temporaryUrl($path);
        $this->assertStringContainsString('signature=', $url);
        $this->assertTrue(Request::create($url)->hasValidRelativeSignature());

        Carbon::setTestNow(now()->addMinutes(9));
        $this->assertTrue(Request::create($url)->hasValidRelativeSignature());

        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertFalse(Request::create($url)->hasValidRelativeSignature(), 'must stop working after 10 minutes');
    }

    public function test_the_private_disk_has_no_public_url(): void
    {
        $this->assertSame('private', config('filesystems.disks.hr_onboarding.visibility'));

        $path = $this->vault()->pathFor('shulesoft', 5, 'tin', 'pdf');
        $this->vault()->store($this->upload('tin.pdf', $this->cleanPdf()), $path, 1048576, ['application/pdf']);

        // The unsigned route the framework registers for served local disks must refuse the file.
        $this->get('/private-onboarding/'.$path)->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafePaths')]
    public function test_paths_outside_the_known_folders_are_refused(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->vault()->temporaryUrl($path);
    }

    public static function unsafePaths(): array
    {
        return [['../secret.pdf'], ['onboarding/../../x'], ['other/1/x.pdf'], ['onboarding//x'], ['']];
    }

    public function test_unknown_module_or_requirement_code_cannot_build_a_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->vault()->pathFor('production', 1, 'tin', 'pdf');
    }

    public function test_the_vault_refuses_to_run_while_the_feature_is_off(): void
    {
        config(['hr_onboarding.enabled' => false]);

        $this->expectException(\RuntimeException::class);
        $this->vault()->disk();
    }

    public function test_shared_encrypter_round_trips_hides_the_value_and_fails_with_a_wrong_key(): void
    {
        $plain = ['tin' => '123456789', 'id_number' => '19900101123450000123'];
        $cipher = (new SharedEncrypter($this->key))->encryptJson($plain);

        $this->assertStringNotContainsString('123456789', $cipher);
        $this->assertSame($plain, SharedEncrypter::fromConfig()->decryptJson($cipher));

        $this->expectException(DecryptException::class);
        (new SharedEncrypter('base64:'.base64_encode(random_bytes(32))))->decryptJson($cipher);
    }

    public function test_a_value_encrypted_by_the_hr_app_is_decrypted_here(): void
    {
        // Produced by the HR app's own SharedEncrypter with this fixed test key.
        $key = 'base64:S0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0s=';
        $fromHrApp = 'eyJpdiI6IlJIZE1iazdtandZajRDTURjOHlvVGc9PSIsInZhbHVlIjoiY2F5blRNSTY1MUVLNTE1MFMrOGxDWTQ1aVFoZDA3WE5xOGppVHlkZ0xrTENXUkR0TGlJSE9GNGIxRUNua1JlQXUvU0poc1ROZy9iUjlyTW5pSWNlUXc9PSIsIm1hYyI6IjQ5MzA3MGZjMWM2NzI0YjdhNWI4YTIxNmIyYWIwNGE5NDI5YjlhOTg5ZDkyYWUwNWZlMzE1MTg5N2MzOGY4ZmQiLCJ0YWciOiIifQ==';

        $this->assertSame(
            ['tin' => '123456789', 'id_number' => '19900101123450000123'],
            (new SharedEncrypter($key))->decryptJson($fromHrApp)
        );
    }
}
