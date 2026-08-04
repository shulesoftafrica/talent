<?php

namespace App\Services\Location;

use App\Models\Constant\ReferCity;
use App\Models\Constant\ReferCountry;

/**
 * Best-effort country_id detection from a phone number (primary — reliable,
 * since calling codes are unambiguous) or free-text location (fallback —
 * fuzzy, only used when the phone doesn't resolve). Never guesses when
 * nothing matches; a candidate with no signal detected simply stays
 * country_id=null (a compliance-adjacent field like this shouldn't default
 * to any particular country).
 */
class CountryDetectionService
{
    public function detect(?string $phone, ?string $locationText = null): ?int
    {
        return $this->detectFromPhone($phone) ?? $this->detectFromLocation($locationText);
    }

    public function detectFromPhone(?string $phone): ?int
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        // Calling codes are 1-3 digits — try longest prefix first so e.g.
        // Tanzania's "255" isn't shadowed by a shorter accidental match.
        for ($length = 3; $length >= 1; $length--) {
            $prefix = substr($digits, 0, $length);

            $countryId = ReferCountry::where('country_code', $prefix)->orderBy('id')->value('id');

            if ($countryId) {
                return $countryId;
            }
        }

        return null;
    }

    public function detectFromLocation(?string $locationText): ?int
    {
        $location = trim((string) $locationText);

        if (mb_strlen($location) < 3) {
            return null;
        }

        $cityCountryId = ReferCity::whereRaw('city ILIKE ?', ['%' . $location . '%'])
            ->orWhereRaw('? ILIKE (\'%\' || city || \'%\')', [$location])
            ->value('countryid');

        if ($cityCountryId) {
            return $cityCountryId;
        }

        return ReferCountry::whereRaw('? ILIKE (\'%\' || country || \'%\')', [$location])->value('id');
    }
}
