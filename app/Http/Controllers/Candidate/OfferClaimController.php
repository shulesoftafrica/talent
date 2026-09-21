<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\Notifications\OtpService;
use App\Services\Onboarding\HrOnboardingGateway;
use App\Services\Phone\PhoneNumberNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The link in the offer message for a candidate who applied through the
 * school's own careers page (REQ-HRX-04). The candidate proves they are the
 * applicant with a one-time code sent to the phone on the application; then
 * their Talent account is found or created and the offer opens. Invalid or
 * expired links show a clear message and no data.
 */
class OfferClaimController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly HrOnboardingGateway $origin,
        private readonly PhoneNumberNormalizer $phones,
    ) {
    }

    /** The unexpired origin application for this link, or null (all failures look the same). */
    private function offerFor(string $module, string $token): ?object
    {
        abort_unless(in_array($module, HrOnboardingGateway::MODULES, true), 404);

        return DB::connection($module)->table('applications')
            ->where('offer_token', $token)
            ->where('offer_token_expires_at', '>', now())
            ->first();
    }

    /** The application's phone as digits with the country code, or null. */
    private function phoneOf(object $hr): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $hr->phone);

        return $digits !== '' ? $digits : null;
    }

    private function mask(string $digits): string
    {
        return '••••'.substr($digits, -3);
    }

    public function show(string $module, string $token): View|RedirectResponse
    {
        $hr = $this->offerFor($module, $token);
        if (! $hr) {
            return view('candidate.offer-claim', ['stage' => 'invalid', 'module' => $module, 'token' => $token, 'masked' => null]);
        }

        // Already signed in: no code needed.
        if ($candidate = Auth::guard('candidate')->user()) {
            $application = $this->origin->claim($candidate, $module, $token);

            return $application
                ? redirect()->route('candidate.applications.offer', $application)
                : view('candidate.offer-claim', ['stage' => 'mismatch', 'module' => $module, 'token' => $token, 'masked' => null]);
        }

        $phone = $this->phoneOf($hr);

        return view('candidate.offer-claim', [
            'stage' => session('claim_sent_'.$token) ? 'code' : 'send',
            'module' => $module, 'token' => $token, 'masked' => $phone ? $this->mask($phone) : null,
        ]);
    }

    public function send(string $module, string $token): RedirectResponse
    {
        $hr = $this->offerFor($module, $token);
        $phone = $hr ? $this->phoneOf($hr) : null;
        abort_unless($phone, 404);

        $delivered = $this->otp->send($phone, 'login', $hr->email ?: null);

        return redirect()->route('offers.claim', [$module, $token])
            ->with($delivered ? 'claim_sent_'.$token : 'claim_error', $delivered ? true : "We couldn't send the code right now. Please try again in a few minutes.");
    }

    public function verify(Request $request, string $module, string $token): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $hr = $this->offerFor($module, $token);
        $phone = $hr ? $this->phoneOf($hr) : null;
        abort_unless($phone, 404);

        $result = $this->otp->verify($phone, trim($data['code']), 'login');
        if ($result !== 'success') {
            $message = match ($result) {
                'too_many_attempts' => 'Too many incorrect attempts. Please request a new code.',
                'expired_or_missing' => 'That code has expired. Please request a new code.',
                default => 'That code is incorrect. Please try again.',
            };

            return redirect()->route('offers.claim', [$module, $token])->with('claim_sent_'.$token, $result === 'invalid_code')->with('claim_error', $message);
        }

        $candidate = $this->findOrCreateCandidate($hr, $phone);
        Auth::guard('candidate')->login($candidate, remember: true);
        $request->session()->regenerate();

        $application = $this->origin->claim($candidate, $module, $token, 'offer_claim');
        abort_unless($application, 403);

        return redirect()->route('candidate.applications.offer', $application);
    }

    /** Matches by phone, then email (the same rules the HR module uses for staff accounts); creates only when neither matches. */
    private function findOrCreateCandidate(object $hr, string $phoneDigits): Candidate
    {
        $national = substr($phoneDigits, -9);
        $candidate = Candidate::query()->whereRaw("regexp_replace(phone, '\\D', '', 'g') LIKE ?", ['%'.$national])->first();

        if (! $candidate && $hr->email) {
            $candidate = Candidate::query()->whereRaw('lower(email) = ?', [strtolower(trim($hr->email))])->first();
        }

        return $candidate ?? Candidate::query()->create([
            'full_name' => $hr->name,
            'email' => $hr->email ?: null,
            'phone' => $this->phones->normalizeFreeform($hr->phone) ?? '+'.$phoneDigits,
        ]);
    }
}
