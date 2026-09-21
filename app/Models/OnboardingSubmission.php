<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What a new hire has saved or submitted for one onboarding requirement. Owned by
 * Talent; the HR module reads it. `data` is ciphertext (key shared with HR);
 * `files` is a list of {id, path, original_name, mime, size, sha256, label, source}.
 * `synced_at` and `processing_error` belong to the HR module.
 */
class OnboardingSubmission extends Model
{
    protected $fillable = [
        'candidate_id', 'application_id', 'source_schema', 'source_application_id', 'requirement_code',
        'data', 'files', 'status', 'submitted_at', 'version',
    ];

    protected function casts(): array
    {
        return ['files' => 'array', 'submitted_at' => 'datetime', 'synced_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            $submission->uuid ??= (string) Str::uuid();
        });
    }
}
