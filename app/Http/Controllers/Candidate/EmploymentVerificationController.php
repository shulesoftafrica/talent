<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateEmploymentVerificationDoc;
use App\Models\CandidateVerificationItem;
use App\Services\Verification\FraudPreventionService;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmploymentVerificationController extends Controller
{
    public function __construct(
        private readonly FraudPreventionService $fraud,
        private readonly VerificationStatusService $statusService,
    ) {
    }

    public function show(): View
    {
        $candidate = $this->candidate();
        $item = $this->itemFor($candidate);

        return view('candidate.verification.employment', [
            'candidate' => $candidate,
            'item' => $item,
            'docs' => $this->docsFor($candidate, $item),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $candidate = $this->candidate();
        $item = $this->itemFor($candidate);
        $isSubmit = $request->input('action') === 'submit';

        if ($isSubmit && $this->fraud->hasActiveSubmissionInReview($item)) {
            return back()->withErrors(['submit' => 'This verification is already under review. Please wait for a decision before resubmitting.']);
        }

        $docs = $this->docsFor($candidate, $item);

        $rules = ['action' => ['required', 'in:draft,submit'], 'declaration' => ['required_if:action,submit']];
        foreach ($docs as $doc) {
            $rules["employer_name_{$doc->id}"] = ['nullable', 'string', 'max:255'];
            $rules["employer_address_{$doc->id}"] = ['nullable', 'string', 'max:500'];
            $rules["employer_website_{$doc->id}"] = ['nullable', 'url', 'max:255'];
            $rules["hr_email_{$doc->id}"] = ['nullable', 'email', 'max:255'];
            $rules["supervisor_name_{$doc->id}"] = ['nullable', 'string', 'max:255'];
            $rules["supervisor_email_{$doc->id}"] = ['nullable', 'email', 'max:255'];
            $rules["supervisor_phone_{$doc->id}"] = ['nullable', 'string', 'max:50'];
        }

        $data = $request->validate($rules);
        $allComplete = true;

        foreach ($docs as $doc) {
            $doc->employer_name = $data["employer_name_{$doc->id}"] ?? $doc->employer_name;
            $doc->employer_address = $data["employer_address_{$doc->id}"] ?? $doc->employer_address;
            $doc->employer_website = $data["employer_website_{$doc->id}"] ?? $doc->employer_website;
            $doc->hr_email = $data["hr_email_{$doc->id}"] ?? $doc->hr_email;
            $doc->supervisor_name = $data["supervisor_name_{$doc->id}"] ?? $doc->supervisor_name;
            $doc->supervisor_email = $data["supervisor_email_{$doc->id}"] ?? $doc->supervisor_email;
            $doc->supervisor_phone = $data["supervisor_phone_{$doc->id}"] ?? $doc->supervisor_phone;

            $doc->hr_email_low_confidence = $this->fraud->isFreeEmailProvider($doc->hr_email);
            $doc->supervisor_email_low_confidence = $this->fraud->isFreeEmailProvider($doc->supervisor_email);

            if ($isSubmit) {
                $doc->submitted_at = now();
            }

            $doc->save();

            if (!$doc->employer_name || !$doc->hr_email || !$doc->supervisor_name || !$doc->supervisor_email) {
                $allComplete = false;
            }
        }

        if ($isSubmit) {
            if ($docs->isEmpty() || !$allComplete) {
                return back()->withErrors(['employer' => 'Employer name, HR email, supervisor name, and supervisor email are required for every employer before submitting.']);
            }

            foreach ($docs as $doc) {
                $this->statusService->transition($doc, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            }

            $this->statusService->transition($item, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $item->forceFill(['declaration_accepted_at' => now(), 'submitted_at' => now()])->save();

            return redirect()->route('candidate.verification.show')->with('status', 'Employment verification submitted for review.');
        }

        return back()->with('status', 'Draft saved.');
    }

    private function candidate(): Candidate
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        return $candidate;
    }

    private function itemFor(Candidate $candidate): CandidateVerificationItem
    {
        $item = $candidate->verificationItems()
            ->whereHas('verificationType', fn ($q) => $q->where('key', 'employment_history'))
            ->with('verificationType')
            ->first();

        abort_if(!$item || $item->status === VerificationStatus::WAITING_PAYMENT, 404);

        return $item;
    }

    /**
     * Lazily creates one doc row per existing experience entry, mirroring
     * EducationVerificationController::docsFor.
     */
    private function docsFor(Candidate $candidate, CandidateVerificationItem $item)
    {
        $experiences = $candidate->experiences()->get();
        $existingExperienceIds = CandidateEmploymentVerificationDoc::where('candidate_verification_item_id', $item->id)
            ->pluck('candidate_experience_id')->all();

        foreach ($experiences as $experience) {
            if (!in_array($experience->id, $existingExperienceIds, true)) {
                CandidateEmploymentVerificationDoc::create([
                    'candidate_verification_item_id' => $item->id,
                    'candidate_experience_id' => $experience->id,
                    'employer_name' => $experience->organization,
                    'status' => VerificationStatus::WAITING_DOCUMENTS,
                ]);
            }
        }

        return CandidateEmploymentVerificationDoc::where('candidate_verification_item_id', $item->id)
            ->with('experience')->get();
    }
}
