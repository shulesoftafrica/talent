<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\CareerBuilder\CareerBuilderDataService;
use App\Services\Verification\VerificationStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly CareerBuilderDataService $careerBuilder)
    {
    }

    public function index(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user()->load([
            'experiences', 'educations', 'certifications', 'skills', 'hobbies', 'portfolioItems',
        ]);

        $profileCompletion = $this->profileCompletion($candidate);

        return view('candidate.profile', [
            'candidate' => $candidate,
            'profileCompletion' => $profileCompletion,
            'builder' => $this->careerBuilder->build($candidate),
        ]);
    }

    /**
     * @return array<int, array{label: string, pct: int}>
     */
    private function profileCompletion(Candidate $candidate): array
    {
        return [
            ['label' => 'Personal Information', 'pct' => $candidate->full_name && $candidate->current_location ? 100 : 60],
            ['label' => 'Experience', 'pct' => $candidate->experiences->isNotEmpty() ? 100 : 0],
            ['label' => 'Education', 'pct' => $candidate->educations->isNotEmpty() ? 100 : 0],
            ['label' => 'Portfolio', 'pct' => min(100, $candidate->portfolioItems->count() * 50)],
            ['label' => 'Skills', 'pct' => min(100, $candidate->skills->count() * 25)],
            ['label' => 'Verification', 'pct' => (int) $candidate->verificationItems()->where('status', VerificationStatus::VERIFIED)->count() * 17],
        ];
    }
}
