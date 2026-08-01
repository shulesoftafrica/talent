<?php

namespace App\Services\Applications;

use App\Models\Application;
use App\Models\Candidate;

/**
 * Detects status changes on a candidate's applications by comparing the
 * live origin-schema status against `last_seen_status` (a cache used only
 * for change detection, never for display — see Application::status()).
 * Runs opportunistically on page load rather than via a queue/cron, since
 * there's no background-job infrastructure decided for this app yet.
 */
class NotificationService
{
    /** Statuses worth interrupting the candidate for. */
    private const NOTIFIABLE_STATUSES = ['interview_scheduled', 'hired', 'rejected'];

    public function syncForCandidate(Candidate $candidate): void
    {
        $applications = $candidate->applications()->get();

        foreach ($applications as $application) {
            $this->syncOne($application);
        }
    }

    private function syncOne(Application $application): void
    {
        $currentStatus = $application->status();

        if ($currentStatus === $application->last_seen_status) {
            return;
        }

        if (in_array($currentStatus, self::NOTIFIABLE_STATUSES, true)) {
            $meta = $application->statusMeta();
            $job = $application->jobPosting();

            $application->notifications()->create([
                'candidate_id' => $application->candidate_id,
                'type' => 'application_status',
                'title' => "{$meta['label']} — {$job->title}",
                'body' => $meta['stage_context'],
                'action_url' => route('candidate.applications.index', ['selected' => $application->id]),
            ]);
        }

        $application->forceFill(['last_seen_status' => $currentStatus])->save();
    }
}
