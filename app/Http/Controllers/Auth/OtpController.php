<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Constant\ReferCity;
use App\Services\Location\CountryDetectionService;
use App\Services\Notifications\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly CountryDetectionService $countryDetection,
    ) {
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_or_email' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(['login', 'signup'])],
        ]);

        if ($data['purpose'] === 'login') {
            $exists = Candidate::query()
                ->where('phone', $data['phone_or_email'])
                ->orWhere('email', $data['phone_or_email'])
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => "We couldn't find a profile for that phone/email. Try \"I'm New\" instead.",
                ], 422);
            }
        }

        $this->otp->send($data['phone_or_email'], $data['purpose']);

        return response()->json(['success' => true]);
    }

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_or_email' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(['login', 'signup'])],
        ]);

        $this->otp->resend($data['phone_or_email'], $data['purpose']);

        return response()->json(['success' => true]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_or_email' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'purpose' => ['required', Rule::in(['login', 'signup'])],
            'full_name' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
        ]);

        $valid = $this->otp->verify($data['phone_or_email'], $data['code'], $data['purpose']);

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'That code is incorrect or has expired. Please try again.',
            ], 422);
        }

        $cityName = !empty($data['city_id']) ? ReferCity::find($data['city_id'])?->city : null;

        $candidate = $data['purpose'] === 'signup'
            ? $this->completeSignup($request, $data['phone_or_email'], $data['full_name'] ?? null, $data['country_id'] ?? null, $cityName)
            : Candidate::where('phone', $data['phone_or_email'])->orWhere('email', $data['phone_or_email'])->firstOrFail();

        $isEmail = str_contains($data['phone_or_email'], '@');
        $candidate->forceFill([
            $isEmail ? 'email_verified_at' : 'phone_verified_at' => now(),
            'last_login_at' => now(),
        ])->save();

        Auth::guard('candidate')->login($candidate, remember: true);
        $request->session()->regenerate();
        $request->session()->forget('onboarding');

        return response()->json([
            'success' => true,
            'redirect' => route('candidate.jobs'),
        ]);
    }

    /**
     * Creates the candidate record (and any experiences/educations/skills
     * parsed from the CV) from the onboarding session data captured in
     * Auth\OnboardingController::uploadCv.
     */
    private function completeSignup(Request $request, string $phone, ?string $fullNameOverride, ?int $countryIdOverride = null, ?string $cityNameOverride = null): Candidate
    {
        $existing = Candidate::where('phone', $phone)->first();
        if ($existing) {
            return $existing;
        }

        $onboarding = $request->session()->get('onboarding', []);
        $parsed = $onboarding['parsed'] ?? [];

        // The candidate's chosen dropdown value wins if they set one;
        // otherwise re-detect from the phone number they actually just
        // verified with (which may differ from whatever the CV happened to
        // contain), falling back to the CV's location text.
        $countryId = $countryIdOverride ?? $this->countryDetection->detect($phone, $parsed['location'] ?? null);

        return DB::transaction(function () use ($onboarding, $parsed, $phone, $fullNameOverride, $countryId, $cityNameOverride) {
            $candidate = Candidate::create([
                'full_name' => $fullNameOverride ?: ($parsed['full_name'] ?? 'New Candidate'),
                'email' => $parsed['email'] ?? null,
                'phone' => $phone,
                'current_location' => $cityNameOverride ?: ($parsed['location'] ?? null),
                'country_id' => $countryId,
            ]);

            if (!empty($onboarding['cv_stored_path'])) {
                $permanentPath = "cv-uploads/{$candidate->id}/" . basename($onboarding['cv_stored_path']);
                if (Storage::disk('local')->exists($onboarding['cv_stored_path'])) {
                    Storage::disk('local')->move($onboarding['cv_stored_path'], $permanentPath);
                }
                $candidate->forceFill([
                    'cv_path' => $permanentPath,
                    'cv_raw_text' => $onboarding['cv_raw_text'] ?? null,
                    'cv_parsed_at' => now(),
                ])->save();
            }

            foreach ($parsed['experiences'] ?? [] as $experience) {
                $candidate->experiences()->create([
                    'title' => $experience['title'] ?? 'Role',
                    'organization' => $experience['organization'] ?? 'Employer',
                    'location' => $experience['location'] ?? null,
                    'start_date' => $this->parseDateGuess($experience['start_date'] ?? null),
                    'end_date' => $this->parseDateGuess($experience['end_date'] ?? null),
                    'is_current' => (bool) ($experience['is_current'] ?? false),
                    'tasks' => $experience['tasks'] ?? [],
                ]);
            }

            foreach ($parsed['educations'] ?? [] as $education) {
                $candidate->educations()->create([
                    'degree' => $education['degree'] ?? 'Qualification',
                    'school' => $education['school'] ?? 'Institution',
                    'start_year' => $education['start_year'] ?? null,
                    'end_year' => $education['end_year'] ?? null,
                ]);
            }

            foreach ($parsed['skills'] ?? [] as $skill) {
                if (is_string($skill) && $skill !== '') {
                    $candidate->skills()->create(['name' => $skill]);
                }
            }

            foreach ($parsed['certifications'] ?? [] as $certification) {
                if (!empty($certification['name'])) {
                    $candidate->certifications()->create([
                        'name' => $certification['name'],
                        'issuer' => $certification['issuer'] ?? null,
                    ]);
                }
            }

            return $candidate;
        });
    }

    private function parseDateGuess(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
