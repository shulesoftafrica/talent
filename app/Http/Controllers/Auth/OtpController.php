<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Constant\ReferCity;
use App\Services\Applications\ApplicationService;
use App\Services\Jobs\ActiveJobsRepository;
use App\Services\Location\CountryDetectionService;
use App\Services\Notifications\OtpService;
use App\Services\Phone\PhoneNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly CountryDetectionService $countryDetection,
        private readonly PhoneNumberNormalizer $phoneNormalizer,
        private readonly ActiveJobsRepository $activeJobs,
        private readonly ApplicationService $applications,
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

        $this->otp->send($data['phone_or_email'], $data['purpose'], $this->resolveCandidateEmail($request, $data['phone_or_email'], $data['purpose']));

        return response()->json(['success' => true]);
    }

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_or_email' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(['login', 'signup'])],
        ]);

        $this->otp->resend($data['phone_or_email'], $data['purpose'], $this->resolveCandidateEmail($request, $data['phone_or_email'], $data['purpose']));

        return response()->json(['success' => true]);
    }

    /**
     * When the OTP is going out over WhatsApp (identifier is a phone
     * number), also send a copy to the candidate's email if one is known —
     * from the CV just parsed during signup, or already saved on their
     * profile for an existing candidate logging in.
     */
    private function resolveCandidateEmail(Request $request, string $identifier, string $purpose): ?string
    {
        if (str_contains($identifier, '@')) {
            return null;
        }

        if ($purpose === 'signup') {
            return $request->session()->get('onboarding.parsed.email');
        }

        return $this->findCandidateByIdentifier($identifier)?->email;
    }

    public function verify(Request $request): JsonResponse
    {
        // SMS autofill on mobile (autocomplete="one-time-code") sometimes
        // hands the input a trailing space or newline along with the digits
        // — normalize before the strict size:6 check so that doesn't cause
        // an otherwise-correct code to fail validation.
        $request->merge(['code' => trim((string) $request->input('code'))]);

        $data = $request->validate([
            'phone_or_email' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'purpose' => ['required', Rule::in(['login', 'signup'])],
            'full_name' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'apply_uuid' => ['nullable', 'string', 'max:36'],
            'apply_ref' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $this->otp->verify($data['phone_or_email'], $data['code'], $data['purpose']);

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

        $redirect = route('candidate.jobs');

        // Resume "Apply" if the candidate arrived via a public Share Vacancy
        // link and had to sign up / log in first — see resources/views/
        // public/vacancy.blade.php and landing.blade.php's verifyOtp().
        // Best-effort: a bad/expired job never blocks login itself.
        if (!empty($data['apply_uuid'])) {
            try {
                $job = $this->activeJobs->findActiveByUuid($data['apply_uuid']);

                if ($job) {
                    $application = $this->applications->apply(
                        $candidate,
                        $job['source_schema'],
                        (int) $job['id'],
                        $data['apply_ref'] ?? null,
                    );

                    $redirect = route('candidate.applications.index', ['selected' => $application->uuid, 'applied' => 1]);
                }
            } catch (\Throwable $e) {
                // Genuinely best-effort: login has already succeeded and
                // committed (Auth::login()/session()->regenerate() above),
                // so nothing from this side effect — an expired job
                // (ValidationException) or an infrastructure hiccup on the
                // origin schema — may ever surface as a login failure.
                // Still logged, since a swallowed non-validation exception
                // here would otherwise vanish with no trace.
                if (!$e instanceof ValidationException) {
                    Log::error('OtpController: post-login auto-apply failed', ['message' => $e->getMessage(), 'apply_uuid' => $data['apply_uuid']]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'redirect' => $redirect,
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
