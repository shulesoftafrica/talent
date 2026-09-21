<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Services\Onboarding\HrOnboardingGateway;
use App\Services\Onboarding\OfferAnswerRejectedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The candidate's formal job offer (REQ-HRX-03): see the terms, accept with a
 * typed name, or decline. Accepting opens the onboarding checklist; the staff
 * account is switched on later, by HR, once the documents are approved.
 */
class OfferController extends Controller
{
    public function __construct(private readonly HrOnboardingGateway $hr)
    {
    }

    private function candidate(): Candidate
    {
        return Auth::guard('candidate')->user();
    }

    private function ownedOffer(Application $application): object
    {
        abort_unless($application->candidate_id === $this->candidate()->id, 404);
        $hr = $this->hr->hrApplication($application);
        abort_unless($hr && ! empty($hr->offer_status), 404);

        return $hr;
    }

    /** The link in the email / WhatsApp / SMS for a candidate who applied through the school's own careers page. */
    public function claim(string $module, string $token): RedirectResponse
    {
        abort_unless(in_array($module, HrOnboardingGateway::MODULES, true), 404);

        $application = $this->hr->claim($this->candidate(), $module, $token);
        if (! $application) {
            return redirect()->route('candidate.applications.index')
                ->with('status', 'We could not match this offer to your account. Log in with the phone number or email the school has on your application.');
        }

        return redirect()->route('candidate.applications.offer', $application);
    }

    public function show(Application $application): View
    {
        $hr = $this->ownedOffer($application);

        return view('candidate.offer', [
            'candidate' => $this->candidate(),
            'application' => $application,
            'hr' => $hr,
            'state' => $this->hr->offerState($hr),
            'job' => $application->jobPosting(),
        ]);
    }

    public function accept(Request $request, Application $application): RedirectResponse
    {
        $this->ownedOffer($application);
        $data = $request->validate(['signed_name' => ['required', 'string', 'min:3', 'max:150']]);

        try {
            $this->hr->accept($application, $data['signed_name'], $request->ip(), $request->userAgent());
        } catch (OfferAnswerRejectedException $e) {
            return back()->withErrors(['signed_name' => $e->getMessage()]);
        }

        return redirect()->route('candidate.applications.onboarding', $application)->with('status', 'Offer accepted. Now upload your onboarding documents.');
    }

    public function decline(Request $request, Application $application): RedirectResponse
    {
        $this->ownedOffer($application);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->hr->decline($application, $data['reason'] ?? null, $request->ip(), $request->userAgent());
        } catch (OfferAnswerRejectedException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('candidate.applications.offer', $application)->with('status', 'You declined this offer.');
    }
}
