<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Applications\HiringManagerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InterviewResponseController extends Controller
{
    public function __construct(private readonly HiringManagerNotifier $notifier)
    {
    }

    /**
     * Lets a candidate confirm they'll attend an interview they've been
     * invited to, with an optional note — the only thing this stage
     * previously offered was a "Confirm Attendance" label with nothing to
     * click. Declining is handled by the existing withdrawal flow (see
     * ApplicationWithdrawalController), not duplicated here.
     */
    public function store(Request $request, Application $application): RedirectResponse
    {
        if ($application->candidate_id !== Auth::guard('candidate')->id()) {
            abort(403);
        }

        if ($application->statusMeta()['label'] !== 'Interview Invited') {
            abort(404);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $application->update([
            'interview_response' => 'confirmed',
            'interview_response_note' => $data['note'] ?? null,
            'interview_responded_at' => now(),
        ]);

        // Surface this to the school's own recruiter tooling — they need to
        // know the candidate is actually coming, and `notes` is the one
        // free-text field their existing UI already shows per application.
        $noteLine = 'Candidate confirmed interview attendance via ShuleSoft Talent Network on ' . now()->format('d M Y H:i')
            . ($data['note'] ? " — \"{$data['note']}\"" : '');

        DB::connection($application->source_schema)->table('applications')
            ->where('id', $application->source_application_id)
            ->update([
                'notes' => DB::raw("coalesce(notes, '') || " . DB::connection($application->source_schema)->getPdo()->quote("\n" . $noteLine)),
                'updated_at' => now(),
            ]);

        $this->notifier->notify($application, 'accepted', $data['note'] ?? null);

        return redirect()
            ->route('candidate.applications.index', ['selected' => $application->uuid])
            ->with('status', 'Thanks — the school has been notified that you\'ll attend.');
    }
}
