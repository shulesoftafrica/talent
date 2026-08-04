<?php

namespace App\Services\AI;

use App\Models\Candidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Scores active job postings against a candidate's profile/preferences
 * using OpenAI (falling back to Ollama, then to a rule-based heuristic —
 * same graceful-degradation pattern as every other AI feature in this app).
 *
 * Replaces the old purely rule-based scorer in JobMatchesController, which
 * only ever gave +30 for an exact substring match between profession and
 * job title/department — so "Administrator" never matched "Administration
 * Lead", while two completely unrelated jobs (e.g. Software Developer and
 * Partnerships Sales Lead) could tie on score purely from sharing the same
 * city and paying above the candidate's minimum salary. The rule-based
 * method below is kept only as the last-resort fallback if both AI
 * providers fail — it's intentionally left as-is, not improved, since it's
 * no longer the primary path.
 */
class JobMatchScorer
{
    public function __construct(private readonly OpenAiClient $openAi)
    {
    }

    /**
     * @param Collection<int, array<string, mixed>> $jobs Each must have at least: id, source_schema, title, department, location, salary_min, salary_max, employment_type.
     * @return Collection<int, array<string, mixed>> Same jobs, each with match_score/reasons/missing added.
     */
    public function score(Candidate $candidate, Collection $jobs): Collection
    {
        if ($jobs->isEmpty()) {
            return $jobs;
        }

        // Cache only the plain-array score data, never the Collection
        // itself — the database cache store serializes whatever is stored,
        // and Collection objects have come back as corrupted
        // __PHP_Incomplete_Class instances on a subsequent read in testing.
        // Plain arrays of scalars round-trip through serialize/unserialize
        // with no such risk.
        $cacheKey = $this->cacheKey($candidate, $jobs);

        $scoresByKey = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($candidate, $jobs) {
            $aiScores = $this->scoreWithAi($candidate, $jobs);

            $result = [];
            foreach ($jobs as $job) {
                $key = $this->jobKey($job);
                $result[$key] = $aiScores[$key] ?? $this->ruleBasedScore($job, $candidate);
            }

            return $result;
        });

