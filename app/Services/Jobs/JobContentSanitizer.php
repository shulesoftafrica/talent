<?php

namespace App\Services\Jobs;

/**
 * Turns a job posting's raw HTML-authored fields into clean, readable
 * plain-text sections for the candidate-facing job detail page (see
 * JobMatchesController::show) — and, since those fields are free text
 * written by the school's own staff and often mention the school by
 * name, redacts any literal occurrence of the resolved school name so
 * its identity stays hidden until the candidate applies.
 */
class JobContentSanitizer
{
    /**
     * Recruiters frequently type the school's own name straight into the
     * free-text 'location' field (e.g. "LUFADA HIGH SCHOOL, Tanzania"
     * instead of just "Tanzania") — resolveReal()/redactSchoolName() can't
     * catch this because it isn't a literal-name lookup problem, it's that
     * an entire comma-separated segment IS the institution's name. These
     * keywords flag such a segment for removal regardless of whether the
     * school is resolvable in admin.schools at all (confirmed some aren't).
     */
    private const LOCATION_INSTITUTION_KEYWORDS = [
        'school', 'academy', 'college', 'institute', 'university',
        'seminary', 'polytechnic', 'preparatory', 'kindergarten',
    ];

    /**
     * Only these fields are shown — 'description' is deliberately
     * excluded, since that's almost always where a school's own
     * self-introduction (and therefore its name) lives; these others are
     * far more likely to be generic task/requirement bullet points.
     *
     * @return array<string, string> field key => cleaned text, only for non-empty fields
     */
    public function sections(object $job, ?string $schoolName): array
    {
        $fields = [
            'responsibilities' => $job->responsibilities ?? null,
            'requirements' => $job->requirements ?? null,
            'qualifications' => $job->qualifications ?? null,
            'benefits' => $job->benefits ?? null,
        ];

        $sections = [];

        foreach ($fields as $key => $html) {
            $text = $this->clean((string) $html, $schoolName);

            if ($text !== '') {
                $sections[$key] = $text;
            }
        }

        return $sections;
    }

    /**
     * Cleans the 'description' field the same way sections() cleans the
     * others, but without redaction — sections() deliberately excludes
     * 'description' for the redacted candidate-matching flow (see class
     * docblock), but the public vacancy page (no redaction; the school's
     * identity is the entire point) needs it rendered too.
     */
    public function plainDescription(?string $html): string
    {
        return $this->clean((string) $html, null);
    }

    /**
     * Drops any comma-separated segment of a job's 'location' that names
     * the institution itself (see LOCATION_INSTITUTION_KEYWORDS), keeping
     * only the segments that read as actual geography. Falls back to a
     * generic label if nothing geographic is left.
     */
    public function sanitizeLocation(?string $location): string
    {
        $segments = array_filter(array_map('trim', explode(',', (string) $location)), fn ($s) => $s !== '');

        $geographic = array_values(array_filter($segments, function ($segment) {
            $lower = mb_strtolower($segment);

            foreach (self::LOCATION_INSTITUTION_KEYWORDS as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return false;
                }
            }

            return true;
        }));

        return $geographic ? implode(', ', $geographic) : 'Location hidden until you apply';
    }

    private function clean(string $html, ?string $schoolName): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = $this->unwrapDoubleEncoded($html);

        // Turn list items/paragraphs into line breaks before stripping
        // tags, so bullet content doesn't run together onto one line.
        $withBreaks = preg_replace('/<li[^>]*>/i', "\n\u{2022} ", $html) ?? $html;
        $withBreaks = preg_replace('/<\/(p|div|br)\s*\/?>/i', "\n", $withBreaks) ?? $withBreaks;

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/', "\n", $text) ?? $text;
        $text = trim($text);

        return $this->redactSchoolName($text, $schoolName);
    }

    /**
     * Some job_postings rows have 'qualifications'/'benefits' stored
     * double-JSON-encoded (the raw column value is itself a JSON string
     * literal, e.g. `"<p>Some text<\/p>"` instead of `<p>Some text</p>`) —
     * an existing data-quality issue in how those two fields get saved
     * upstream. Unwrap it so stray quotes/backslashes don't leak into the
     * candidate-facing page.
     */
    private function unwrapDoubleEncoded(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || $trimmed[0] !== '"' || substr($trimmed, -1) !== '"') {
            return $value;
        }

        $decoded = json_decode($trimmed);

        return is_string($decoded) ? $decoded : $value;
    }

    /**
     * Strips the resolved school name (and its first word — e.g.
     * "ShuleSoft" out of "ShuleSoft International School") wherever it
     * appears as a whole word. A school writing its own name into a
     * requirements or benefits paragraph is common, and only excluding the
     * 'description' field doesn't catch that.
     */
    private function redactSchoolName(string $text, ?string $schoolName): string
    {
        if (!$schoolName) {
            return $text;
        }

        $variants = array_unique(array_filter(
            [$schoolName, strtok($schoolName, ' ')],
            fn ($v) => mb_strlen((string) $v) >= 3
        ));

        foreach ($variants as $variant) {
            $text = preg_replace('/\b' . preg_quote($variant, '/') . '\b/iu', 'the school', $text) ?? $text;
        }

        return $text;
    }
}
