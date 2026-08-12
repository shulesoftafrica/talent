<?php

namespace App\Services\Candidates;

use App\Models\Candidate;
use App\Services\Verification\VerificationStatus;

/**
 * Per-section profile-completion breakdown shared by the profile page's own
 * completion meter (ProfileController) and the post-apply confirmation
 * modal (Candidate\ApplicationsController) — previously duplicated
 * verbatim in both places, which let them silently drift out of sync.
 */
class ProfileCompletionService
{
    /**
     * @return array<int, array{label: string, pct: int}>
     */
    public function sections(Candidate $candidate): array
    {
        $sections = [
            ['label' => 'Personal Information', 'pct' => $candidate->full_name && $candidate->current_location ? 100 : 60],
            ['label' => 'Experience', 'pct' => $candidate->experiences->isNotEmpty() ? 100 : 0],
            ['label' => 'Education', 'pct' => $candidate->educations->isNotEmpty() ? 100 : 0],
            ['label' => 'Portfolio', 'pct' => min(100, $candidate->portfolioItems->count() * 50)],
            ['label' => 'Skills', 'pct' => min(100, $candidate->skills->count() * 25)],
        ];

        // Verification is behind a global kill-switch until the business
        // launches it — showing this row (permanently stuck at 0%, since
        // nothing can be verified while it's off) would be a dead end.
        if (config('services.verification_enabled')) {
            $sections[] = ['label' => 'Verification', 'pct' => min(100, (int) $candidate->verificationItems()->where('status', VerificationStatus::VERIFIED)->count() * 17)];
        }

        return $sections;
    }
}
