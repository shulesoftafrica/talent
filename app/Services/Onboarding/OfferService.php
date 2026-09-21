<?php

namespace App\Services\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\OfferResponse;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The candidate's view of a formal offer and the way they answer it
 * (REQ-HRX-04). Reads the offer from the origin application (the HR module is
 * the system of record) and records an answer ONLY in Talent's own
 * `offer_responses` table -- nothing is written into the HR schema; the HR
 * module applies the row within a minute.
 */
class OfferService
{
    /** Same list the HR module offers on its page. */
    public const DECLINE_REASONS = [
        'salary' => 'Salary',
        'accepted_another_offer' => 'Accepted another offer',
        'location' => 'Location',
        'role_not_as_expected' => 'Role not as expected',
        'personal_reasons' => 'Personal reasons',
        'other' => 'Other',
    ];

    public function __construct(private readonly HrOnboardingGateway $origin)
    {
    }

    /**
     * open | awaiting_hr (answered here, not yet applied) | accepted | declined | expired | withdrawn
     *
     * @return array{hr: object, state: string, response: ?OfferResponse}|null null when the school has made no offer
     */
    public function snapshot(Application $application): ?array
    {
        $hr = $this->origin->hrApplication($application);
        if (! $hr || empty($hr->offer_status)) {
            return null;
        }

        $response = OfferResponse::query()
            ->where('source_schema', $application->source_schema)
            ->where('source_application_id', $hr->id)
            ->where('offer_version', (int) $hr->offer_version)
            ->first();

        $state = match (true) {
            $hr->offer_status === 'accepted' => 'accepted',
            $hr->offer_status === 'declined' => 'declined',
            $hr->offer_status === 'withdrawn' => 'withdrawn',
            $hr->offer_status === 'expired' => 'expired',
            $hr->offer_status === 'sent' && $this->isPastExpiry($hr) => 'expired',
            $hr->offer_status === 'sent' && $response !== null => 'awaiting_hr',
            default => 'open',
        };

        return ['hr' => $hr, 'state' => $state, 'response' => $response];
    }

    private function isPastExpiry(object $hr): bool
    {
        return $hr->offer_token_expires_at && strtotime($hr->offer_token_expires_at) < time();
    }

    /** Case-insensitive, spacing-tolerant. */
    public static function namesMatch(?string $typed, ?string ...$known): bool
    {
        $normalise = fn (?string $n) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $n)));
        $typed = $normalise($typed);

        foreach ($known as $name) {
            if ($typed !== '' && $typed === $normalise($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{signed_name?:?string, confirmed?:mixed, reason?:?string, reason_detail?:?string}  $input
     *
     * @throws OfferAnswerRejectedException with a message safe to show the candidate
     */
    public function respond(Application $application, Candidate $candidate, string $action, array $input, ?string $ip, ?string $userAgent): OfferResponse
    {
        $snapshot = $this->snapshot($application);
        if (! $snapshot) {
            throw new OfferAnswerRejectedException('There is no offer to answer.');
        }

        $message = match ($snapshot['state']) {
            'open' => null,
            'expired' => 'This offer has expired. Please contact the school.',
            'withdrawn' => 'The school has withdrawn this offer.',
            default => 'This offer has already been answered.',
        };
        if ($message) {
            throw new OfferAnswerRejectedException($message);
        }

        $hr = $snapshot['hr'];
        $row = [
            'candidate_id' => $candidate->id, 'application_id' => $application->id, 'source_schema' => $application->source_schema,
            'source_application_id' => $hr->id, 'offer_version' => (int) $hr->offer_version, 'action' => $action,
            'ip' => $ip, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 300) : null, 'responded_at' => now(),
        ];

        if ($action === 'accept') {
            $name = trim((string) ($input['signed_name'] ?? ''));
            if (! self::namesMatch($name, $candidate->full_name, $hr->name)) {
                throw new OfferAnswerRejectedException('The name you typed does not match your name. Please type your full name exactly as it appears on your Talent profile or your application.');
            }
            if (! filter_var($input['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw new OfferAnswerRejectedException('Tick the box to confirm you have read and accept the terms of this offer.');
            }
            $row['signed_name'] = mb_substr($name, 0, 150);
        } elseif ($action === 'decline') {
            $reason = $input['reason'] ?? null;
            $detail = trim((string) ($input['reason_detail'] ?? ''));
            if (! isset(self::DECLINE_REASONS[$reason])) {
                throw new OfferAnswerRejectedException('Please choose a reason for declining.');
            }
            if ($reason === 'other' && $detail === '') {
                throw new OfferAnswerRejectedException('Please tell us the reason.');
            }
            $row['decline_reason'] = $reason;
            $row['decline_detail'] = $detail !== '' ? mb_substr($detail, 0, 400) : null;
        } else {
            throw new OfferAnswerRejectedException('Unknown action.');
        }

        try {
            return OfferResponse::query()->create($row);
        } catch (UniqueConstraintViolationException $e) {
            throw new OfferAnswerRejectedException('This offer has already been answered.');
        }
    }

    /** A 10-minute link to the offer letter PDF; the caller must have checked ownership. */
    public function letterUrl(object $hr, OnboardingVault $vault): ?string
    {
        return ! empty($hr->offer_letter_path) ? $vault->temporaryUrl($hr->offer_letter_path) : null;
    }
}
