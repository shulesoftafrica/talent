<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Constant\ReferSubject;
use App\Services\Verification\VerificationStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** How many of the biggest supply/demand gaps to surface — same idea as "jobs needing attention", not a full subject directory. */
    private const SUBJECT_ROWS = 12;

    public function index(): View
    {
        $officer = Auth::guard('officer')->user();

        $stats = [
            ['label' => 'Total Candidates', 'value' => number_format(DB::table('candidates')->count()), 'sub' => 'On the network'],
            ['label' => 'Premium Candidates', 'value' => number_format(DB::table('candidates')->where('is_premium', true)->count()), 'sub' => 'Paying subscribers'],
            ['label' => 'Verification Items Pending', 'value' => number_format(DB::table('candidate_verification_items')->whereIn('status', VerificationStatus::AWAITING_OFFICER)->count()), 'sub' => 'Awaiting officer review'],
            ['label' => 'Verification Items Verified', 'value' => number_format(DB::table('candidate_verification_items')->where('status', VerificationStatus::VERIFIED)->count()), 'sub' => 'Approved to date'],
            ['label' => 'Applications Submitted', 'value' => number_format(DB::table('applications')->count()), 'sub' => 'Via Talent Network'],
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

        return view('officer.dashboard', [
            'officer' => $officer,
            'stats' => $stats,
            'regions' => $regions,
            'professions' => $professions,
            'totalCandidates' => $totalCandidates,
            'subjectBalance' => $this->subjectSupplyDemand(),
        ]);
    }

    /**
     * Candidates who can teach a subject vs. currently-active postings that
     * need it — both sides are real, explicit data (candidate_teaching_
     * subjects; job_posting_teaching_assignments joined to active postings
     * in each origin schema), not inferred from free-text. A negative gap
     * means more open demand than candidates who list that subject.
     *
     * @return array<int, array{subject: string, candidates: int, jobs: int, gap: int}>
     */
    private function subjectSupplyDemand(): array
    {
        $candidatesBySubject = DB::table('candidate_teaching_subjects')
            ->select('subject_id')
            ->selectRaw('count(distinct candidate_id) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        $jobsBySubject = collect();

        foreach (['shulesoft', 'safaribook'] as $schema) {
            // Schemas evolve independently (this table doesn't exist yet on
            // every origin app's install) — skip rather than 500 the whole
            // dashboard over one lagging schema.
            if (!Schema::connection($schema)->hasTable('job_posting_teaching_assignments')) {
                continue;
            }

            DB::connection($schema)->table('job_posting_teaching_assignments as jta')
                ->join('job_postings as jp', 'jp.id', '=', 'jta.job_posting_id')
                ->where('jp.status', 'active')
                ->select('jta.subject_id')
                ->selectRaw('count(distinct jta.job_posting_id) as total')
                ->groupBy('jta.subject_id')
                ->get()
                ->each(function ($row) use ($jobsBySubject) {
                    $jobsBySubject[$row->subject_id] = ($jobsBySubject[$row->subject_id] ?? 0) + $row->total;
                });
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
}
