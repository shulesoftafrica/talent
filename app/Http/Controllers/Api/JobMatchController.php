<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\AI\JobMatchScorer;
use App\Services\Jobs\ActiveJobsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets ShuleSoft's hiring-manager UI ask "what does THIS candidate's Job
 * Match score look like for THIS posting" and get back the exact same
 * number JobMatchScorer computed for the candidate's own "jobs for you"
 * list — same function, same active-job set (via ActiveJobsRepository),
 * same cache key, so the two sides can never disagree. Deliberately does
 * NOT gate on $candidate->is_premium — that's a candidate-side monetization
 * limit on how many of their OWN matches they can see, irrelevant to a
 * hiring manager reviewing one specific applicant.
 */
class JobMatchController extends Controller
{
    public function __construct(
        private readonly JobMatchScorer $matcher,
        private readonly ActiveJobsRepository $activeJobs,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id' => 'required|integer',
            'source_schema' => 'required|in:shulesoft,safaribook',
            'job_posting_id' => 'required|integer',
        ]);

        $candidate = Candidate::find($validated['candidate_id']);

        if (!$candidate) {
            return response()->json(['success' => false, 'message' => 'Candidate not found.'], 404);
        }

        // Laravel's 'integer' validation rule checks the shape of a value,
        // it does not cast it — a query-string job_posting_id arrives here
        // as the string "23", so without an explicit (int) cast the strict
        // === below silently failed for every request and every posting
        // came back "not available" regardless of its real status.
        $jobPostingId = (int) $validated['job_posting_id'];

        $scored = $this->matcher
            ->score($candidate, $this->activeJobs->fetchActive())
            ->first(fn (array $job) => $job['source_schema'] === $validated['source_schema']
                && (int) $job['id'] === $jobPostingId);

        if (!$scored) {
            // Not an error — the posting simply isn't in the active set the
            // candidate would be matched against (closed/expired/draft), so
            // there is no candidate-facing score to mirror. Distinct from a
            // genuine failure so ShuleSoft can show "not available" rather
            // than treating this as a lookup error.
            return response()->json([
                'success' => true,
                'available' => false,
                'reason' => 'Posting is not currently active, so no candidate-facing match score exists for it.',
            ]);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'match_score' => $scored['match_score'],
            'reasons' => $scored['reasons'],
            'missing' => $scored['missing'],
        ]);
    }
}
