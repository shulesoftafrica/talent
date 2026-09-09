<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lets ShuleSoft's Job Posting editor manage a vacancy's structured skill
 * requirements (vacancy_skill_requirements) — the table VacancySkillMatcher
 * already reads to compute skills_match on the job-match endpoint. Internal,
 * server-to-server only, same auth as JobMatchController.
 */
class VacancySkillRequirementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_schema' => 'required|in:shulesoft,safaribook',
            'job_posting_id' => 'required|integer',
        ]);

        $requirements = DB::table('vacancy_skill_requirements')
            ->where('source_schema', $validated['source_schema'])
            ->where('job_posting_id', (int) $validated['job_posting_id'])
            ->orderByRaw("CASE requirement WHEN 'required' THEN 0 ELSE 1 END")
            ->orderBy('skill_name')
            ->get(['skill_slug', 'skill_name', 'min_level_rank', 'min_level_name', 'requirement']);

        return response()->json(['success' => true, 'requirements' => $requirements]);
    }

    /**
     * Replaces the FULL set of requirements for one (source_schema,
     * job_posting_id) — simpler and safer than a partial patch API, and
     * matches how the Job Posting editor naturally submits "here is the
     * current full list of skill rows" on save. Passing an empty
     * `requirements` array is valid and clears every requirement for that
     * posting.
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_schema' => 'required|in:shulesoft,safaribook',
            'job_posting_id' => 'required|integer',
            'requirements' => 'present|array',
            'requirements.*.skill_slug' => 'required|string|max:255',
            'requirements.*.skill_name' => 'required|string|max:255',
            'requirements.*.min_level_rank' => 'required|integer|min:1|max:5',
            'requirements.*.min_level_name' => 'nullable|string|max:255',
            'requirements.*.requirement' => 'required|in:required,preferred',
        ]);

        $jobPostingId = (int) $validated['job_posting_id'];

        DB::transaction(function () use ($validated, $jobPostingId) {
            DB::table('vacancy_skill_requirements')
                ->where('source_schema', $validated['source_schema'])
                ->where('job_posting_id', $jobPostingId)
                ->delete();

            if (empty($validated['requirements'])) {
                return;
            }

            $now = now();
            $rows = array_map(fn (array $r) => [
                'source_schema' => $validated['source_schema'],
                'job_posting_id' => $jobPostingId,
                'skill_slug' => $r['skill_slug'],
                'skill_name' => $r['skill_name'],
                'min_level_rank' => $r['min_level_rank'],
                'min_level_name' => $r['min_level_name'] ?? null,
                'requirement' => $r['requirement'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $validated['requirements']);

            DB::table('vacancy_skill_requirements')->insert($rows);
        });

        return response()->json(['success' => true]);
    }
}
