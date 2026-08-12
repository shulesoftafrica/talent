<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\Jobs\ActiveJobsRepository;
use App\Services\Jobs\JobContentSanitizer;
use App\Services\Jobs\SchoolNameResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Genuinely public (no candidate login required) vacancy landing page for
 * ShuleSoft's "Share Vacancy" distribution feature — the destination behind
 * every WhatsApp/Facebook/LinkedIn share, QR code, and printed poster a
 * school generates for one of its own job postings.
 *
 * Deliberately separate from Candidate\JobMatchesController::show(), which
 * requires auth:candidate, redacts the school's identity, and gates full
 * content behind premium — none of that applies here: this page is a public
 * advertisement for one specific vacancy, so the school's name and the full
 * job content are shown openly. The only thing gated is applying, which
 * still goes through the existing candidate signup/login + apply flow.
 */
class PublicVacancyController extends Controller
{
    public function __construct(
        private readonly ActiveJobsRepository $activeJobs,
        private readonly SchoolNameResolver $schoolNames,
        private readonly JobContentSanitizer $sanitizer,
    ) {
    }

    public function show(string $uuid): View
    {
        $job = $this->activeJobs->findByUuid($uuid);

        // Draft postings aren't public yet, and a uuid that matches nothing
        // (deleted, or never existed) gets the same friendly "Opportunity
        // Unavailable" treatment — a school's printed poster or an old
        // WhatsApp share should never dead-end on a bare Laravel 404 page.
        if (!$job || $job['status'] === 'draft') {
            return view('public.vacancy', ['unavailable' => true]);
        }

        $schoolName = $this->schoolNames->resolve($job['source_schema'], $job['created_by'] ?? null, $job['department'] ?? null);
        $schoolLogoUrl = $this->schoolNames->resolveLogoUrl($job['source_schema'], $job['created_by'] ?? null);

        $deadline = $job['application_deadline'] ? Carbon::parse($job['application_deadline']) : null;
        $deadlinePassed = $deadline ? $deadline->isPast() : false;

        /** @var Candidate|null $candidate */
        $candidate = Auth::guard('candidate')->user();
        $alreadyApplied = $candidate && $candidate->applications()
            ->where('source_schema', $job['source_schema'])
            ->where('source_job_posting_id', $job['id'])
            ->whereNull('withdrawn_at')
            ->exists();

        return view('public.vacancy', [
            'unavailable' => false,
            'uuid' => $uuid,
            'job' => $job,
            'schoolName' => $schoolName,
            'schoolLogoUrl' => $schoolLogoUrl,
            'description' => $this->sanitizer->plainDescription($job['description'] ?? null),
            'sections' => $this->sanitizer->sections((object) $job, null),
            'deadline' => $deadline,
            'deadlinePassed' => $deadlinePassed,
            // Both a 'closed' status and a passed deadline mean applications
            // are shut, but the page itself (and its stable URL) stays up —
            // only "closed" implies the school itself ended it early.
            'applicationsClosed' => $job['status'] === 'closed' || $deadlinePassed,
            'salaryLabel' => $this->salaryLabel($job['salary_min'] ?? null, $job['salary_max'] ?? null),
            'alreadyApplied' => $alreadyApplied,
        ]);
    }

    private function salaryLabel(?string $min, ?string $max): string
    {
        if (!$min && !$max) {
            return 'Competitive Salary';
        }

        $fmt = fn ($v) => number_format((float) $v, 0);

        return $min && $max
            ? 'TZS ' . $fmt($min) . ' – ' . $fmt($max) . '/mo'
            : 'TZS ' . $fmt($min ?? $max) . '/mo';
    }
}
