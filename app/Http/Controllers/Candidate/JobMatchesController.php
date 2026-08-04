<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\AI\JobMatchScorer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JobMatchesController extends Controller
{
    /** Free-tier candidates only see this many matches before the premium upsell. */
    private const FREE_MATCH_LIMIT = 3;

    public function __construct(private readonly JobMatchScorer $matcher)
    {
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
