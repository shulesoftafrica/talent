<?php

namespace App\Services\Academy;

use App\Models\Candidate;
use Illuminate\Support\Facades\DB;

/**
 * Matches a candidate's Academy-verified skills against a vacancy's structured
 * skill requirements (spec §34–§35, §48).
 *
 * Returns a weighted match percentage (required skills weigh more than preferred),
 * an "N of M required matched" count, and a per-requirement breakdown with the
 * candidate's verified level — so the score is explainable, not mysterious. A
 * verified skill is what counts here; an unverified CV claim never satisfies a
 * requirement (§35). Returns null when the vacancy has no structured
 * requirements, so callers can simply hide the panel.
 */
class VacancySkillMatcher
{
    public function __construct(
        private readonly VerifiedSkillsRepository $verified,
    ) {
    }

    /**
     * @return array{percent:int, required_total:int, required_met:int,
     *               items:array<int,array<string,mixed>>, gaps:array<int,array<string,mixed>>}|null
     */
    public function match(Candidate $candidate, string $sourceSchema, int $jobPostingId): ?array
    {
        $reqs = DB::table('vacancy_skill_requirements')
            ->where('source_schema', $sourceSchema)
            ->where('job_posting_id', $jobPostingId)
            ->orderByRaw("CASE requirement WHEN 'required' THEN 0 ELSE 1 END")
            ->orderBy('skill_name')
            ->get();

        if ($reqs->isEmpty()) {
            return null;
        }

        $verified = $this->verified->forCandidate($candidate)->keyBy('skill_slug');
        $academyBase = rtrim((string) config('services.academy.url', 'http://localhost/academy'), '/');

        $items = [];
        $requiredTotal = 0;
        $requiredMet = 0;
        $weightSum = 0.0;
        $weightMet = 0.0;

        foreach ($reqs as $r) {
            $isRequired = $r->requirement === 'required';
            $weight = $isRequired ? 2 : 1;
            $vs = $verified->get($r->skill_slug);
            $met = $vs !== null && (int) $vs['level_rank'] >= (int) $r->min_level_rank;

            $items[] = [
                'skill_name'      => $r->skill_name,
                'skill_slug'      => $r->skill_slug,
                'min_level_name'  => $r->min_level_name,
                'min_level_rank'  => (int) $r->min_level_rank,
                'requirement'     => $r->requirement,
                'met'             => $met,
                'candidate_level' => $vs['level_name'] ?? null,
                'candidate_rank'  => $vs['level_rank'] ?? null,
                'candidate_score' => $vs['score'] ?? null,
                'verify_url'      => $academyBase . '/skills/skill/' . $r->skill_slug,
            ];

            $weightSum += $weight;
            if ($met) {
                $weightMet += $weight;
            }
            if ($isRequired) {
                $requiredTotal++;
                if ($met) {
                    $requiredMet++;
                }
            }
        }

        return [
            'percent'        => $weightSum > 0 ? (int) round($weightMet / $weightSum * 100) : 0,
            'required_total' => $requiredTotal,
            'required_met'   => $requiredMet,
            'items'          => $items,
            'gaps'           => array_values(array_filter($items, fn ($i) => ! $i['met'])),
        ];
    }
}
