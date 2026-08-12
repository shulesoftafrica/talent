<?php

namespace App\Services\Jobs;

use App\Models\Constant\ReferCountry;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a job posting's school/organization display name (and, for the
 * public vacancy page, its logo and country) from its origin schema — used
 * both for the "hidden until you apply" label and for redacting the
 * school's identity out of job description text before application (see
 * JobMatchesController::show). Both 'shulesoft' and 'safaribook' resolve
 * via their own users table plus the shared admin.schools table; anything
 * else falls back to the generic label.
 *
 * Every resolve*() method takes the job posting's hiring_manager_id AND its
 * created_by, in that order of preference — hiring_manager_id is the one
 * that actually identifies the school-side account (confirmed live: for
 * every currently-active posting checked across both schemas,
 * created_by's user record consistently has no schema_name — often no
 * user record at all — while hiring_manager_id resolves correctly).
 * created_by is kept only as a fallback for however job postings are
 * created elsewhere in the ecosystem; never rely on it alone.
 */
class SchoolNameResolver
{
    /** @var array<string, ?string> Memoized within this instance's lifetime. */
    private array $cache = [];

    /** @var array<string, ?string> Memoized within this instance's lifetime. */
    private array $schemaNameCache = [];

    /**
     * Filenames the underlying apps use as a "no logo uploaded" stand-in.
     * 'default-logo.png' is a real file but is the generic ShuleSoft brand
     * mark, not a school's own logo — showing it on the public vacancy page
     * would misrepresent it as the school's identity. 'business-default-
     * logo.png' doesn't even resolve to a real file on the live server
     * (confirmed via a direct request: 404) — the tenant app itself only
     * avoids that break by checking is_file() before building the URL,
     * which this app can't do against a remote server's filesystem.
     */
    private const PLACEHOLDER_PHOTOS = ['default-logo.png', 'defualt.png', 'business-default-logo.png'];

    private const DOMAIN_SUFFIX = ['shulesoft' => 'shulesoft.africa', 'safaribook' => 'safaribook.africa'];

    public function resolve(string $sourceSchema, ?int $hiringManagerId, ?int $createdBy, ?string $department): string
    {
        $fallback = $department ?: ($sourceSchema === 'shulesoft' ? 'A ShuleSoft School' : 'A ShuleSoft Network Client');

        return $this->resolveReal($sourceSchema, $hiringManagerId, $createdBy) ?? $fallback;
    }

    /**
     * Like resolve(), but returns null instead of a generic display
     * fallback when the real school name couldn't be found — used for
     * redacting a school's identity out of free-text job content (see
     * JobContentSanitizer), where a generic placeholder like "Software
     * department" must never be treated as the name to strip (it's a
     * job's department label, not the school's identity, and can
     * coincidentally match ordinary words elsewhere in the text), and by
     * any officer-facing report where a wrong-but-plausible-looking name
     * would be worse than an honest "unknown".
     */
    public function resolveReal(string $sourceSchema, ?int $hiringManagerId, ?int $createdBy = null): ?string
    {
        $schemaName = $this->resolveSchemaName($sourceSchema, $hiringManagerId, $createdBy);

        if (!$schemaName) {
            return null;
        }

        if (array_key_exists($schemaName, $this->cache)) {
            return $this->cache[$schemaName];
        }

        $name = DB::connection('admin')->table('schools')->where('schema_name', $schemaName)->value('name');

        // admin.schools is the curated registry of client schools — it has
        // no row for ShuleSoft's own tenant (confirmed: it isn't a school),
        // which is correct, not a gap. But that tenant still has a real,
        // self-reported name on its own setting row (sname) — falling back
        // to it means ShuleSoft's own internal postings show "ShuleSoft
        // Limited" instead of "Unknown school", without ever inventing a
        // name for a genuine client-school gap (this fallback only fires
        // when a schema_name resolved at all).
        $name ??= DB::connection($sourceSchema)->table('setting')->where('schema_name', $schemaName)->value('sname');

        return $this->cache[$schemaName] = $name;
    }

    /**
     * The school's own uploaded logo, as a public, directly-loadable URL —
     * or null if none was ever uploaded (still on a placeholder). Each
     * tenant is served on its own subdomain of shulesoft.africa/
     * safaribook.africa (the same subdomain as its schema_name), with
     * uploads publicly reachable at /storage/uploads/images/{filename} —
     * confirmed live against real school records.
     */
    public function resolveLogoUrl(string $sourceSchema, ?int $hiringManagerId, ?int $createdBy = null): ?string
    {
        $schemaName = $this->resolveSchemaName($sourceSchema, $hiringManagerId, $createdBy);
        $suffix = self::DOMAIN_SUFFIX[$sourceSchema] ?? null;

        if (!$schemaName || !$suffix) {
            return null;
        }

        $photo = DB::connection($sourceSchema)->table('setting')->where('schema_name', $schemaName)->value('photo');

        if (!$photo || in_array($photo, self::PLACEHOLDER_PHOTOS, true)) {
            return null;
        }

        return "https://{$schemaName}.{$suffix}/storage/uploads/images/{$photo}";
    }

    /**
     * The school's registered country, resolved the same way as its logo
     * (schema_name -> that tenant's own setting row -> country_id), then
     * named via the shared reference table. Null whenever the schema_name
     * itself can't be resolved — callers must show that as "unknown", not
     * guess.
     */
    public function resolveCountry(string $sourceSchema, ?int $hiringManagerId, ?int $createdBy = null): ?string
    {
        $schemaName = $this->resolveSchemaName($sourceSchema, $hiringManagerId, $createdBy);

        if (!$schemaName) {
            return null;
        }

        $countryId = DB::connection($sourceSchema)->table('setting')->where('schema_name', $schemaName)->value('country_id');

        return $countryId ? ReferCountry::find($countryId)?->country : null;
    }

    private function resolveSchemaName(string $sourceSchema, ?int $hiringManagerId, ?int $createdBy): ?string
    {
        if (!in_array($sourceSchema, ['shulesoft', 'safaribook'], true)) {
            return null;
        }

        foreach (array_filter([$hiringManagerId, $createdBy]) as $userId) {
            $cacheKey = "{$sourceSchema}:{$userId}";

            if (array_key_exists($cacheKey, $this->schemaNameCache)) {
                $cached = $this->schemaNameCache[$cacheKey];
            } else {
                $cached = $this->schemaNameCache[$cacheKey] = DB::connection($sourceSchema)->table('users')->where('id', $userId)->value('schema_name');
            }

            if ($cached) {
                return $cached;
            }
        }

        return null;
    }
}
