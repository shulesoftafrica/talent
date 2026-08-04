<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Constant\ReferCity;
use App\Services\Location\CountryDetectionService;
use App\Services\Notifications\OtpService;
use App\Services\Phone\PhoneNumberNormalizer;
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
        private readonly PhoneNumberNormalizer $phoneNormalizer,
    ) {
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_or_email' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(['login', 'signup'])],
        ]);

        if ($data['purpose'] === 'login' && !$this->findCandidateByIdentifier($data['phone_or_email'])) {
            return response()->json([
                'success' => false,
                'message' => "We couldn't find a profile for that phone/email. Try \"I'm New\" instead.",
            ], 422);
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

        if ($data['purpose'] === 'signup') {
            $candidate = $this->completeSignup($request, $data['phone_or_email'], $data['full_name'] ?? null, $data['country_id'] ?? null, $cityName);
        } else {
            $candidate = $this->findCandidateByIdentifier($data['phone_or_email']);
            abort_if(!$candidate, 404);
        }

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
        $existing = $this->findCandidateByIdentifier($phone);
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

        // Store phone consistently as "+<calling code><national number>"
        // regardless of how the candidate typed it or what the CV
        // contained — falls back to the CV's location text, then Tanzania,
        // if no calling code and no detected country are available.
        $normalizedPhone = $this->phoneNormalizer->normalizeFreeform($phone, $parsed['location'] ?? null, $countryId) ?? $phone;

        return DB::transaction(function () use ($onboarding, $parsed, $normalizedPhone, $fullNameOverride, $countryId, $cityNameOverride) {
            $candidate = Candidate::create([
                'full_name' => $fullNameOverride ?: ($parsed['full_name'] ?? 'New Candidate'),
                'email' => $parsed['email'] ?? null,
                'phone' => $normalizedPhone,
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

    /**
     * Stored phone numbers are normalized to "+<calling code><national
     * number>", but a candidate may still type the same number in whatever
     * local format they originally used (or in the format it had before
     * normalization/backfill) — match on the trailing national-significant
     * digits rather than requiring an exact string match, so login/signup
     * keep resolving to the same account regardless of formatting.
     */
    private function findCandidateByIdentifier(string $identifier): ?Candidate
    {
        if (str_contains($identifier, '@')) {
            return Candidate::where('email', $identifier)->first();
        }

        $national = ltrim(preg_replace('/\D/', '', $identifier) ?? '', '0');

        if ($national === '') {
            return null;
        }

        return Candidate::whereRaw("regexp_replace(phone, '\\D', '', 'g') LIKE ?", ['%' . $national])->first();
    }
}
