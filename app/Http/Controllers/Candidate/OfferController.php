<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Services\Onboarding\HrOnboardingGateway;
use App\Services\Onboarding\OfferAnswerRejectedException;
use App\Services\Onboarding\OfferService;
use App\Services\Onboarding\OnboardingVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The candidate's formal job offer (REQ-HRX-04): read the terms and the letter,
 * accept with a typed name and a tick, or decline with a reason. An answer is
 * written only to Talent's own `offer_responses`; the HR module applies it.
 * Only the owning candidate may see or answer an offer (403 otherwise).
 */
class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offers, private readonly HrOnboardingGateway $origin)
    {
    }

    private function candidate(): Candidate
    {
        return Auth::guard('candidate')->user();
    }

    /** @return array{hr: object, state: string, response: ?\App\Models\OfferResponse} */
    private function owned(Application $application): array
    {
        abort_unless($application->candidate_id === $this->candidate()->id, 403);
        $snapshot = $this->offers->snapshot($application);
        abort_unless($snapshot, 404);

        return $snapshot;
    }

    public function show(Application $application): View
    {
        $snapshot = $this->owned($application);

        return view('candidate.offer', [
            'candidate' => $this->candidate(),
            'application' => $application,
            'hr' => $snapshot['hr'],
            'state' => $snapshot['state'],
            'response' => $snapshot['response'],
            'reasons' => OfferService::DECLINE_REASONS,
            'hasLetter' => ! empty($snapshot['hr']->offer_letter_path),
            'job' => $application->jobPosting(),
        ]);
    }

    /** The offer letter PDF, through a 10-minute link, for the owning candidate only. */
    public function letter(Application $application): RedirectResponse
    {
        $snapshot = $this->owned($application);
        $url = $this->offers->letterUrl($snapshot['hr'], app(OnboardingVault::class));
        abort_unless($url, 404);

        return redirect()->away($url);
    }

    public function accept(Request $request, Application $application): RedirectResponse
    {
        $this->owned($application);
        $input = $request->validate(['signed_name' => ['required', 'string', 'min:3', 'max:150'], 'confirmed' => ['accepted']], [
            'confirmed.accepted' => 'Tick the box to confirm you have read and accept the terms of this offer.',
        ]);

        try {
            $this->offers->respond($application, $this->candidate(), 'accept', $input, $request->ip(), $request->userAgent());
        } catch (OfferAnswerRejectedException $e) {
            return back()->withErrors(['signed_name' => $e->getMessage()])->withInput();
        }

        return redirect()->route('candidate.applications.offer', $application);
    }

    public function decline(Request $request, Application $application): RedirectResponse
    {
        $this->owned($application);
        $input = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_keys(OfferService::DECLINE_REASONS))],
            'reason_detail' => ['nullable', 'string', 'max:400', 'required_if:reason,other'],
        ]);

        try {
            $this->offers->respond($application, $this->candidate(), 'decline', $input, $request->ip(), $request->userAgent());
        } catch (OfferAnswerRejectedException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return redirect()->route('candidate.applications.offer', $application);
    }
}
