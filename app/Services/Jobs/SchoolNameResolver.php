<?php

namespace App\Services\Jobs;

use Illuminate\Support\Facades\DB;

/**
 * Resolves a job posting's school/organization display name from its
 * source schema + creator — used both for the "hidden until you apply"
 * label and for redacting the school's identity out of job description
 * text before application (see JobMatchesController::show). Only
 * 'shulesoft' currently has a real admin.schools lookup path;
 * 'safaribook' always falls back to its generic label since that link
 * isn't available yet.
 */
class SchoolNameResolver
{
    /** @var array<string, ?string> Memoized within this instance's lifetime. */
    private array $cache = [];

    public function resolve(string $sourceSchema, ?int $createdBy, ?string $department): string
    {
        $fallback = $department ?: ($sourceSchema === 'shulesoft' ? 'A ShuleSoft School' : 'A ShuleSoft Network Client');

        return $this->resolveReal($sourceSchema, $createdBy) ?? $fallback;
    }

    /**
     * Like resolve(), but returns null instead of a generic display
     * fallback when the real school name couldn't be found — used for
     * redacting a school's identity out of free-text job content (see
     * JobContentSanitizer), where a generic placeholder like "Software
     * department" must never be treated as the name to strip (it's a
     * job's department label, not the school's identity, and can
     * coincidentally match ordinary words elsewhere in the text).
     */
    public function resolveReal(string $sourceSchema, ?int $createdBy): ?string
    {
        if (!$createdBy || $sourceSchema !== 'shulesoft') {
            return null;
        }

        $cacheKey = "{$sourceSchema}:{$createdBy}";
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $schemaName = DB::connection('shulesoft')->table('users')->where('id', $createdBy)->value('schema_name');
        $schoolName = $schemaName
            ? DB::connection('admin')->table('schools')->where('schema_name', $schemaName)->value('name')
            : null;

        return $this->cache[$cacheKey] = $schoolName;
    }
}
