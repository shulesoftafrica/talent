<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateCertification;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateHobby;
use App\Models\CandidatePortfolioItem;
use App\Models\CandidateSkill;
use App\Models\Constant\ReferCity;
use App\Models\Constant\ReferCountry;
use App\Services\Phone\PhoneNumberNormalizer;
use App\Services\Uploads\UploadSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileItemController extends Controller
{
    /** Portfolio types a candidate can pick, matching the mockup's list. */
    private const PORTFOLIO_TYPES = ['Lesson Plan', 'Teaching Video', 'Presentation Slides', 'Project', 'Research', 'Document'];

    /**
     * The only type that doesn't take a file upload — videos are never
     * accepted as uploads (large, expensive to store, awkward to scan for
     * malicious content); candidates paste a YouTube/Vimeo link instead.
     */
    private const VIDEO_TYPE = 'Teaching Video';

    private const ALLOWED_VIDEO_HOSTS = ['youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com'];

    /** Safe document/image types only — no video, no executables/scripts. */
    private const PORTFOLIO_MIMES = 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png';

    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNormalizer,
        private readonly UploadSecurityService $uploadSecurity,
    ) {
    }

    public function updatePersonalInfo(Request $request): RedirectResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('candidates', 'email')->ignore($candidate->id)],
            'phone_country_id' => ['required', 'integer'],
            'phone_national' => ['required', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'current_employer' => ['nullable', 'string', 'max:255'],
        ]);

        $callingCode = ReferCountry::find($data['phone_country_id'])?->country_code;

        if (!$callingCode) {
            return back()->withErrors(['phone_country_id' => __('profile.phone_country_invalid')])->withInput();
        }

        $normalizedPhone = $this->phoneNormalizer->combine($callingCode, $data['phone_national']);

        if (Candidate::where('phone', $normalizedPhone)->where('id', '!=', $candidate->id)->exists()) {
            return back()->withErrors(['phone_national' => __('profile.phone_taken')])->withInput();
        }

        // A changed number hasn't actually been verified by this candidate
        // via OTP, so it can't keep the old number's verified status.
        // phone_verified_at isn't mass-assignable, so it's set separately.
        $phoneChanged = $normalizedPhone !== $candidate->phone;

        $data['phone'] = $normalizedPhone;
        unset($data['phone_country_id'], $data['phone_national']);

        $data['current_location'] = !empty($data['city_id']) ? ReferCity::find($data['city_id'])?->city : null;
        unset($data['city_id']);

        // Changing employer invalidates any prior employer verification — it
        // no longer describes who they say they currently work for.
        if ($data['current_employer'] !== $candidate->current_employer) {
            $data['current_employer_verified'] = false;
        }

        if ($phoneChanged) {
            $candidate->forceFill(['phone_verified_at' => null]);
        }

        $candidate->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function storeExperience(Request $request): RedirectResponse
    {
        $data = $this->validateExperience($request);

        Auth::guard('candidate')->user()->experiences()->create($data);

        return back()->with('status', 'Experience added.');
    }

    public function updateExperience(Request $request, CandidateExperience $experience): RedirectResponse
    {
        $this->authorizeOwner($experience);

        $data = $this->validateExperience($request);

        // Editing a verified entry's facts means it no longer reflects what
        // was actually verified, so re-verification is required.
        if ($experience->is_verified) {
            $data['is_verified'] = false;
            $data['verified_by'] = null;
            $data['verified_at'] = null;
        }

        $experience->update($data);

        return back()->with('status', 'Experience updated.');
    }

    public function destroyExperience(CandidateExperience $experience): RedirectResponse
    {
        $this->authorizeOwner($experience);
        $experience->delete();

        return back()->with('status', 'Experience removed.');
    }

    public function storeEducation(Request $request): RedirectResponse
    {
        $data = $this->validateEducation($request);

        Auth::guard('candidate')->user()->educations()->create($data);

        return back()->with('status', 'Education added.');
    }

    public function updateEducation(Request $request, CandidateEducation $education): RedirectResponse
    {
        $this->authorizeOwner($education);

        $data = $this->validateEducation($request);

        if ($education->status === 'Verified') {
            $data['status'] = 'Not Verified';
            $data['verified_by'] = null;
            $data['verified_at'] = null;
        }

        $education->update($data);

        return back()->with('status', 'Education updated.');
    }

    public function destroyEducation(CandidateEducation $education): RedirectResponse
    {
        $this->authorizeOwner($education);
        $education->delete();

        return back()->with('status', 'Education removed.');
    }

    public function storeCertification(Request $request): RedirectResponse
    {
        $data = $this->validateCertification($request);

        Auth::guard('candidate')->user()->certifications()->create($data);

        return back()->with('status', 'Certification added.');
    }

    public function updateCertification(Request $request, CandidateCertification $certification): RedirectResponse
    {
        $this->authorizeOwner($certification);

        $data = $this->validateCertification($request);

        if ($certification->status === 'Verified') {
            $data['status'] = 'Not Verified';
        }

        $certification->update($data);

        return back()->with('status', 'Certification updated.');
    }

    public function destroyCertification(CandidateCertification $certification): RedirectResponse
    {
        $this->authorizeOwner($certification);
        $certification->delete();

        return back()->with('status', 'Certification removed.');
    }

    public function storeSkill(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        Auth::guard('candidate')->user()->skills()->create($data);

        return back()->with('status', 'Skill added.');
    }

    public function updateSkill(Request $request, CandidateSkill $skill): RedirectResponse
    {
        $this->authorizeOwner($skill);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        if ($skill->is_verified) {
            $data['is_verified'] = false;
        }

        $skill->update($data);

        return back()->with('status', 'Skill updated.');
    }

    public function destroySkill(CandidateSkill $skill): RedirectResponse
    {
        $this->authorizeOwner($skill);
        $skill->delete();

        return back()->with('status', 'Skill removed.');
    }

    public function storeHobby(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        Auth::guard('candidate')->user()->hobbies()->create($data);

        return back()->with('status', 'Hobby added.');
    }

    public function updateHobby(Request $request, CandidateHobby $hobby): RedirectResponse
    {
        $this->authorizeOwner($hobby);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $hobby->update($data);

        return back()->with('status', 'Hobby updated.');
    }

    public function destroyHobby(CandidateHobby $hobby): RedirectResponse
    {
        $this->authorizeOwner($hobby);
        $hobby->delete();

        return back()->with('status', 'Hobby removed.');
    }

    public function storePortfolioItem(Request $request): RedirectResponse
    {
        $data = $this->validatePortfolioItem($request);

        $candidate = Auth::guard('candidate')->user();

        if ($data['type'] === self::VIDEO_TYPE) {
            $candidate->portfolioItems()->create([
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'external_url' => $data['external_url'],
            ]);

            return back()->with('status', 'Portfolio item added.');
        }

        $file = $request->file('file');
        $path = $file->store("portfolio/{$candidate->id}", 'local');

        $candidate->portfolioItems()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'file_size_bytes' => $file->getSize(),
        ]);

        return back()->with('status', 'Portfolio item added.');
    }

    public function updatePortfolioItem(Request $request, CandidatePortfolioItem $portfolioItem): RedirectResponse
    {
        $this->authorizeOwner($portfolioItem);

        $data = $this->validatePortfolioItem($request, forFileRequired: false);

        if ($data['type'] === self::VIDEO_TYPE) {
            if ($portfolioItem->file_path) {
                Storage::disk('local')->delete($portfolioItem->file_path);
            }
            $data['file_path'] = null;
            $data['file_size_bytes'] = null;
        } else {
            // Always clear a stale link left over from a prior 'Teaching
            // Video' type, even if this edit doesn't replace the file.
            $data['external_url'] = null;

            if ($request->hasFile('file')) {
                if ($portfolioItem->file_path) {
                    Storage::disk('local')->delete($portfolioItem->file_path);
                }
                $file = $request->file('file');
                $data['file_path'] = $file->store("portfolio/{$portfolioItem->candidate_id}", 'local');
                $data['file_size_bytes'] = $file->getSize();
            }
        }

        $portfolioItem->update($data);

        return back()->with('status', 'Portfolio item updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePortfolioItem(Request $request, bool $forFileRequired = true): array
    {
        $isVideo = $request->input('type') === self::VIDEO_TYPE;

        $data = $request->validate([
            'type' => ['required', Rule::in(self::PORTFOLIO_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => $isVideo
                ? ['prohibited']
                : [$forFileRequired ? 'required' : 'nullable', 'file', 'mimes:' . self::PORTFOLIO_MIMES, 'max:20480'],
            'external_url' => $isVideo
                ? ['required', 'url:https,http', 'max:500']
                : ['prohibited'],
        ]);

        if ($isVideo && !$this->isAllowedVideoHost($data['external_url'])) {
            throw ValidationException::withMessages([
                'external_url' => 'Please paste a YouTube or Vimeo link.',
            ]);
        }

        if (!$isVideo && $request->hasFile('file')) {
            if ($unsafeReason = $this->uploadSecurity->check($request->file('file'))) {
                throw ValidationException::withMessages(['file' => $unsafeReason]);
            }
        }

        return $data;
    }

    private function isAllowedVideoHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, self::ALLOWED_VIDEO_HOSTS, true);
    }

    public function destroyPortfolioItem(CandidatePortfolioItem $portfolioItem): RedirectResponse
    {
        $this->authorizeOwner($portfolioItem);
        Storage::disk('local')->delete($portfolioItem->file_path);
        $portfolioItem->delete();

        return back()->with('status', 'Portfolio item removed.');
    }

    /** Earliest plausible date for anything in a candidate's work/education history. */
    private const EARLIEST_HISTORY_DATE = '1950-01-01';

    private function validateExperience(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'organization' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date', 'after_or_equal:' . self::EARLIEST_HISTORY_DATE, 'before_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
            'is_current' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_current'] = $request->boolean('is_current');
        $data['tasks'] = $this->linesToArray($data['tasks'] ?? null);

        return $data;
    }

    private function validateEducation(Request $request): array
    {
        $maxYear = (int) now()->addYears(10)->year;

        return $request->validate([
            'degree' => ['required', 'string', 'max:255'],
            'school' => ['required', 'string', 'max:255'],
            'start_year' => ['nullable', 'digits:4', 'integer', 'min:1950', 'max:' . $maxYear],
            'end_year' => ['nullable', 'digits:4', 'integer', 'min:1950', 'max:' . $maxYear, 'gte:start_year'],
        ]);
    }

    private function validateCertification(Request $request): array
    {
        $maxExpiry = now()->addYears(50)->toDateString();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date', 'after_or_equal:' . self::EARLIEST_HISTORY_DATE, 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at', 'before_or_equal:' . $maxExpiry],
        ]);
    }

    /**
     * Every profile-item route is implicit-bound purely by ID, which does not
     * scope to the authenticated candidate on its own — this guards against
     * one candidate editing/deleting another's row by guessing its ID.
     */
    private function authorizeOwner(mixed $item): void
    {
        if ($item->candidate_id !== Auth::guard('candidate')->id()) {
            abort(403);
        }
    }

    private function linesToArray(?string $text): array
    {
        if (!$text) {
            return [];
        }

        return collect(explode("\n", $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
