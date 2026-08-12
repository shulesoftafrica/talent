<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Constant\ReferCity;
use App\Models\Constant\ReferCountry;
use App\Services\CareerBuilder\CareerBuilderDataService;
use App\Services\Candidates\ProfileCompletionService;
use App\Services\Location\CountryDetectionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly CareerBuilderDataService $careerBuilder,
        private readonly CountryDetectionService $countryDetection,
        private readonly ProfileCompletionService $profileCompletionService,
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
        return $this->profileCompletionService->sections($candidate);
    }
}
