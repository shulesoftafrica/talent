<?php

namespace App\Services\Applications;

/**
 * Maps the origin schema's raw `applications.status` (owned by
 * shulesoft/safaribook — the recruiter's real source of truth) to the
 * candidate-facing stage display. Always resolved live against the origin
 * row, never cached, so a recruiter's status change shows up immediately
 * without any sync job.
 */
class ApplicationStatusMapper
{
    private const STEP_LABELS = ['Applied', 'Under Review', 'Interview Invited', 'Offer', 'Hired'];

    /**
     * @return array{label:string, step_index:int, is_rejected:bool, urgency:string, stage_context:string, next_action_label:string, next_action_sub:string}
     */
    public static function resolve(string $status): array
    {
        return match ($status) {
            'new' => [
                'label' => 'Applied', 'step_index' => 0, 'is_rejected' => false, 'urgency' => 'waiting',
                'stage_context' => 'Your application has been received and is in the queue for review.',
                'next_action_label' => 'Wait for Review', 'next_action_sub' => 'Most schools respond within 5–7 business days.',
            ],
            'reviewing' => [
                'label' => 'Under Review', 'step_index' => 1, 'is_rejected' => false, 'urgency' => 'waiting',
                'stage_context' => 'The school is currently reviewing your application.',
                'next_action_label' => 'Improve Profile While You Wait', 'next_action_sub' => 'A stronger profile can raise your chances.',
            ],
            'shortlisted' => [
                'label' => 'Shortlisted', 'step_index' => 1, 'is_rejected' => false, 'urgency' => 'waiting',
                'stage_context' => 'You have been shortlisted — the school is deciding on interviews.',
                'next_action_label' => 'Prepare for a Possible Interview', 'next_action_sub' => 'Shortlisted candidates are often invited to interview soon.',
            ],
            'interview_scheduled' => [
                'label' => 'Interview Invited', 'step_index' => 2, 'is_rejected' => false, 'urgency' => 'attention',
                'stage_context' => 'The school has invited you to interview.',
                'next_action_label' => 'Confirm Attendance', 'next_action_sub' => 'Reply promptly to keep your interview slot.',
            ],
            'interviewed' => [
                'label' => 'Interview Completed', 'step_index' => 2, 'is_rejected' => false, 'urgency' => 'waiting',
                'stage_context' => 'You completed your interview — awaiting the school\'s decision.',
                'next_action_label' => 'Wait for a Decision', 'next_action_sub' => 'Schools usually decide within two weeks of interviewing.',
            ],
            'hired' => [
                'label' => 'Hired', 'step_index' => 4, 'is_rejected' => false, 'urgency' => 'completed',
                'stage_context' => 'Congratulations — you were hired for this role.',
                'next_action_label' => 'Start Onboarding', 'next_action_sub' => 'Prepare your documents for your first day.',
            ],
            'rejected' => [
                'label' => 'Not Selected', 'step_index' => 1, 'is_rejected' => true, 'urgency' => 'completed',
                'stage_context' => 'The school has moved forward with other candidates for this role.',
                'next_action_label' => 'See Recommended Jobs', 'next_action_sub' => 'Keep applying — your next match may be a better fit.',
            ],
            default => [
                'label' => 'Applied', 'step_index' => 0, 'is_rejected' => false, 'urgency' => 'waiting',
                'stage_context' => 'Your application has been received.',
                'next_action_label' => 'Wait for Review', 'next_action_sub' => 'Most schools respond within 5–7 business days.',
            ],
        };
    }

    public static function stepLabels(): array
    {
        return self::STEP_LABELS;
    }
}
