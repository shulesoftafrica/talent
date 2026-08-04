<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\CandidateTeachingSubject;
use App\Services\CareerBuilder\CareerBuilderDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CareerBuilderController extends Controller
{
    public function saveProfession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profession' => ['required', Rule::in(CareerBuilderDefinition::PROFESSIONS)],
        ]);

        Auth::guard('candidate')->user()->forceFill($data)->save();

        return back()->with('status', 'Profession saved.');
    }

    /**
     * Generic save for one builder step's non-'subjects' fields. Field keys
     * are whitelisted per-step server-side against CareerBuilderDefinition
     * so a candidate can't write arbitrary field_key rows.
     */
    public function saveStepAnswers(Request $request): RedirectResponse
    {
        $candidate = Auth::guard('candidate')->user();
        $step = $request->input('step');

        $allSteps = CareerBuilderDefinition::allSteps($candidate->profession);
        if (!isset($allSteps[$step])) {
            abort(422, 'Unknown career builder step.');
        }

        $allowedKeys = collect($allSteps[$step]['fields'])
            ->where('kind', '!=', 'subjects')
            ->pluck('key');

        foreach ($allowedKeys as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $value = $request->input($key);
            $value = is_array($value) ? array_values(array_filter($value, fn ($v) => $v !== '')) : $value;

            // An empty/cleared field means "no answer yet" — delete the row
            // rather than writing null, since step-completion is derived
            // from which field_key rows exist at all (see CareerBuilderDataService).
            if ($value === null || $value === '' || $value === []) {
                $candidate->careerAnswers()->where('field_key', $key)->delete();
                continue;
            }

            $candidate->careerAnswers()->updateOrCreate(
                ['field_key' => $key],
                ['field_value' => $value]
            );
        }

        return back()->with('status', 'Saved.');
    }

    public function saveSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
            'years_experience' => ['nullable', 'integer', 'min:1', 'max:40'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
        ]);

        $candidate = Auth::guard('candidate')->user();

        $teachingSubject = $candidate->teachingSubjects()->updateOrCreate(
            ['subject_id' => $data['subject_id']],
            ['years_experience' => $data['years_experience'] ?? 1]
        );

        $teachingSubject->classes()->delete();
        foreach ($data['class_ids'] ?? [] as $classId) {
            $teachingSubject->classes()->create(['refer_class_id' => $classId]);
        }

        return back()->with('status', 'Subject saved.');
    }

    public function removeSubject(CandidateTeachingSubject $subject): RedirectResponse
    {
        if ($subject->candidate_id !== Auth::guard('candidate')->id()) {
            abort(403);
        }

        $subject->delete();

        return back()->with('status', 'Subject removed.');
    }
}
