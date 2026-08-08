<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Services\Applications\HiringManagerNotifier;
use App\Services\Applications\WithdrawalReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApplicationWithdrawalController extends Controller
{
    public function __construct(private readonly HiringManagerNotifier $notifier)
    {
    }

    /**
     * Withdraws a candidate from a single job's recruitment pipeline. The
     * origin school's own applications row only ever gets its status
     * flipped to 'withdrawn' — the reason the candidate picked is a
     * candidate-network-side detail and is never written to that row, per
     * product direction ("the school will not see the personal reason").
     */
    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeOwner($application);

        if ($application->isWithdrawn()) {
            return back()->with('status', 'This application has already been withdrawn.');
        }

        if ($application->statusMeta()['label'] === 'Hired') {
            return back()->withErrors([
                'withdraw' => 'This application has already moved to Hired. Automatic withdrawal isn\'t available at this stage — please contact the school directly to formally decline the offer.',
            ]);
        }

        // Whether this withdrawal is really "declining an interview" —
        // keyed off an actual live, non-cancelled interview row rather than
        // applications.status === 'interview_scheduled', because not every
        // school's own flow reliably sets that exact status when it books
        // one (bulk scheduling, manual edits, etc. can leave it on
        // 'shortlisted' or similar even with a real interview on the
        // books). scheduledInterview() is the same live cross-schema read
        // the candidate's own "Interview Invited" UI already trusts.
        $pendingInterview = $application->scheduledInterview();

        $data = $request->validate([
            'reason' => ['required', Rule::in(WithdrawalReason::KEYS)],
            'reason_other' => ['required_if:reason,other', 'nullable', 'string', 'max:500'],
        ]);

        $application->update([
            'withdrawal_reason' => $data['reason'],
            'withdrawal_reason_other' => $data['reason'] === 'other' ? $data['reason_other'] : null,
            'withdrawn_at' => now(),
            ...($pendingInterview ? [
                'interview_response' => 'declined',
                'interview_responded_at' => now(),
            ] : []),
        ]);

        // The only signal the school's own tooling ever gets: this row is no
        // longer active. No reason, no candidate-network detail.
        //
        // Both shulesoft.applications and safaribook.applications have a
        // Postgres CHECK constraint on `status` that doesn't include a
        // "withdrawn" value (allowed: new/reviewing/shortlisted/
        // interview_scheduled/interviewed/offer/hired/joined/probation/
        // confirmed/rejected) — and altering that constraint would mean
        // changing business logic in a schema this app doesn't own. 'rejected'
        // is the closest existing status that means "no longer active in
        // this pipeline," so that's what gets written here.
        DB::connection($application->source_schema)->table('applications')
            ->where('id', $application->source_application_id)
            ->update(['status' => 'rejected', 'updated_at' => now()]);

        // Withdrawing only ever flipped the application's own status —
        // any interview the school had booked for this application kept
        // sitting there as 'scheduled'/'rescheduled', so the recruiter's
        // Interviews list kept showing it as upcoming even though the
        // candidate had already pulled out. Cancel it the same way the
        // school's own "Cancel Interview" action does, so both views agree.
        $connection = DB::connection($application->source_schema);
        $cancelNote = "\nCancelled: candidate withdrew their application via the Talent Network.";

        $connection->table('interviews')
            ->where('application_id', $application->source_application_id)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->update([
                'status' => 'cancelled',
                'notes' => DB::raw("coalesce(notes, '') || " . $connection->getPdo()->quote($cancelNote)),
                'updated_at' => now(),
            ]);

        if ($pendingInterview) {
            $this->notifier->notify($application, 'declined');
        }

        if ($data['reason'] === 'accepted_other_offer') {
            session()->flash('withdrawal_offer_prompt', $application->uuid);
        }

        return redirect()
            ->route('candidate.applications.index')
            ->with('status', 'Application withdrawn.');
    }

    /**
     * Optional follow-up after withdrawing for "accepted another offer" —
     * only ever runs if the candidate chooses to fill it in. Updates the
     * candidate's profile with the new role when they do.
     */
    public function storeOfferDetails(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeOwner($application);

        if (!$application->isWithdrawn() || $application->withdrawal_reason !== 'accepted_other_offer') {
            abort(404);
        }

        $data = $request->validate([
            'new_employer_name' => ['nullable', 'string', 'max:255'],
            'new_position' => ['nullable', 'string', 'max:255'],
            'new_start_date' => ['nullable', 'date'],
            'found_via_shulesoft' => ['nullable', 'boolean'],
        ]);

        $application->update($data);

        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        if (!empty($data['new_employer_name'])) {
            $candidate->update([
                'current_employer' => $data['new_employer_name'],
                'current_employer_verified' => false,
            ]);
        }

        if (!empty($data['new_employer_name']) || !empty($data['new_position'])) {
            $candidate->experiences()->updateOrCreate(
                [
                    'title' => $data['new_position'] ?: 'New Role',
                    'organization' => $data['new_employer_name'] ?: 'New Employer',
                ],
                [
                    'start_date' => $data['new_start_date'] ?? null,
                    'is_current' => true,
                ]
            );
        }

        return redirect()
            ->route('candidate.applications.index')
            ->with('status', 'Thanks — your profile has been updated.');
    }

    private function authorizeOwner(Application $application): void
    {
        if ($application->candidate_id !== Auth::guard('candidate')->id()) {
            abort(403);
        }
    }
}
