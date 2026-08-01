<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TrainingController extends Controller
{
    /**
     * Enrolls the candidate — completion (and any certificate) is recorded
     * separately once they've actually attended, not instantly on click.
     */
    public function enroll(Training $training): JsonResponse
    {
        $candidate = Auth::guard('candidate')->user();

        $candidate->trainings()->firstOrCreate(
            ['training_id' => $training->id],
            ['status' => 'enrolled']
        );

        return response()->json(['success' => true]);
    }
}
