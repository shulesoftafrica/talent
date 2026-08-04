<?php

namespace App\Services\Phone;

use App\Models\Candidate;
use App\Models\Constant\ReferCountry;
use App\Services\Location\CountryDetectionService;

/**
 * Normalizes phone numbers into a single consistent stored format:
 * "+<calling code><national number>", digits only aside from the leading
 * '+' — no spaces, dashes, parens, or a stray local trunk "0". Candidate
 * data has historically been stored however it was typed (some with a
 * calling code, some purely local, some with spacing/punctuation), which
 * makes both verification workflows and outbound SMS/WhatsApp delivery
 * unreliable.
 */
class PhoneNumberNormalizer
{
    public function __construct(private readonly CountryDetectionService $countryDetection)
    {
    }

    /**
     * Best-effort normalize a freeform number (as typed by a candidate or
     * extracted from a CV), guessing a country only when the number itself
     * doesn't already carry a calling code. $locationHint (e.g. CV address
     * text) and $preferredCountryId (e.g. already-detected/selected country)
     * are tried in order before falling back to Tanzania.
     */
    public function normalizeFreeform(?string $raw, ?string $locationHint = null, ?int $preferredCountryId = null): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = $this->digitsOnly($raw);

        if ($digits === '') {
            return null;
        }

        // "00" international prefix is equivalent to a leading '+'.
        if (!$hasPlus && str_starts_with($digits, '00')) {
            $hasPlus = true;
            $digits = substr($digits, 2);
        }

        if ($hasPlus) {
            return '+' . $digits;
        }

        // A number that doesn't start with a local trunk "0" and already
        // resolves to a real calling code is treated as already-coded
        // (e.g. "255782371125") rather than local.
        if (!str_starts_with($digits, '0') && $this->countryDetection->detectFromPhone($digits)) {
            return '+' . $digits;
        }

        $national = ltrim($digits, '0');

        if ($national === '') {
            return null;
        }

        $countryId = $preferredCountryId
            ?? $this->countryDetection->detectFromLocation($locationHint)
            ?? Candidate::TANZANIA_COUNTRY_ID;

        $callingCode = ReferCountry::find($countryId)?->country_code
            ?? ReferCountry::find(Candidate::TANZANIA_COUNTRY_ID)?->country_code
            ?? '255';
        $diallingCode = '+' . $callingCode;

        return $this->combine($diallingCode, $national);
    }

    /**
     * Combine an explicit calling code (e.g. "+255", from a candidate's own
     * country-code selection) with a national number, stripping a redundant
     * leading trunk "0" if the candidate typed one anyway.
     */
    public function combine(string $diallingCode, string $nationalNumber): string
    {
        $code = trim($diallingCode);
        $code = str_starts_with($code, '+') ? $code : '+' . $this->digitsOnly($code);

        $national = ltrim($this->digitsOnly($nationalNumber), '0');

        return $code . $national;
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
