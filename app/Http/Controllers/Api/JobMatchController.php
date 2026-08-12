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
 * Match score look like for THIS posting". Scores just the one requested
 * job (via ActiveJobsRepository::findActiveById()), not the candidate's
 * whole active-job set — deliberately not the same call/cache entry as the
 * candidate's own "jobs for you" list. That was the original design (see
 * git history), but scoring the entire network's active postings just to
 * answer a single-job question was timing out in production on every
 * cache-cold request (confirmed live: consistent ~3s timeouts, the caller's
 * budget, with 0 bytes ever received) — this endpoint has to actually
 * respond. The trade-off: the AI's score for a job scored in isolation can
 * differ slightly from the same job scored alongside a candidate's other
 * matches (its system prompt reasons comparatively across the batch it's
 * given), so this number may not always exactly equal what the candidate
 * sees on their own list — an occasional few-point difference is a better
 * outcome than "always unavailable". Deliberately does NOT gate on
 * $candidate->is_premium — that's a candidate-side monetization limit on
 * how many of their OWN matches they can see, irrelevant to a hiring
 * manager reviewing one specific applicant.
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

        // Deliberately scores just this one job, not the whole active set
        // (fetchActive() would be dozens+ of postings network-wide) — this
        // endpoint answers "what's the score for THIS job", and the caller
        // (ShuleSoft's hiring-manager UI) is a synchronous HTTP request with
        // a short timeout budget. Scoring the full set here was previously
        // timing out in production on every cache-cold request (confirmed
        // live: consistent ~3s timeouts with 0 bytes received) — see
        // JobMatchScorer::score()'s cache-key comment for why a smaller
        // jobs collection here doesn't collide with or invalidate a
        // candidate's own "jobs for you" cache entry.
        $job = $this->activeJobs->findActiveById($validated['source_schema'], $jobPostingId);

        $scored = $job ? $this->matcher->score($candidate, collect([$job]))->first() : null;

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
