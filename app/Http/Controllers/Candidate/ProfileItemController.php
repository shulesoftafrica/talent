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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileItemController extends Controller
{
    public function updatePersonalInfo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'current_employer' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $data['current_location'] = !empty($data['city_id']) ? ReferCity::find($data['city_id'])?->city : null;
        unset($data['city_id']);

        // Changing employer invalidates any prior employer verification — it
        // no longer describes who they say they currently work for.
        if ($data['current_employer'] !== $candidate->current_employer) {
            $data['current_employer_verified'] = false;
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
        $data = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $candidate = Auth::guard('candidate')->user();
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

        $data = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        if ($request->hasFile('file')) {
            Storage::disk('local')->delete($portfolioItem->file_path);
            $file = $request->file('file');
            $data['file_path'] = $file->store("portfolio/{$portfolioItem->candidate_id}", 'local');
            $data['file_size_bytes'] = $file->getSize();
        }

        $portfolioItem->update($data);

        return back()->with('status', 'Portfolio item updated.');
    }

    public function destroyPortfolioItem(CandidatePortfolioItem $portfolioItem): RedirectResponse
    {
        $this->authorizeOwner($portfolioItem);
        Storage::disk('local')->delete($portfolioItem->file_path);
        $portfolioItem->delete();

        return back()->with('status', 'Portfolio item removed.');
    }

    private function validateExperience(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'organization' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_current'] = $request->boolean('is_current');
        $data['tasks'] = $this->linesToArray($data['tasks'] ?? null);

        return $data;
    }

    private function validateEducation(Request $request): array
    {
        return $request->validate([
            'degree' => ['required', 'string', 'max:255'],
            'school' => ['required', 'string', 'max:255'],
            'start_year' => ['nullable', 'string', 'max:10'],
            'end_year' => ['nullable', 'string', 'max:10'],
        ]);
    }

    private function validateCertification(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
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
