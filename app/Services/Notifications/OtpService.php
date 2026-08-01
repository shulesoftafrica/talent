<?php

namespace App\Services\Notifications;

use App\Models\CandidateOtp;
use App\Services\WhatsApp\MetaWhatsAppService;

/**
 * Generates, stores, and delivers OTP codes for candidate phone/email
 * verification. Email + SMS go through the Unified Notification Client;
 * WhatsApp goes through the direct Meta Cloud API client instead, per
 * product direction (see MetaWhatsAppService).
 */
class OtpService
{
    private const CODE_TTL_MINUTES = 5;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly UnifiedNotificationClient $notifications,
        private readonly MetaWhatsAppService $whatsApp,
    ) {
    }

    /**
     * @param string $purpose login|verify_phone|verify_email
     */
    public function send(string $phoneOrEmail, string $purpose = 'login'): CandidateOtp
    {
        $isEmail = str_contains($phoneOrEmail, '@');
        $channel = $isEmail ? 'email' : 'sms+whatsapp';

        $code = (string) random_int(100000, 999999);

        $otp = CandidateOtp::create([
            'phone_or_email' => $phoneOrEmail,
            'code' => $code,
            'purpose' => $purpose,
            'channel' => $channel,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        $this->deliver($phoneOrEmail, $code, $isEmail);

        return $otp;
    }

    public function resend(string $phoneOrEmail, string $purpose = 'login'): CandidateOtp
    {
        return $this->send($phoneOrEmail, $purpose);
    }

    /**
     * @return bool True if the code was valid and is now marked verified.
     */
    public function verify(string $phoneOrEmail, string $code, string $purpose = 'login'): bool
    {
        $otp = CandidateOtp::where('phone_or_email', $phoneOrEmail)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS || $otp->expires_at->isPast()) {
            return false;
        }

        if (!hash_equals($otp->code, $code)) {
            $otp->increment('attempts');
            return false;
        }

        $otp->forceFill(['verified_at' => now()])->save();

        return true;
    }

    /**
     * Email OTP goes out over the Unified Notification Client. Phone OTP goes
     * out over BOTH SMS (Unified Notification Client) and WhatsApp (direct
     * Meta Cloud API, per product direction) with the same code, for reach —
     * each channel fails independently without blocking the other.
     */
    private function deliver(string $phoneOrEmail, string $code, bool $isEmail): void
    {
        $message = "Your ShuleSoft Talent Network verification code is: {$code}. It expires in " . self::CODE_TTL_MINUTES . ' minutes.';

        if ($isEmail) {
            $this->notifications->send([
                'schema_name' => 'talent',
                'channel' => 'email',
                'to' => $phoneOrEmail,
                'subject' => 'Your ShuleSoft Talent Network verification code',
                'message' => $message,
            ]);
            return;
        }

        $this->notifications->send([
            'schema_name' => 'talent',
            'channel' => 'sms',
            'to' => $phoneOrEmail,
            'message' => $message,
        ]);

        $this->whatsApp->sendOtpTemplate($phoneOrEmail, $code);
    }
}