        return $jobs->map(fn (array $job) => [
            ...$job,
            ...$scoresByKey[$this->jobKey($job)] ?? $this->ruleBasedScore($job, $candidate),
        ]);
    }

    /**
     * @return array<string, array{match_score:int, reasons:array<int,string>, missing:?string}>|array{}
     */
    private function scoreWithAi(Candidate $candidate, Collection $jobs): array
    {
        $profile = $this->candidateSummary($candidate);
        $jobList = $jobs->map(fn (array $job) => [
            'job_key' => $this->jobKey($job),
            'title' => $job['title'],
            'department' => $job['department'],
            'location' => $job['location'],
            'employment_type' => $job['employment_type'],
            'salary_min' => $job['salary_min'],
            'salary_max' => $job['salary_max'],
        ])->values()->all();

        $response = $this->openAi->chatJson(
            system: 'You are a job-matching engine for a school-staffing platform in Africa. Given a candidate profile/preferences and a list of active job postings, score how well EACH job matches the candidate from 0-100. Weigh role/profession relevance most heavily (a job unrelated to the candidate\'s profession should score low even if location/salary happen to fit), then location, salary fit, and employment type. Two jobs in different fields should essentially never tie unless they are genuinely equally relevant. IMPORTANT on salary: "minimum_acceptable_salary" is a FLOOR the candidate will accept, not a target — a job\'s salary_max at or above it is always a good fit, and a HIGHER salary is never a downside or a reason to lower the score; only a job whose salary_max falls below that floor is a salary mismatch. If a job\'s salary_min AND salary_max are BOTH null, the school simply did not disclose a salary — this is neutral, NOT a mismatch, and must never lower the score or appear in "reasons"/"missing" at all. IMPORTANT on "missing": it is shown to the candidate as "Missing: X — adding it may raise this match", so it must ONLY ever name something the CANDIDATE could personally add or change on their own profile (e.g. their salary expectation, a skill, a certification) — NEVER something about the job/school\'s own listing (an undisclosed salary, a vague description, etc. are the school\'s gap, not the candidate\'s, so they must never appear here). Use null for "missing" whenever there is nothing the candidate themselves could add. Return ONLY JSON: {"scores": [{"job_key": string, "score": integer 0-100, "reasons": [1-3 short strings, most important first], "missing": string|null}]} — exactly one entry per job_key given, any order.',
            user: json_encode(['candidate' => $profile, 'jobs' => $jobList]),
            maxTokens: 1800,
            candidateId: $candidate->id,
            feature: AiFeature::JOB_MATCH,
            meta: ['job_count' => count($jobList)],
        );

        $entries = $response['scores'] ?? null;

        if (!is_array($entries)) {
            return [];
        }

        $byKey = [];

        foreach ($entries as $entry) {
            $key = $entry['job_key'] ?? null;

            if (!$key) {
                continue;
            }

            $byKey[$key] = [
                'match_score' => max(0, min(99, (int) ($entry['score'] ?? 0))),
                'reasons' => array_values(array_filter((array) ($entry['reasons'] ?? []))) ?: ['AI match'],
                'missing' => $entry['missing'] ?? null,
            ];
        }

        return $byKey;
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateSummary(Candidate $candidate): array
    {
        $answers = $candidate->careerAnswersMap;

        return [
            'profession' => $candidate->profession,
            'current_employer' => $candidate->current_employer,
            'skills' => $candidate->skills()->pluck('name')->all(),
            'years_of_experience' => $candidate->experiences()->count(),
            'employment_type' => $answers['employmentType'] ?? null,
            'availability' => $answers['availability'] ?? null,
            'minimum_acceptable_salary' => $answers['salary'] ?? null,
            'preferred_cities' => $answers['cities'] ?? [],
            'preferred_countries' => $answers['countries'] ?? [],
            'willing_to_relocate' => $answers['relocate'] ?? null,
            'languages' => $answers['languages'] ?? [],
        ];
    }

    /**
     * Original rule-based scorer — last-resort fallback only (see class docblock).
     *
     * @return array{match_score:int, reasons:array<int,string>, missing:?string}
     */
    private function ruleBasedScore(array $job, Candidate $candidate): array
    {
        $answers = $candidate->careerAnswersMap;
        $reasons = [];
        $missing = null;
        $score = 40;

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
        } elseif ($minSalary && $job['salary_max']) {
            // The job DID disclose a salary and it's below the candidate's
            // floor — a genuine mismatch worth surfacing.
            $missing = 'Salary Expectation';
        }
        // If the job has no salary_max at all, the school simply didn't
        // disclose one — that's neutral, not a mismatch, so nothing is
        // flagged as missing.

        if (empty($reasons)) {
            $reasons[] = 'Newly posted';
        }

        return ['match_score' => min(99, $score), 'reasons' => $reasons, 'missing' => $missing];
    }

    private function jobKey(array $job): string
    {
        return "{$job['source_schema']}:{$job['id']}";
    }

    /**
     * Keyed by candidate id + a hash of both the active job set and the
     * candidate's own profile/preference fields, so the cache naturally
     * busts whenever either changes — no manual invalidation needed, and a
     * candidate re-visiting the same unchanged list within the window
     * doesn't trigger a fresh (billed) AI call every time.
     */
    private function cacheKey(Candidate $candidate, Collection $jobs): string
    {
        $jobsFingerprint = $jobs->map(fn (array $j) => $this->jobKey($j))->sort()->implode(',');
        $profileFingerprint = json_encode($this->candidateSummary($candidate));

        return 'job_match:' . $candidate->id . ':' . md5($jobsFingerprint . '|' . $profileFingerprint);
    }
}
