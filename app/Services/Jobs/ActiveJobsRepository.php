<?php

namespace App\Services\Jobs;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "the active job postings a candidate can be
 * matched against" — used by both the candidate-facing job list
 * (JobMatchesController) and the internal cross-app score endpoint
 * (Api\JobMatchController). Previously duplicated inline in
 * JobMatchesController; extracted so a hiring-manager-side score lookup and
 * the candidate's own list are always built from the exact same job set.
 */
class ActiveJobsRepository
{
    /** Shared by fetchActive() and findActiveById() so a single-job lookup returns the exact same shape JobMatchScorer expects. */
    private const SCORING_COLUMNS = ['id', 'title', 'department', 'location', 'salary_min', 'salary_max', 'employment_type', 'application_deadline', 'created_at'];

    /**
     * Read-only UNION ALL feed across the two recruitment schemas. Done as
     * two separate connection queries merged in PHP rather than a real SQL
     * UNION ALL, since 'shulesoft' and 'safaribook' are modeled as separate
     * Laravel connections (even though physically the same Postgres server).
     */
    public function fetchActive(): Collection
    {
        $shulesoft = DB::connection('shulesoft')->table('job_postings')
            ->where('status', 'active')
            ->get(self::SCORING_COLUMNS)
            ->map(fn ($row) => (array) $row + ['source_schema' => 'shulesoft']);

        $safaribook = DB::connection('safaribook')->table('job_postings')
            ->where('status', 'active')
            ->get(self::SCORING_COLUMNS)
            ->map(fn ($row) => (array) $row + ['source_schema' => 'safaribook']);

        return $shulesoft->concat($safaribook);
    }

    /**
     * Same shape as one row of fetchActive(), but for exactly one known
     * (schema, id) pair — used by the internal hiring-manager score lookup
     * (Api\JobMatchController) so it can score just the one job asked
     * about instead of the whole active set. JobMatchScorer::score()
     * fingerprints its cache key from the exact jobs collection it's given,
     * so scoring a single-job collection here is both correct (same
     * scoring logic) and cheap (a one-job AI prompt instead of the whole
     * network's, and its own independent cache entry — this never competes
     * with or invalidates a candidate's own "jobs for you" cache).
     */
    public function findActiveById(string $sourceSchema, int $id): ?array
    {
        if (!in_array($sourceSchema, ['shulesoft', 'safaribook'], true)) {
            return null;
        }

        $row = DB::connection($sourceSchema)->table('job_postings')
            ->where('id', $id)
            ->where('status', 'active')
            ->first(self::SCORING_COLUMNS);

        return $row ? (array) $row + ['source_schema' => $sourceSchema] : null;
    }

    /**
     * Looks up a single posting by its public uuid, regardless of status —
     * tries both connections since a uuid alone doesn't say which schema it
     * came from (the public "Share Vacancy" link is deliberately just
     * /jobs/{uuid}, no schema in the URL, so internal IDs/schema names stay
     * hidden). Returns the full raw row (not the trimmed column set
     * fetchActive() uses), since the public vacancy page needs description/
     * requirements/etc. that the candidate-matching list doesn't.
     *
     * Deliberately not filtered to status='active': PublicVacancyController
     * needs to distinguish active/closed/draft to show the right message
     * (e.g. "applications are closed" rather than a bare 404), so the
     * status decision belongs there, not in this lookup. Use
     * findActiveByUuid() below for the auto-apply paths, which must still
     * only ever apply to a genuinely open posting.
     */
    public function findByUuid(string $uuid): ?array
    {
        foreach (['shulesoft', 'safaribook'] as $schema) {
            $row = DB::connection($schema)->table('job_postings')
                ->where('uuid', $uuid)
                ->first();

            if ($row) {
                return (array) $row + ['source_schema' => $schema];
            }
        }

        return null;
    }

    /**
     * Same lookup, but only returns a result when the posting is actually
     * open for applications right now — status='active' AND its deadline
     * (if any) hasn't passed — used by the OTP post-login auto-apply path
     * so a stale/closed/expired link doesn't even attempt to apply. This
     * is a convenience short-circuit, not the sole guarantee: the real,
     * authoritative check lives in ApplicationService::apply() itself
     * (every apply path, including this one, goes through it), since a
     * deadline can pass in the gap between this lookup and the write.
     */
    public function findActiveByUuid(string $uuid): ?array
    {
        $job = $this->findByUuid($uuid);

        if (!$job || $job['status'] !== 'active') {
            return null;
        }

        if (!empty($job['application_deadline']) && \Illuminate\Support\Carbon::parse($job['application_deadline'])->isPast()) {
            return null;
        }

        return $job;
    }
}
