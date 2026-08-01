<?php

namespace App\Services\AI;

use App\Models\Candidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recommends Academy LMS courses for a candidate's Career Plan.
 *
 * Reads the Academy course catalogue (academy schema of the shared
 * shulesoft2024 database) and ranks active courses by how well they match the
 * candidate's profession, skills and career-questionnaire answers — i.e. the
 * gap between where the candidate is and what employers ask for. Results are
 * shaped like the Career Plan "recommendations" items and deep-link out to the
 * Academy course page for enrolment.
 */
class AcademyCourseRecommender
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recommend(Candidate $candidate, int $limit = 4): array
    {
        $courses = DB::connection('academy')->table('course as c')
            ->leftJoin('category as cat', 'cat.id', '=', 'c.category_id')
            ->where('c.status', 'active')
            ->select(
                'c.id', 'c.title', 'c.outcomes', 'c.level',
                'c.price', 'c.is_free_course', 'cat.name as category'
            )
            ->limit(80)
            ->get();

        if ($courses->isEmpty()) {
            return [];
        }

        $signals = $this->candidateSignals($candidate);
        $base = rtrim((string) config('services.academy.url', 'http://localhost/academy'), '/');

        $scored = $courses->map(function (object $c) use ($signals) {
            $haystack = Str::lower(trim(
                ($c->title ?? '') . ' ' .
                ($c->category ?? '') . ' ' .
                strip_tags((string) ($c->outcomes ?? '')) . ' ' .
                ($c->level ?? '')
            ));

            $matched = [];
            foreach ($signals as $label => $tokens) {
                foreach ($tokens as $token) {
                    if ($token !== '' && str_contains($haystack, $token)) {
                        $matched[$label] = true;
                        break;
                    }
                }
            }

            return ['course' => $c, 'score' => count($matched), 'matched' => array_keys($matched)];
        })
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return $scored->map(function (array $row) use ($base) {
            $c = $row['course'];
            $slug = Str::slug($c->title ?: 'course') ?: 'course';

            $meta = array_values(array_filter([
                $c->level ? ['k' => 'Level', 'v' => ucfirst((string) $c->level)] : null,
                $c->category ? ['k' => 'Category', 'v' => $c->category] : null,
                ['k' => 'Price', 'v' => $this->priceLabel($c)],
            ]));

            return [
                'id' => 'academy-' . $c->id,
                'title' => $c->title,
                'priority' => $row['score'] > 0 ? 'RECOMMENDED' : 'EXPLORE',
                'why' => $row['score'] > 0
                    ? 'Builds on ' . $this->joinLabels($row['matched'])
                    : 'Popular course to grow your skills',
                'meta' => $meta,
                'url' => $base . '/home/course/' . $slug . '/' . $c->id,
                'source' => 'academy',
                'completed' => false,
                'enrolled' => false,
                'certificate_id' => null,
            ];
        })->all();
    }

    /**
     * Candidate signal tokens grouped by a human-readable label, used both for
     * matching and to explain *why* a course was recommended.
     *
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

        $answerTokens = [];
        foreach ($candidate->careerAnswers()->get() as $answer) {
            $value = $answer->field_value;
            if ($value === false || $value === null || $value === '' || $value === '0' || $value === 'false') {
                continue;
            }
            // e.g. "financial_reporting_experience" -> "financial reporting"
            $answerTokens[] = Str::lower(trim(str_replace(['_experience', '_'], ['', ' '], (string) $answer->field_key)));
        }
        if ($answerTokens) {
            $signals['your experience'] = array_values(array_filter($answerTokens));
        }

        return $signals;
    }

    private function priceLabel(object $c): string
    {
        if ((int) $c->is_free_course === 1) {
            return 'Free';
        }

        return is_numeric($c->price) ? number_format((float) $c->price) : 'Paid';
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
