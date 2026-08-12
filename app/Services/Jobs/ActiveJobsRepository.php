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
    /**
     * Read-only UNION ALL feed across the two recruitment schemas. Done as
     * two separate connection queries merged in PHP rather than a real SQL
     * UNION ALL, since 'shulesoft' and 'safaribook' are modeled as separate
     * Laravel connections (even though physically the same Postgres server).
     */
    public function fetchActive(): Collection
    {
        $columns = ['id', 'title', 'department', 'location', 'salary_min', 'salary_max', 'employment_type', 'application_deadline', 'created_at'];

        $shulesoft = DB::connection('shulesoft')->table('job_postings')
            ->where('status', 'active')
            ->get($columns)
            ->map(fn ($row) => (array) $row + ['source_schema' => 'shulesoft']);

        $safaribook = DB::connection('safaribook')->table('job_postings')
            ->where('status', 'active')
            ->get($columns)
            ->map(fn ($row) => (array) $row + ['source_schema' => 'safaribook']);

        return $shulesoft->concat($safaribook);
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
     * open for applications — used by every "apply" write path (Otp
     * Controller's post-login auto-apply, ApplicationsController::apply via
     * ApplicationService) so a stale/closed link can never be used to
     * sneak in an application, even if the public page itself is still
     * viewable for a closed posting.
     */
    public function findActiveByUuid(string $uuid): ?array
    {
        $job = $this->findByUuid($uuid);

        return $job && $job['status'] === 'active' ? $job : null;
    }
}
