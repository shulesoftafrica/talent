<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\OfficerUser;
use App\Services\Notifications\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Email-OTP login for officers, alongside the existing password form —
 * added because admin.users accounts have no self-service password reset
 * anywhere in the ShuleSoft ecosystem, so a forgotten password otherwise
 * locks an officer out entirely. Reuses the same CandidateOtp-backed
 * OtpService as the candidate flow (purpose 'officer_login' keeps it a
 * separate code namespace), sending only to the email already on file for
 * that admin.users row — never anything else supplied by the requester.
 */
class OtpController extends Controller
{
    private const PURPOSE = 'officer_login';

    public function __construct(private readonly OtpService $otp)
    {
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $officer = OfficerUser::where('email', $data['email'])->first();

        if (!$officer) {
            return response()->json([
                'success' => false,
                'message' => "We couldn't find an account with that email.",
            ], 422);
        }

        if (!$officer->hasVerificationAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account does not have Talent Verification Officer access.',
            ], 422);
        }

        $this->otp->send($data['email'], self::PURPOSE);

        return response()->json(['success' => true]);
    }

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->otp->resend($data['email'], self::PURPOSE);

        return response()->json(['success' => true]);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->merge(['code' => trim((string) $request->input('code'))]);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $result = $this->otp->verify($data['email'], $data['code'], self::PURPOSE);

        if ($result !== 'success') {
            $message = match ($result) {
                'too_many_attempts' => 'Too many incorrect attempts. Please request a new code.',
                'expired_or_missing' => 'That code has expired. Please request a new code.',
                default => 'That code is incorrect. Please try again.',
            };

            return response()->json([
                'success' => false,
                'locked_out' => in_array($result, ['too_many_attempts', 'expired_or_missing'], true),
                'message' => $message,
            ], 422);
        }

        $officer = OfficerUser::where('email', $data['email'])->first();

        // Re-check existence/access rather than trusting the send()-time
        // check — access could theoretically have been revoked in the
        // window between requesting and entering the code.
        if (!$officer || !$officer->hasVerificationAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account does not have Talent Verification Officer access.',
            ], 422);
        }

        Auth::guard('officer')->login($officer, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => route('officer.dashboard'),
        ]);
    }
}
