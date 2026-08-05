<?php

namespace App\Services\Notifications;

use App\Models\CandidateOtp;

/**
 * Generates, stores, and delivers OTP codes for candidate phone/email
 * verification — both channels now go through the single Unified
 * Notification Client. When the identifier is a phone number, WhatsApp
 * (official Meta channel, via the notification service) is the primary
 * delivery — a copy also goes to the candidate's email when one is known
 * (e.g. parsed from their CV), for reach in case WhatsApp delivery fails
 * or they don't check it. SMS is intentionally not used for now.
 */
class OtpService
{
    private const CODE_TTL_MINUTES = 5;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly UnifiedNotificationClient $notifications)
    {
    }

    /**
     * @param string $purpose login|verify_phone|verify_email
     * @param string|null $candidateEmail When $phoneOrEmail is a phone number, an
     *                     email address to also send the code to (e.g. from the
     *                     candidate's CV or saved profile). Ignored when
     *                     $phoneOrEmail is itself an email — nothing to add.
     */
    public function send(string $phoneOrEmail, string $purpose = 'login', ?string $candidateEmail = null): CandidateOtp
    {
        $isEmail = str_contains($phoneOrEmail, '@');
        $channel = $isEmail ? 'email' : 'whatsapp';

        $code = (string) random_int(100000, 999999);

        $otp = CandidateOtp::create([
            'phone_or_email' => $phoneOrEmail,
            'code' => $code,
            'purpose' => $purpose,
            'channel' => $channel,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        $this->deliver($phoneOrEmail, $code, $isEmail, $candidateEmail);

        return $otp;
    }

    public function resend(string $phoneOrEmail, string $purpose = 'login', ?string $candidateEmail = null): CandidateOtp
    {
        return $this->send($phoneOrEmail, $purpose, $candidateEmail);
    }

    /**
     * A wrong-code failure and an attempts-exhausted failure need different
     * messages — once attempts run out, no code (not even the genuinely
     * correct one) will ever verify against this row again, so telling the
     * candidate to just "try again" without also telling them to request a
     * new code leaves them stuck retrying something that can never succeed.
     *
     * @return 'success'|'invalid_code'|'too_many_attempts'|'expired_or_missing'
     */
    public function verify(string $phoneOrEmail, string $code, string $purpose = 'login'): string
    {
        $otp = CandidateOtp::where('phone_or_email', $phoneOrEmail)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$otp || $otp->expires_at->isPast()) {
            return 'expired_or_missing';
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return 'too_many_attempts';
        }

        if (!hash_equals($otp->code, $code)) {
            $otp->increment('attempts');
            return 'invalid_code';
        }

        $otp->forceFill(['verified_at' => now()])->save();

        return 'success';
    }

    /**
     * If the identifier is an email, that's the only channel. If it's a
     * phone, WhatsApp (official) is primary and a copy also goes to the
     * candidate's known email, if any — each channel fails independently
     * without blocking the other.
     */
    private function deliver(string $phoneOrEmail, string $code, bool $isEmail, ?string $candidateEmail): void
    {
        $message = "Your ShuleSoft Talent Network verification code is: {$code}. It expires in " . self::CODE_TTL_MINUTES . ' minutes.';

        if ($isEmail) {
            $this->sendEmail($phoneOrEmail, $message);

            return;
        }

        $this->notifications->send([
            'channel' => 'whatsapp',
            'to' => $phoneOrEmail,
            'message' => $message,
            'type' => 'official',
        ]);

        if ($candidateEmail) {
            $this->sendEmail($candidateEmail, $message);
        }
    }

    private function sendEmail(string $to, string $message): void
    {
        $this->notifications->send([
            'channel' => 'email',
            'to' => $to,
            'subject' => 'Your ShuleSoft Talent Network verification code',
            'message' => $message,
        ]);
    }
}
