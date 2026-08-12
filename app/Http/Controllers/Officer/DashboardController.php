<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Constant\ReferSubject;
use App\Services\Jobs\SchoolNameResolver;
use App\Services\Verification\VerificationStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** How many of the biggest supply/demand gaps to surface — same idea as "jobs needing attention", not a full subject directory. */
    private const SUBJECT_ROWS = 12;

    /** How many under-performing active postings to surface. */
    private const ATTENTION_ROWS = 15;

    /** A posting isn't flagged until it's had a few days to attract applicants — avoid noise from same-day postings. */
    private const ATTENTION_MIN_DAYS_LIVE = 3;

    /** Below this application count, a posting is worth an officer's attention. */
    private const ATTENTION_MAX_APPLICATIONS = 5;

    public function __construct(private readonly SchoolNameResolver $schoolNames)
    {
    }

    public function index(): View
    {
        $officer = Auth::guard('officer')->user();

        $stats = [
            ['label' => 'Total Candidates', 'value' => number_format(DB::table('candidates')->count()), 'sub' => 'On the network'],
            ['label' => 'Premium Candidates', 'value' => number_format(DB::table('candidates')->where('is_premium', true)->count()), 'sub' => 'Paying subscribers'],
            ['label' => 'Verification Items Pending', 'value' => number_format(DB::table('candidate_verification_items')->whereIn('status', VerificationStatus::AWAITING_OFFICER)->count()), 'sub' => 'Awaiting officer review'],
            ['label' => 'Verification Items Verified', 'value' => number_format(DB::table('candidate_verification_items')->where('status', VerificationStatus::VERIFIED)->count()), 'sub' => 'Approved to date'],
            ['label' => 'Applications Submitted', 'value' => number_format(DB::table('applications')->count()), 'sub' => 'Via Talent Network only, all-time — see "Active postings needing attention" below for all-channel totals'],
            ['label' => 'Candidates Hired', 'value' => '—', 'sub' => 'Tracked per application status'],
        ];

        $regions = DB::table('candidates')
            ->select('current_location', DB::raw('count(*) as total'))
            ->whereNotNull('current_location')
            ->groupBy('current_location')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $professions = DB::table('candidates')
            ->select('profession', DB::raw('count(*) as total'))
            ->whereNotNull('profession')
            ->groupBy('profession')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $totalCandidates = max(1, DB::table('candidates')->count());

        $candidatesBySubject = $this->candidatesBySubject();
        $activeJobs = $this->activeJobsWithHealth();

        return view('officer.dashboard', [
            'officer' => $officer,
            'stats' => $stats,
            'regions' => $regions,
            'professions' => $professions,
            'totalCandidates' => $totalCandidates,
            'subjectBalance' => $this->subjectSupplyDemand($candidatesBySubject, $activeJobs),
            'jobHealth' => $this->jobPostingHealth($candidatesBySubject, $activeJobs),
        ]);
    }

    /**
     * @return Collection<int, int> candidate count keyed by subject_id
     */
    private function candidatesBySubject(): Collection
    {
        return DB::table('candidate_teaching_subjects')
            ->select('subject_id')
            ->selectRaw('count(distinct candidate_id) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');
    }

    /**
     * Every currently-active posting across both origin schemas, with its
     * real application count (the origin app's own applications table —
     * every channel, not just Talent Network) and required subject_ids —
     * the shared base both the subject-balance table and the job-health
     * table are built from, so each origin schema is only queried once.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function activeJobsWithHealth(): Collection
    {
        $rows = collect();

        foreach (['shulesoft', 'safaribook'] as $schema) {
            $jobs = DB::connection($schema)->table('job_postings')
                ->where('status', 'active')
                ->select('id', 'title', 'department', 'created_by', 'hiring_manager_id', 'created_at', 'location')
                ->get();

            if ($jobs->isEmpty()) {
                continue;
            }

            $jobIds = $jobs->pluck('id');

            $appCounts = DB::connection($schema)->table('applications')
                ->whereIn('job_posting_id', $jobIds)
                ->select('job_posting_id')
                ->selectRaw('count(*) as total')
                ->groupBy('job_posting_id')
                ->pluck('total', 'job_posting_id');

            $subjectsByJob = collect();

            if (Schema::connection($schema)->hasTable('job_posting_teaching_assignments')) {
                DB::connection($schema)->table('job_posting_teaching_assignments')
                    ->whereIn('job_posting_id', $jobIds)
                    ->select('job_posting_id', 'subject_id')
                    ->get()
                    ->each(function ($row) use ($subjectsByJob) {
                        if (!isset($subjectsByJob[$row->job_posting_id])) {
                            $subjectsByJob[$row->job_posting_id] = collect();
                        }
                        $subjectsByJob[$row->job_posting_id]->push($row->subject_id);
                    });
            }

            foreach ($jobs as $job) {
                $rows->push([
                    'source_schema' => $schema,
                    'id' => $job->id,
                    'title' => $job->title,
                    'department' => $job->department,
                    'location' => $job->location,
                    'created_by' => $job->created_by,
                    'hiring_manager_id' => $job->hiring_manager_id,
                    'days_live' => (int) Carbon::parse($job->created_at)->diffInDays(now()),
                    'applications' => (int) ($appCounts[$job->id] ?? 0),
                    'subject_ids' => $subjectsByJob[$job->id] ?? collect(),
                ]);
            }
        }

        return $rows;
    }

    /**
     * Candidates who can teach a subject vs. currently-active postings that
     * need it — both sides are real, explicit data (candidate_teaching_
     * subjects; job_posting_teaching_assignments), not inferred from
     * free-text. A negative gap means more open demand than candidates who
     * list that subject.
     *
     * @return array<int, array{subject: string, candidates: int, jobs: int, gap: int}>
     */
    private function subjectSupplyDemand(Collection $candidatesBySubject, Collection $activeJobs): array
    {
        $jobsBySubject = collect();
        foreach ($activeJobs as $job) {
            foreach ($job['subject_ids'] as $subjectId) {
                $jobsBySubject[$subjectId] = ($jobsBySubject[$subjectId] ?? 0) + 1;
            }
        }

        $subjectIds = $candidatesBySubject->keys()->merge($jobsBySubject->keys())->unique();

        if ($subjectIds->isEmpty()) {
            return [];
        }

        $subjectNames = ReferSubject::whereIn('subject_id', $subjectIds)->pluck('subject_name', 'subject_id');

        $rows = $subjectIds->map(function ($id) use ($candidatesBySubject, $jobsBySubject, $subjectNames) {
            $candidates = (int) ($candidatesBySubject[$id] ?? 0);
            $jobs = (int) ($jobsBySubject[$id] ?? 0);

            return [
                'subject' => $subjectNames[$id] ?? "Subject #{$id}",
                'candidates' => $candidates,
                'jobs' => $jobs,
                'gap' => $candidates - $jobs,
            ];
        })
            // Biggest shortages (most negative gap) first — that's what
            // actually needs an officer's attention, same framing as the
            // "jobs needing attention" idea.
            ->sortBy('gap')
            ->take(self::SUBJECT_ROWS)
            ->values();

        // Bar width as a % of the chart's half-track, scaled to the largest
        // gap in the (already-trimmed) visible rows — computed here rather
        // than in the view so the view stays pure presentation.
        $maxAbsGap = max(1, $rows->max(fn ($row) => abs($row['gap'])));

        return $rows->map(fn ($row) => [
            ...$row,
            'bar_pct' => (int) round(abs($row['gap']) / $maxAbsGap * 100),
        ])->all();
    }

    /**
     * Real-signal health check for active postings — deliberately limited
     * to what's actually derivable today: application counts (real) and
     * teaching-subject supply (real, from the same data as the balance
     * table above). No invitation/acceptance funnel and no salary-benchmark
     * diagnosis — neither has any underlying data source in this app yet,
     * and fabricating those numbers on an officer-facing dashboard would be
     * actively misleading rather than merely incomplete.
     *
     * @return array{
     *   active_count: int, total_applications: int, avg_applications: float,
     *   zero_application_count: int,
     *   flagged: array<int, array<string, mixed>>
     * }
     */
    private function jobPostingHealth(Collection $candidatesBySubject, Collection $activeJobs): array
    {
        $activeCount = $activeJobs->count();
        $totalApplications = $activeJobs->sum('applications');
        $zeroApplicationCount = $activeJobs->where('applications', 0)->count();

        $flagged = $activeJobs
            ->filter(fn ($job) => $job['days_live'] >= self::ATTENTION_MIN_DAYS_LIVE && $job['applications'] < self::ATTENTION_MAX_APPLICATIONS)
            ->map(function ($job) use ($candidatesBySubject) {
                $hasSubjects = $job['subject_ids']->isNotEmpty();
                $anySupply = $hasSubjects && $job['subject_ids']->contains(fn ($id) => ($candidatesBySubject[$id] ?? 0) > 0);

                $diagnosis = match (true) {
                    $hasSubjects && !$anySupply => 'No candidates list this subject',
                    $job['applications'] === 0 => 'No applications yet',
                    $job['applications'] <= 2 => 'Very few applications',
                    default => 'Low applications — worth a look',
                };

                return [
                    ...$job,
                    // resolveReal() (not resolve()) deliberately — this report
                    // must never substitute the job's internal department
                    // label as if it were a school name. A handful of active
                    // postings have a created_by pointing at a user id that no
                    // longer exists in the origin schema (confirmed via direct
                    // query), so their real school genuinely can't be
                    // resolved — that has to read as "Unknown school", not a
                    // department name that happens to look like one.
                    'school' => $this->schoolNames->resolveReal($job['source_schema'], $job['hiring_manager_id'], $job['created_by']),
                    'country' => $this->schoolNames->resolveCountry($job['source_schema'], $job['hiring_manager_id'], $job['created_by']),
                    'diagnosis' => $diagnosis,
                ];
            })
            ->sortBy([['applications', 'asc'], ['days_live', 'desc']])
            ->take(self::ATTENTION_ROWS)
            ->values()
            ->all();

        return [
            'active_count' => $activeCount,
            'total_applications' => $totalApplications,
            'avg_applications' => $activeCount > 0 ? round($totalApplications / $activeCount, 1) : 0.0,
            'zero_application_count' => $zeroApplicationCount,
            'flagged' => $flagged,
        ];
    }
}
