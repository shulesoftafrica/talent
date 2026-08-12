<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\AI\JobMatchScorer;
use App\Services\Jobs\ActiveJobsRepository;
use App\Services\Jobs\JobContentSanitizer;
use App\Services\Jobs\SchoolNameResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JobMatchesController extends Controller
{
    /** Free-tier candidates only see this many matches before the premium upsell. */
    private const FREE_MATCH_LIMIT = 3;

    /**
     * Rendered per page load / per "load more" batch — at 1000+ active
     * postings, sending every matched job's markup in one response was the
     * actual page-weight problem (scoring itself is cheap after the first
     * hit, since JobMatchScorer caches the AI call — see its class docblock).
     */
    private const PAGE_SIZE = 10;

    public function __construct(
        private readonly JobMatchScorer $matcher,
        private readonly SchoolNameResolver $schoolNames,
        private readonly JobContentSanitizer $sanitizer,
        private readonly ActiveJobsRepository $activeJobs,
    ) {
    }

    public function index(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $jobs = $this->scoredJobs($candidate);

        $visible = $candidate->is_premium ? $jobs->take(self::PAGE_SIZE) : $jobs->take(self::FREE_MATCH_LIMIT);
        $hiddenCount = $candidate->is_premium ? 0 : $jobs->count() - $visible->count();
        $hasMore = $candidate->is_premium && $jobs->count() > $visible->count();

        return view('candidate.jobs', [
            'candidate' => $candidate,
            'jobs' => $visible,
            'totalCount' => $jobs->count(),
            'hiddenCount' => $hiddenCount,
            'hasMore' => $hasMore,
            'appliedKeys' => $this->appliedKeys($candidate),
        ]);
    }

    /**
     * Infinite-scroll continuation of index() — same ranked list (the AI
     * score cache makes re-deriving it here cheap on every call, so nothing
     * needs to be stored server-side between requests), sliced to the next
     * batch. Premium-only: free-tier's list is already fully visible within
     * one page (FREE_MATCH_LIMIT < PAGE_SIZE), so there's never a "more" to
     * load for them — and this must never become a way to page past that
     * limit without upgrading.
     */
    public function more(Request $request): JsonResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        abort_unless($candidate->is_premium, 403);

        $offset = max(0, (int) $request->query('offset', 0));

        $jobs = $this->scoredJobs($candidate);
        $batch = $jobs->slice($offset, self::PAGE_SIZE)->values();

        $appliedKeys = $this->appliedKeys($candidate);

        $html = $batch->map(fn ($job) => view('candidate._job-card', [
            'job' => $job,
            'appliedKeys' => $appliedKeys,
        ])->render())->implode('');

        return response()->json([
            'html' => $html,
            'count' => $batch->count(),
            'has_more' => $offset + $batch->count() < $jobs->count(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function scoredJobs(Candidate $candidate): Collection
    {
        return $this->matcher->score($candidate, $this->activeJobs->fetchActive())
            ->map(fn ($job) => $this->withDisplayFields($job))
            ->sortByDesc('match_score')
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function appliedKeys(Candidate $candidate): array
    {
        return $candidate->applications()
            ->get(['source_schema', 'source_job_posting_id'])
            ->map(fn ($app) => "{$app->source_schema}:{$app->source_job_posting_id}")
            ->all();
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

        $job = $this->matcher->score($candidate, $this->activeJobs->fetchActive())
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
