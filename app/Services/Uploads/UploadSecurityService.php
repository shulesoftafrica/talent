<?php

namespace App\Services\Uploads;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;

/**
 * Defense-in-depth scan layered on top of Laravel's own `mimes` validation
 * rule (which already content-sniffs via Fileinfo, not just the file
 * extension, so a raw script renamed to a trusted extension is already
 * rejected before this ever runs). Scoped to PDFs specifically: PDF
 * structure is mostly plain-text markers around compressed content
 * streams, so scanning for embedded script tags and PDF auto-action/
 * JavaScript objects is meaningful there — a legitimate CV or certificate
 * never needs either. The same byte-level scan is NOT run against
 * Office formats (doc/docx), since those are compressed/binary containers
 * where a plaintext substring search is both ineffective (a malicious
 * payload inside a compressed part wouldn't appear as literal text) and
 * prone to false positives on innocent binary noise.
 *
 * Also attempts a genuine PDF parse (same smalot/pdfparser library already
 * used for password/malformed detection in FraudPreventionService::
 * validatePdfUpload — mirrored here since CV/portfolio uploads accept more
 * than just verification-category PDFs and go through a different flow).
 */
class UploadSecurityService
{
    private const PDF_SCRIPT_SIGNATURES = ['<script', '/JavaScript', '/JS', '/OpenAction', '/AA'];

    /**
     * @return string|null An error message if the file is unsafe/invalid, or null if it's fine to proceed.
     */
    public function check(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return 'That file could not be uploaded — please try again.';
        }

        if ($file->getMimeType() !== 'application/pdf') {
            return null;
        }

        $contents = @file_get_contents($file->getRealPath()) ?: '';

        foreach (self::PDF_SCRIPT_SIGNATURES as $signature) {
            if (stripos($contents, $signature) !== false) {
                return 'That file could not be accepted for security reasons.';
            }
        }

        try {
            (new Parser())->parseFile($file->getRealPath());
        } catch (\Throwable $e) {
            $reason = strtolower($e->getMessage());

            return str_contains($reason, 'password') || str_contains($reason, 'secured') || str_contains($reason, 'encrypt')
                ? 'This PDF is password protected. Please upload an unprotected copy.'
                : 'This file is not a valid PDF document.';
        }

        return null;
    }
}
