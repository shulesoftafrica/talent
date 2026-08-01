<?php

namespace App\Services\CareerBuilder;

use App\Models\Candidate;
use App\Models\Constant\ReferCity;
use App\Models\Constant\ReferClass;
use App\Models\Constant\ReferCountry;
use App\Models\Constant\ReferSubject;
use App\Models\Constant\SchoolLevel;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the Career Profile Builder's render-ready view data: static
 * step/field definitions merged with the candidate's saved answers, plus
 * (for Teacher) live options queried from the constant schema.
 */
class CareerBuilderDataService
{
    /** Default country for constant-schema lookups until the candidate sets one (Tanzania). */
    private const DEFAULT_COUNTRY_ID = 1;

    public function build(Candidate $candidate): array
    {
        $countryId = $candidate->country_id ?? self::DEFAULT_COUNTRY_ID;
        $answers = $candidate->careerAnswers()->pluck('field_value', 'field_key');

        $steps = CareerBuilderDefinition::allSteps($candidate->profession);

        $poolCountryIds = $this->poolCountryIds();
        $poolCountries = $this->poolCountries($poolCountryIds);
        $poolCities = $this->poolCities($poolCountryIds);

        $steps['common']['fields'] = collect($steps['common']['fields'])->map(function ($field) use ($poolCountries, $poolCities) {
            if (($field['key'] ?? null) === 'countries') {
                $field['options'] = $poolCountries;
            }
            if (($field['key'] ?? null) === 'cities') {
                $field['citySamples'] = $this->citySamples($poolCities);
                $field['cityOptions'] = $poolCities;
            }
            return $field;
        })->all();

        if ($candidate->profession === 'Teacher') {
            $selectedLevelIds = $answers->get('teachLevel', []);
            $steps['teaching']['fields'] = collect($steps['teaching']['fields'])->map(function ($field) use ($countryId, $selectedLevelIds) {
                if (($field['key'] ?? null) === 'teachLevel') {
                    $field['options'] = SchoolLevel::where('refer_country_id', $countryId)
                        ->orderBy('level_numeric')
                        ->get()
                        ->map(fn ($level) => ['value' => (string) $level->id, 'label' => $level->name])
                        ->all();
                }
                if (($field['key'] ?? null) === 'curriculum') {
                    $field['options'] = SchoolLevel::where('refer_country_id', $countryId)
                        ->whereNotNull('syllabus')
                        ->distinct()
                        ->orderBy('syllabus')
                        ->pluck('syllabus')
                        ->map(fn ($syllabus) => ['value' => $syllabus, 'label' => $syllabus])
                        ->all();
                }
                return $field;
            })->all();
        }

        return [
            'professions' => CareerBuilderDefinition::PROFESSIONS,
            'profession' => $candidate->profession,
            'steps' => $steps,
            'answers' => $answers,
            'teachingSubjects' => $candidate->profession === 'Teacher' ? $this->teachingSubjects($candidate) : collect(),
            'subjectOptions' => $candidate->profession === 'Teacher' ? $this->subjectOptions($countryId) : collect(),
            'classOptions' => $candidate->profession === 'Teacher' ? $this->classOptionsFor($answers->get('teachLevel', [])) : collect(),
            'progress' => $this->progress($steps, $answers, $candidate),
        ];
    }

    private function teachingSubjects(Candidate $candidate)
    {
        return $candidate->teachingSubjects()
            ->with(['subject', 'classes.referClass'])
            ->get();
    }

    /**
     * Countries where a real ShuleSoft/SafariBook school actually operates —
     * read from the live client base rather than a static guess, so this list
     * grows on its own as the business expands into new countries.
     *
     * @return array<int, int>
     */
    private function poolCountryIds(): array
    {
        $shulesoft = DB::connection('shulesoft')->table('setting')->whereNotNull('country_id')->distinct()->pluck('country_id');
        $safaribook = DB::connection('safaribook')->table('setting')->whereNotNull('country_id')->distinct()->pluck('country_id');

        return $shulesoft->merge($safaribook)->unique()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private function poolCountries(array $poolCountryIds): array
    {
        if (empty($poolCountryIds)) {
            return [];
        }

        return ReferCountry::whereIn('id', $poolCountryIds)
            ->orderBy('country')
            ->pluck('country')
            // refer_countries stores full official names like "Tanzania
            // (United Republic of)" — strip the parenthetical for a chip
            // label short enough to read at a glance.
            ->map(fn ($country) => trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $country)))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function poolCities(array $poolCountryIds): array
    {
        if (empty($poolCountryIds)) {
            return [];
        }

        return ReferCity::whereIn('countryid', $poolCountryIds)
            ->orderBy('city')
            ->pluck('city')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A short "popular picks" shortlist shown as one-click suggestion chips,
     * before the candidate has to type anything. Prefers well-known city
     * names when they exist in the live pool, falling back to the first few
     * pool cities alphabetically so this never comes up empty.
     *
     * @return array<int, string>
     */
    private function citySamples(array $poolCities): array
    {
        $preferred = ['Dar Es Salaam', 'Arusha', 'Dodoma', 'Mwanza', 'Zanzibar', 'Lagos', 'Abuja', 'Nairobi'];

        $matches = collect($poolCities)
            ->filter(fn ($city) => collect($preferred)->contains(fn ($p) => strcasecmp($p, $city) === 0))
            ->values();

        if ($matches->count() >= 4) {
            return $matches->all();
        }

        return collect($poolCities)->take(8)->all();
    }

    private function subjectOptions(int $countryId)
    {
        return ReferSubject::where('country_id', $countryId)
            ->orderBy('subject_name')
            ->get(['subject_id', 'subject_name']);
    }

    private function classOptionsFor(array $levelIds)
    {
        if (empty($levelIds)) {
            return collect();
        }

        return ReferClass::whereIn('school_level_id', $levelIds)
            ->orderBy('school_level_id')
            ->orderBy('class_numeric')
            ->get(['id', 'name', 'school_level_id']);
    }

    /**
     * @return array{answered:int,total:int,pct:int}
     */
    private function progress(array $steps, $answers, Candidate $candidate): array
    {
        $total = 0;
        $answered = 0;

        foreach ($steps as $step) {
            foreach ($step['fields'] as $field) {
                $total++;
                if ($field['kind'] === 'subjects') {
                    if ($candidate->profession === 'Teacher' && $candidate->relationLoaded('teachingSubjects') ? $candidate->teachingSubjects->isNotEmpty() : $candidate->teachingSubjects()->exists()) {
                        $answered++;
                    }
                    continue;
                }
                if ($answers->has($field['key']) && $answers->get($field['key']) !== null && $answers->get($field['key']) !== [] && $answers->get($field['key']) !== '') {
                    $answered++;
                }
            }
        }

        $ratio = $total ? $answered / $total : 0;

        return [
            'answered' => $answered,
            'total' => $total,
            'pct' => (int) round(46 + 52 * $ratio),
        ];
    }
}
