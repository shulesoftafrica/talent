<?php

namespace App\Services\Applications;

use App\Models\Application;
use App\Services\Notifications\UnifiedNotificationClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tells the hiring manager by email when a candidate accepts or declines an
 * interview invitation, and mirrors that response onto the live
 * shulesoft/safaribook interviews row so the recruiter's own dashboard shows
 * it immediately — same "write straight into the origin schema" approach
 * already used for interview cancellation sync (see
 * ApplicationWithdrawalController and Application::scheduledInterview()).
 */
class HiringManagerNotifier
{
    public function __construct(private readonly UnifiedNotificationClient $notifications)
    {
    }

    public function notify(Application $application, string $response, ?string $note = null): void
    {
        $connection = DB::connection($application->source_schema);

        $jobPosting = $connection->table('job_postings')->find($application->source_job_posting_id);
        if (!$jobPosting) {
            return;
        }

        $this->updateInterviewRecord($connection, $application->source_application_id, $response);

        $managerId = $jobPosting->hiring_manager_id ?? $jobPosting->created_by ?? null;
        $manager = $managerId ? $connection->table('users')->find($managerId) : null;

        if (!$manager || empty($manager->email)) {
            Log::info('HiringManagerNotifier: no hiring manager email to notify', [
                'application_id' => $application->id,
                'job_posting_id' => $application->source_job_posting_id,
            ]);
            return;
        }

        $candidate = $application->candidate;
        $candidateName = $candidate->full_name ?? 'The candidate';
        $verb = $response === 'accepted' ? 'accepted' : 'declined';

        $subject = ucfirst($verb) . ' Interview Invitation - ' . $jobPosting->title;
        $message = "Dear {$manager->name},\n\n"
            . "{$candidateName} has {$verb} the interview invitation for the position of {$jobPosting->title}.\n"
            . ($note ? "\nCandidate's note: \"{$note}\"\n" : '')
            . "\nYou can view the full application in your ShuleSoft Recruitment dashboard.\n\n"
            . "Best regards,\nShuleSoft Talent Network";

        $this->notifications->send([
            'channel' => 'email',
            'to' => $manager->email,
            'subject' => $subject,
            'message' => $message,
        ]);
    }

    private function updateInterviewRecord($connection, ?int $sourceApplicationId, string $response): void
    {
        if (!$sourceApplicationId) {
            return;
        }

        // No status filter here (unlike Application::scheduledInterview()) —
        // for a decline, the withdrawal flow has already flipped this same
        // row to 'cancelled' by the time this runs, and that's still the
        // right row to stamp with the candidate's response.
        $interview = $connection->table('interviews')
            ->where('application_id', $sourceApplicationId)
            ->orderByDesc('id')
            ->first();

        if (!$interview) {
            return;
        }

        $connection->table('interviews')
            ->where('id', $interview->id)
            ->update([
                'candidate_response' => $response,
                'candidate_responded_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
