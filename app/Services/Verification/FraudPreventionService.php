<?php

namespace App\Services\Verification;

use App\Models\CandidateVerificationItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

/**
 * Shared fraud-prevention checks used by every category upload controller
 * (Upgrade Spec Part 11). validatePdfUpload() and hasActiveSubmissionInReview()
 * are hard blocks (throw / return true to stop submission). The rest are
 * advisory flags the caller stores alongside the submission but never use
 * to reject it outright.
 */
class FraudPreventionService
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2MB

    private const FREE_EMAIL_DOMAINS = [
        'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com',
        'live.com', 'aol.com', 'mail.com', 'yandex.com', 'protonmail.com', 'gmx.com',
    ];

    /**
     * PDF only, max 2MB, must be a genuine parseable PDF (not a renamed
     * file), must not be password protected.
     */
    public function validatePdfUpload(UploadedFile $file, string $field): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => 'File must be 2MB or smaller.']);
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'pdf' || $file->getMimeType() !== 'application/pdf') {
            throw ValidationException::withMessages([$field => 'Only PDF files are accepted.']);
        }

        try {
            (new Parser())->parseFile($file->getRealPath());
        } catch (\Throwable $e) {
            $reason = strtolower($e->getMessage());
            $message = str_contains($reason, 'password') || str_contains($reason, 'secured') || str_contains($reason, 'encrypt')
                ? 'This PDF is password protected. Please upload an unprotected copy.'
                : 'This file is not a valid PDF document.';

            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * @param  class-string  $modelClass  A category detail model with a candidate_id column.
     */
    public function isDuplicateNumber(string $modelClass, string $column, string $value, int $excludeCandidateId): bool
    {
        return $modelClass::where($column, $value)
            ->where('candidate_id', '!=', $excludeCandidateId)
            ->exists();
    }

    public function isFreeEmailProvider(?string $email): bool
    {
        if (!$email || !str_contains($email, '@')) {
            return false;
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        return in_array($domain, self::FREE_EMAIL_DOMAINS, true);
    }

    /**
     * Best-effort, advisory only ("where possible" per the spec) — loose
     * token-overlap between the candidate's registered name and free text
     * pulled from the uploaded document. Never a hard block.
     */
    public function nameLikelyMatches(string $candidateName, string $documentText): bool
    {
        $needleTokens = array_filter(explode(' ', strtolower(preg_replace('/[^a-z ]/i', ' ', $candidateName))));

        if (empty($needleTokens)) {
            return true;
        }

        $haystack = strtolower($documentText);
        $matches = 0;

        foreach ($needleTokens as $token) {
            if (strlen($token) > 2 && str_contains($haystack, $token)) {
                $matches++;
            }
        }

        return $matches >= max(1, (int) ceil(count($needleTokens) / 2));
    }

    /**
     * Blocks a new submission while a prior one for the same category is
     * still actively in the officer's hands.
     */
    public function hasActiveSubmissionInReview(CandidateVerificationItem $item): bool
    {
        return in_array($item->status, VerificationStatus::AWAITING_OFFICER, true);
    }
}
