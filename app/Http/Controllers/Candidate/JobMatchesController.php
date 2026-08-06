<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\AI\JobMatchScorer;
use App\Services\Jobs\JobContentSanitizer;
use App\Services\Jobs\SchoolNameResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JobMatchesController extends Controller
{
    /** Free-tier candidates only see this many matches before the premium upsell. */
    private const FREE_MATCH_LIMIT = 3;

    public function __construct(
        private readonly JobMatchScorer $matcher,
        private readonly SchoolNameResolver $schoolNames,
        private readonly JobContentSanitizer $sanitizer,
    ) {
    }

    public function index(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $jobs = $this->matcher->score($candidate, $this->fetchActiveJobs())
            ->map(fn ($job) => $this->withDisplayFields($job))
            ->sortByDesc('match_score')
            ->values();

        $visible = $candidate->is_premium ? $jobs : $jobs->take(self::FREE_MATCH_LIMIT);
        $hiddenCount = $jobs->count() - $visible->count();

        $appliedKeys = $candidate->applications()
            ->get(['source_schema', 'source_job_posting_id'])
            ->map(fn ($app) => "{$app->source_schema}:{$app->source_job_posting_id}")
            ->all();

        return view('candidate.jobs', [
            'candidate' => $candidate,
            'jobs' => $visible,
            'hiddenCount' => $hiddenCount,
            'appliedKeys' => $appliedKeys,
        ]);
    }

    /**
     * Full job detail — responsibilities/requirements/qualifications/
     * benefits plus the AI match breakdown, with the hiring school's name
     * redacted throughout (candidates only learn who it is after applying).
     * Gated behind premium: non-premium candidates get a simple upsell
     * message instead, with no job content or score sent to the view.
     */
    public function show(string $sourceSchema, int $jobPostingId): View
    {
        abort_unless(in_array($sourceSchema, ['shulesoft', 'safaribook'], true), 404);

        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $rawJob = DB::connection($sourceSchema)->table('job_postings')
            ->where('id', $jobPostingId)
            ->where('status', 'active')
            ->first();

        abort_unless($rawJob, 404);

        $applied = $candidate->applications()
            ->where('source_schema', $sourceSchema)
            ->where('source_job_posting_id', $jobPostingId)
            ->whereNull('withdrawn_at')
            ->exists();

        if (!$candidate->is_premium) {
            return view('candidate.job-detail', [
                'candidate' => $candidate,
                'locked' => true,
                'title' => $rawJob->title,
                'location' => $this->sanitizer->sanitizeLocation($rawJob->location),
                'applied' => $applied,
                'sourceSchema' => $sourceSchema,
                'jobPostingId' => $jobPostingId,
            ]);
        }

        $job = $this->matcher->score($candidate, $this->fetchActiveJobs())
            ->map(fn ($j) => $this->withDisplayFields($j))
            ->first(fn ($j) => $j['source_schema'] === $sourceSchema && (int) $j['id'] === $jobPostingId);

        abort_unless($job, 404);

        $schoolName = $this->schoolNames->resolveReal($sourceSchema, $rawJob->created_by ?? null);

        return view('candidate.job-detail', [
            'candidate' => $candidate,
            'locked' => false,
            'job' => $job,
            'sections' => $this->sanitizer->sections($rawJob, $schoolName),
            'applied' => $applied,
            'sourceSchema' => $sourceSchema,
            'jobPostingId' => $jobPostingId,
        ]);
    }

    /**
     * Read-only UNION ALL feed across the two recruitment schemas. Done as
     * two separate connection queries merged in PHP rather than a real SQL
     * UNION ALL, since 'shulesoft' and 'safaribook' are modeled as separate
     * Laravel connections (even though physically the same Postgres server).
     */
    private function fetchActiveJobs()
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
     * Presentational fields only — actual match scoring/reasons come from
     * JobMatchScorer (AI-based, with a rule-based fallback), not from here.
     */
    private function withDisplayFields(array $job): array
    {
        $deadline = $job['application_deadline'] ? Carbon::parse($job['application_deadline']) : null;
        $deadlineDays = $deadline ? (int) floor(now()->diffInDays($deadline, false)) : null;

        return [
            ...$job,
            'location' => $this->sanitizer->sanitizeLocation($job['location']),
            'posted_days_ago' => (int) floor(Carbon::parse($job['created_at'])->diffInDays(now())),
            'deadline_days' => $deadlineDays,
            'salary_label' => $this->salaryLabel($job['salary_min'], $job['salary_max']),
        ];
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
