<?php

namespace App\Services\Verification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single place every verification-status change goes through, so the audit
 * trail (Upgrade Spec Part 12: "fully auditable with timestamps and status
 * history") can never drift out of sync with the actual status column.
 * Works on candidate_verification_items and on any category detail table,
 * since they all share the same status column shape plus a
 * statusHistories(): MorphMany(VerificationStatusHistory::class, 'subject') relation.
 */
class VerificationStatusService
{
    /**
     * @param  string  $actorType  'candidate' | 'officer' | 'system'
     */
    public function transition(Model $subject, string $toStatus, string $actorType, ?int $actorId = null, ?string $note = null): void
    {
        if (!VerificationStatus::isValid($toStatus)) {
            throw new InvalidArgumentException("Unknown verification status: {$toStatus}");
        }

        $from = $subject->status;

        if ($from === $toStatus) {
            return;
        }

        DB::transaction(function () use ($subject, $from, $toStatus, $actorType, $actorId, $note) {
            $subject->forceFill(['status' => $toStatus])->save();

            $subject->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $toStatus,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }
}
