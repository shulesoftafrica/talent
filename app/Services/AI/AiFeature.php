<?php

namespace App\Services\AI;

/**
 * The set of features that make OpenAI calls, used to tag ai_usage_logs
 * rows — keeps the feature column typo-proof and gives a single place to
 * see every call site that spends money.
 */
final class AiFeature
{
    public const CV_PARSE = 'cv_parse';
    public const PROFILE_REVIEW = 'profile_review';
    public const JOB_COACH = 'job_coach';

    public const LABELS = [
        self::CV_PARSE => 'CV Parsing',
        self::PROFILE_REVIEW => 'AI Profile Review',
        self::JOB_COACH => 'Job Coach',
    ];

    public static function label(string $feature): string
    {
        return self::LABELS[$feature] ?? $feature;
    }
}
