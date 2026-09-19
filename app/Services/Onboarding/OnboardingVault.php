<?php

namespace App\Services\Onboarding;

use App\Services\Uploads\UploadSecurityService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The only place onboarding files (identity documents, birth certificates,
 * medical forms, photos ...) are written to, or opened from, the shared
 * private `hr_onboarding` disk (REQ-HRX-10).
 *
 * store() vets every upload (size, sniffed MIME type, the existing PDF
 * script/password scan, EXIF stripping for images) and never returns a
 * public URL; temporaryUrl() issues a 10-minute link. Authorising WHO may
 * open a file (the owning candidate) is the caller's job and must happen
 * before temporaryUrl() -- this class only refuses paths that could escape
 * the three known folders.
 *
 * Path convention (matches the HR app):
 *   onboarding/{module}/{source_application_id}/{requirement_code}/{uuid}.{ext}
 */
class OnboardingVault
{
    public const MODULES = ['shulesoft', 'safaribook'];
    private const FOLDERS = ['onboarding', 'offers', 'templates'];

    public function __construct(private readonly UploadSecurityService $security)
    {
    }

    public static function enabled(): bool
    {
        return (bool) config('hr_onboarding.enabled');
    }

    public function disk(): Filesystem
    {
        if (! self::enabled()) {
            throw new \RuntimeException('HR onboarding storage is not enabled.');
        }

        return Storage::disk((string) config('hr_onboarding.disk'));
    }

    public function pathFor(string $module, int $applicationId, string $requirementCode, string $extension): string
    {
        if (! in_array($module, self::MODULES, true)) {
            throw new \InvalidArgumentException('Unknown module.');
        }
        if (! preg_match('/^[a-z0-9_]{1,60}$/', $requirementCode)) {
            throw new \InvalidArgumentException('Invalid requirement code.');
        }

        return sprintf('onboarding/%s/%d/%s/%s.%s', $module, $applicationId, $requirementCode, (string) Str::uuid(), strtolower($extension));
    }

    /**
     * @param  array<int,string>  $allowedMimes  e.g. ['application/pdf', 'image/jpeg', 'image/png']
     * @return array{path:string, original_name:string, mime:string, size:int, sha256:string}
     *
     * @throws UploadRejectedException
     */
    public function store(UploadedFile $file, string $path, int $maxBytes, array $allowedMimes): array
    {
        $this->assertSafePath($path);

        if (! $file->isValid()) {
            throw new UploadRejectedException('That file could not be uploaded. Please try again.');
        }

        if ($file->getSize() > $maxBytes) {
            throw new UploadRejectedException('That file is too large. The maximum is '.round($maxBytes / 1048576, 1).' MB.');
        }

        // finfo-sniffed content type, not the client-supplied one.
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, $allowedMimes, true)) {
            throw new UploadRejectedException('That file type is not accepted for this item.');
        }

        if ($error = $this->security->check($file)) {
            throw new UploadRejectedException($error);
        }

        $bytes = file_get_contents($file->getRealPath());
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $bytes = $this->reencodeImage($bytes, $mime);
        }

        $this->disk()->put($path, $bytes, 'private');

        return [
            'path' => $path,
            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 200),
            'mime' => $mime,
            'size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ];
    }

    /** A link valid for 10 minutes. Ownership must already have been checked. */
    public function temporaryUrl(string $path): string
    {
        $this->assertSafePath($path);

        return $this->disk()->temporaryUrl($path, now()->addMinutes((int) config('hr_onboarding.url_ttl_minutes', 10)));
    }

    private function assertSafePath(string $path): void
    {
        $parts = explode('/', $path);
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\') || in_array('', $parts, true) || ! in_array($parts[0], self::FOLDERS, true)) {
            throw new \InvalidArgumentException('Invalid file path.');
        }
    }

    /**
     * Decoding and re-encoding drops all metadata (EXIF: GPS, camera,
     * timestamps). The camera orientation is applied first so a photo taken
     * on a phone does not come out sideways afterwards.
     */
    private function reencodeImage(string $bytes, string $mime): string
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new UploadRejectedException('That image could not be read. Please upload a different file.');
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
            $rotation = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($rotation !== 0 && ($rotated = imagerotate($image, $rotation, 0)) !== false) {
                $image = $rotated;
            }
        }

        ob_start();
        if ($mime === 'image/png') {
            imagesavealpha($image, true);
            imagepng($image);
        } else {
            imagejpeg($image, null, 90);
        }
        $clean = (string) ob_get_clean();

        return $clean;
    }
}
