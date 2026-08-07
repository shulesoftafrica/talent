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
}
