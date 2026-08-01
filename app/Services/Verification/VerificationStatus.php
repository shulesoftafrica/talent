<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\Lang;

/**
 * The 8 verification-item statuses (Upgrade Spec Part 9). Every
 * status-bearing table — candidate_verification_items and each category
 * detail table (identity, education docs, license, employment docs,
 * background, references) — stores exactly one of these values. Never a
 * bespoke per-table status string.
 */
final class VerificationStatus
{
    public const WAITING_PAYMENT = 'waiting_payment';
    public const WAITING_DOCUMENTS = 'waiting_documents';
    public const DOCUMENTS_SUBMITTED = 'documents_submitted';
    public const UNDER_REVIEW = 'under_review';
    public const MORE_INFORMATION_REQUIRED = 'more_information_required';
    public const VERIFIED = 'verified';
    public const PARTIALLY_VERIFIED = 'partially_verified';
    public const REJECTED = 'rejected';

    public const LABELS = [
        self::WAITING_PAYMENT => 'Waiting Payment',
        self::WAITING_DOCUMENTS => 'Waiting Documents',
        self::DOCUMENTS_SUBMITTED => 'Documents Submitted',
        self::UNDER_REVIEW => 'Under Review',
        self::MORE_INFORMATION_REQUIRED => 'More Information Required',
        self::VERIFIED => 'Verified',
        self::PARTIALLY_VERIFIED => 'Partially Verified',
        self::REJECTED => 'Rejected',
    ];

    /** Statuses meaning an officer still owes the candidate a decision. */
    public const AWAITING_OFFICER = [
        self::DOCUMENTS_SUBMITTED,
        self::UNDER_REVIEW,
    ];

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::LABELS);
    }

    public static function isValid(string $status): bool
    {
        return array_key_exists($status, self::LABELS);
    }

    /**
     * Locale-aware label — falls back to the English constant if the
     * active locale has no translation for this status key yet.
     */
    public static function label(string $status): string
    {
        $key = "verification.status.{$status}";

        if (Lang::has($key)) {
            return __($key);
        }

        return self::LABELS[$status] ?? $status;
    }
}
