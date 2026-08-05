<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Constant\ReferCity;
use App\Models\Constant\ReferCountry;
use App\Services\CareerBuilder\CareerBuilderDataService;
use App\Services\Location\CountryDetectionService;
use App\Services\Verification\VerificationStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly CareerBuilderDataService $careerBuilder,
        private readonly CountryDetectionService $countryDetection,
    ) {
    }

    public function index(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user()->load([
            'experiences', 'educations', 'certifications', 'skills', 'hobbies', 'portfolioItems',
        ]);

        $profileCompletion = $this->profileCompletion($candidate);

        $countryName = $candidate->country_id ? ReferCountry::find($candidate->country_id)?->country : null;
        $initialCities = $candidate->country_id
            ? ReferCity::where('countryid', $candidate->country_id)->orderBy('city')->get(['id', 'city'])
            : collect();
        $initialCityId = $candidate->current_location
            ? $initialCities->firstWhere('city', $candidate->current_location)?->id
            : null;

        [$phoneCountryId, $phoneNational] = $this->splitPhone($candidate->phone);

        return view('candidate.profile', [
            'candidate' => $candidate,
            'profileCompletion' => $profileCompletion,
            'builder' => $this->careerBuilder->build($candidate),
            'countries' => ReferCountry::orderBy('country')->get(['id', 'country', 'country_code']),
            'countryName' => $countryName,
            'initialCities' => $initialCities,
            'initialCityId' => $initialCityId,
            'phoneCountryId' => $phoneCountryId,
            'phoneNational' => $phoneNational,
        ]);
    }

    /**
     * Split a stored "+<code><national>" phone number back into its
     * calling-code country (for pre-selecting the edit-form dropdown) and
     * the bare national number, so the candidate never has to retype their
     * whole number just to change the last few digits.
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function splitPhone(?string $phone): array
    {
        if (!$phone) {
            return [null, null];
        }

        $countryId = $this->countryDetection->detectFromPhone($phone);
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $callingCode = $countryId ? ReferCountry::find($countryId)?->country_code : null;

        $national = $callingCode && str_starts_with($digits, $callingCode)
            ? substr($digits, strlen($callingCode))
            : $digits;

        return [$countryId, $national];
    }

    /**
     * @return array<int, array{label: string, pct: int}>
     */
    private function profileCompletion(Candidate $candidate): array
    {
        $sections = [
            ['label' => 'Personal Information', 'pct' => $candidate->full_name && $candidate->current_location ? 100 : 60],
            ['label' => 'Experience', 'pct' => $candidate->experiences->isNotEmpty() ? 100 : 0],
            ['label' => 'Education', 'pct' => $candidate->educations->isNotEmpty() ? 100 : 0],
            ['label' => 'Portfolio', 'pct' => min(100, $candidate->portfolioItems->count() * 50)],
            ['label' => 'Skills', 'pct' => min(100, $candidate->skills->count() * 25)],
        ];

        // Verification is behind a global kill-switch until the business
        // launches it — showing this row (permanently stuck at 0%, since
        // nothing can be verified while it's off) would be a dead end.
        if (config('services.verification_enabled')) {
            $sections[] = ['label' => 'Verification', 'pct' => (int) $candidate->verificationItems()->where('status', VerificationStatus::VERIFIED)->count() * 17];
        }

        return $sections;
    }
}
