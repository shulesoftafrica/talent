<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\AI\AiCoachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AiCoachController extends Controller
{
    public function __construct(private readonly AiCoachService $coach)
    {
    }

    public function profileReview(): JsonResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        return response()->json($this->coach->reviewProfile($candidate));
    }

    public function jobCoach(string $sourceSchema, int $jobPostingId): JsonResponse
    {
        if (!in_array($sourceSchema, ['shulesoft', 'safaribook'], true)) {
            abort(404);
        }

        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $job = DB::connection($sourceSchema)->table('job_postings')->find($jobPostingId);
        if (!$job) {
            abort(404);
        }

        return response()->json($this->coach->jobCoach($candidate, $job));
    }

    public function careerReadiness(): JsonResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        return response()->json($this->coach->careerReadiness($candidate));
    }
}
