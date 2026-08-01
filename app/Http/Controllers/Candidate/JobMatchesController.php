<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JobMatchesController extends Controller
{
    /** Free-tier candidates only see this many matches before the premium upsell. */
    private const FREE_MATCH_LIMIT = 3;

    public function index(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $jobs = $this->fetchActiveJobs()
            ->map(fn ($job) => $this->scoreJob($job, $candidate))
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
     * Rule-based match score + human-readable reasons, standing in for the
     * AI-assisted reasoning until OpenAI quota/config is sorted out (see
     * App\Services\AI\OpenAiClient) — this app never blocks core browsing
     * on that dependency.
     */
    private function scoreJob(array $job, Candidate $candidate): array
    {
        $answers = $candidate->careerAnswersMap;
        $reasons = [];
        $missing = null;
        $score = 40; // base score so every active job is at least visible

        $professionTerms = array_filter([$candidate->profession, ...($job['department'] ? [$job['department']] : [])]);
        if ($candidate->profession && (
            str_contains(strtolower($job['title']), strtolower($candidate->profession))
            || str_contains(strtolower((string) $job['department']), strtolower($candidate->profession))
        )) {
            $score += 30;
            $reasons[] = "{$candidate->profession} role match";
        }

        $preferredCities = collect($answers['cities'] ?? []);
        if ($job['location'] && $preferredCities->contains(fn ($city) => str_contains(strtolower($job['location']), strtolower($city)))) {
            $score += 15;
            $reasons[] = 'Preferred location';
        }

        $minSalary = $answers['salary'] ?? null;
        if ($minSalary && $job['salary_max'] && (float) $job['salary_max'] >= (float) $minSalary) {
            $score += 15;
            $reasons[] = 'Salary range fits';
        } elseif ($minSalary) {
            $missing = 'Salary Expectation';
        }

        if (empty($reasons)) {
            $reasons[] = 'Newly posted';
        }

        $deadline = $job['application_deadline'] ? Carbon::parse($job['application_deadline']) : null;
        $deadlineDays = $deadline ? (int) floor(now()->diffInDays($deadline, false)) : null;

        return [
            ...$job,
            'match_score' => min(99, $score),
            'reasons' => $reasons,
            'missing' => $missing,
            'posted_days_ago' => (int) floor(Carbon::parse($job['created_at'])->diffInDays(now())),
            'deadline_days' => $deadlineDays,
            'salary_label' => $this->salaryLabel($job['salary_min'], $job['salary_max']),
        ];
    }

    private function salaryLabel(?string $min, ?string $max): string
    {
        if (!$min && !$max) {
            return 'Salary not disclosed';
        }

        $fmt = fn ($v) => number_format((float) $v, 0);

        return $min && $max
            ? 'TZS ' . $fmt($min) . ' – ' . $fmt($max) . '/mo'
            : 'TZS ' . $fmt($min ?? $max) . '/mo';
    }
}
