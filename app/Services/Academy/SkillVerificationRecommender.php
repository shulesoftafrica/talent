<?php

namespace App\Services\Academy;

use App\Models\Candidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The skill-gap → "Verify this skill" loop (spec §36).
 *
 * Surfaces Academy skills the candidate could *prove* — skills that have a
 * published assessment and that the candidate hasn't already earned a valid
 * credential for — ranked by how well they fit the candidate's profession and
 * self-declared skills. Each item deep-links into Academy's assessment flow so
 * the candidate can turn a claimed skill into a verified one. Complements the
 * course recommender (learn → then prove).
 */
class SkillVerificationRecommender
{
    public function __construct(
        private readonly VerifiedSkillsRepository $verified,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recommend(Candidate $candidate, int $limit = 3): array
    {
        $alreadyVerified = $this->verified->verifiedSkillSlugs($candidate);

        $skills = DB::connection('academy')->table('skills as s')
            ->join('assessments as a', 'a.skill_id', '=', 's.id')
            ->leftJoin('category as cat', 'cat.id', '=', 's.category_id')
            ->where('a.status', 'published')
            ->where('s.status', 1) // academy.skills.status: 1 = active
            ->select('s.id', 's.name', 's.slug', 's.description', 'cat.name as category')
            ->groupBy('s.id', 's.name', 's.slug', 's.description', 'cat.name')
            ->limit(60)
            ->get()
            ->reject(fn (object $s) => in_array($s->slug, $alreadyVerified, true));

        if ($skills->isEmpty()) {
            return [];
        }

        $signals = $this->candidateSignals($candidate);
        $base = rtrim((string) config('services.academy.url', 'http://localhost/academy'), '/');

        return $skills->map(function (object $s) use ($signals) {
            $haystack = Str::lower(trim(($s->name ?? '') . ' ' . ($s->category ?? '') . ' ' . strip_tags((string) ($s->description ?? ''))));
            $matched = [];
            foreach ($signals as $label => $tokens) {
                foreach ($tokens as $token) {
                    if ($token !== '' && str_contains($haystack, $token)) {
                        $matched[$label] = true;
                        break;
                    }
                }
            }

            return ['skill' => $s, 'score' => count($matched), 'matched' => array_keys($matched)];
        })
            ->sortByDesc('score')
            ->take($limit)
            ->map(function (array $row) use ($base) {
                $s = $row['skill'];

                return [
                    'id' => 'verify-' . $s->id,
                    'title' => $s->name,
                    'priority' => 'VERIFY SKILL',
                    'why' => $row['score'] > 0
                        ? 'Prove ' . $this->joinLabels($row['matched']) . ' with a verified Academy credential employers can trust'
                        : 'Stand out by verifying this skill with a ShuleSoft Academy credential',
                    'meta' => array_values(array_filter([
                        $s->category ? ['k' => 'Category', 'v' => $s->category] : null,
                        ['k' => 'Outcome', 'v' => 'Verified credential'],
                    ])),
                    'url' => $base . '/skills/skill/' . $s->slug,
                    'cta' => 'Verify this skill',
                    'source' => 'academy-verify',
                    'completed' => false,
                    'enrolled' => false,
                    'certificate_id' => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function candidateSignals(Candidate $candidate): array
    {
        $signals = [];

        if ($candidate->profession) {
            $signals['your career path'] = [Str::lower($candidate->profession)];
        }

        $skills = $candidate->skills()->pluck('name')
            ->filter()
            ->map(fn ($s) => Str::lower((string) $s))
            ->all();
        if ($skills) {
            $signals['your skills'] = $skills;
        }

        return $signals;
    }

    /**
     * @param array<int, string> $labels
     */
    private function joinLabels(array $labels): string
    {
        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels) . ' & ' . $last;
    }
}
