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
    public static function resolve(string $status, ?object $origin = null): array
    {
        return self::overlay(self::base($status), $status, $origin);
    }

    private static function base(string $status): array
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
            'offer' => [
                'label' => 'Offer', 'step_index' => 3, 'is_rejected' => false, 'urgency' => 'attention',
                'stage_context' => 'The school has made you a formal job offer.',
                'next_action_label' => 'Respond to Your Offer', 'next_action_sub' => 'Review the terms and accept or decline before the offer expires.',
            ],
            'hired', 'joined', 'probation', 'confirmed' => [
                'label' => 'Hired', 'step_index' => 4, 'is_rejected' => false, 'urgency' => 'completed',
                'stage_context' => 'Congratulations — you were hired for this role.',
                'next_action_label' => 'Start Onboarding', 'next_action_sub' => 'Prepare your documents for your first day.',
            ],
            'rejected' => [
                'label' => 'Not Selected', 'step_index' => 1, 'is_rejected' => true, 'urgency' => 'completed',
                'stage_context' => 'The school has moved forward with other candidates for this role.',
                'next_action_label' => 'See Recommended Jobs', 'next_action_sub' => 'Keep applying — your next match may be a better fit.',
            ],
            'transferred' => [
                'label' => 'Transferred', 'step_index' => 1, 'is_rejected' => false, 'urgency' => 'waiting',
                'stage_context' => 'The school moved your application to another vacancy.',
                'next_action_label' => 'Wait for the School', 'next_action_sub' => 'They will contact you about the new role.',
            ],
            'offer_expired' => [
                'label' => 'Offer Expired', 'step_index' => 3, 'is_rejected' => true, 'urgency' => 'completed',
                'stage_context' => 'Your offer expired before it was answered. Contact the school if you are still interested.',
                'next_action_label' => 'Contact the School', 'next_action_sub' => 'They can send you a new offer.',
            ],
            default => [
                'label' => 'Applied', 'step_index' => 0, 'is_rejected' => false, 'urgency' => 'waiting',
                'stage_context' => 'Your application has been received.',
                'next_action_label' => 'Wait for Review', 'next_action_sub' => 'Most schools respond within 5–7 business days.',
            ],
        };
    }

    /**
     * The wording the candidate sees. `label` stays the stage's fixed name (other code
     * compares it); `display_label` is what is shown, and follows the offer and onboarding
     * state on the origin application.
     */
    private static function overlay(array $meta, string $status, ?object $origin): array
    {
        $meta['display_label'] = $meta['label'];
        $offer = $origin->offer_status ?? null;
        $onboarding = $origin->onboarding_status ?? null;

        if ($status === 'offer') {
            $meta['display_label'] = 'Offer received';
        } elseif ($status === 'hired' && $offer === 'accepted') {
            [$display, $action, $sub, $urgency] = match ($onboarding) {
                'completed' => ['Onboarding complete', 'Welcome aboard', 'Your onboarding documents have been approved.', 'completed'],
                'submitted' => ['Onboarding under review', 'Wait for the school to review', 'The school is checking the documents you sent.', 'waiting'],
                'changes_requested' => ['Onboarding: changes requested', 'Fix the returned items', 'The school sent something back with a reason.', 'attention'],
                default => ['Offer accepted', 'Complete your onboarding', 'Upload the documents the school needs before your first day.', 'attention'],
            };
            $meta['display_label'] = $display;
            $meta['next_action_label'] = $action;
            $meta['next_action_sub'] = $sub;
            $meta['urgency'] = $urgency;
        } elseif ($status === 'rejected' && $offer === 'declined') {
            $meta['display_label'] = 'Offer declined';
            $meta['stage_context'] = 'You declined this offer.';
        } elseif ($status === 'offer_expired') {
            $meta['display_label'] = 'Offer expired';
        }

        return $meta;
    }

    public static function stepLabels(): array
    {
        return self::STEP_LABELS;
    }
}
