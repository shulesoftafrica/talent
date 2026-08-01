<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateEducationVerificationDoc;
use App\Models\CandidateVerificationItem;
use App\Services\Verification\FraudPreventionService;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EducationVerificationController extends Controller
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

        return view('candidate.verification.education', [
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

        $request->validate([
            'action' => ['required', 'in:draft,submit'],
            'declaration' => ['required_if:action,submit'],
        ]);

        $docs = $this->docsFor($candidate, $item);
        $allHaveCertificate = true;

        foreach ($docs as $doc) {
            $certKey = "certificate_{$doc->id}";
            $transcriptKey = "transcript_{$doc->id}";

            if ($request->hasFile($certKey)) {
                $request->validate([$certKey => ['file', 'mimes:pdf', 'max:2048']]);
                $file = $request->file($certKey);
                $this->fraud->validatePdfUpload($file, $certKey);

                if ($doc->certificate_path) {
                    Storage::disk('local')->delete($doc->certificate_path);
                }
                $doc->certificate_path = $file->store("education-verification/{$candidate->id}", 'local');
            }

            if ($request->hasFile($transcriptKey)) {
                $request->validate([$transcriptKey => ['file', 'mimes:pdf', 'max:2048']]);
                $file = $request->file($transcriptKey);
                $this->fraud->validatePdfUpload($file, $transcriptKey);

                if ($doc->transcript_path) {
                    Storage::disk('local')->delete($doc->transcript_path);
                }
                $doc->transcript_path = $file->store("education-verification/{$candidate->id}", 'local');
            }

            if ($isSubmit) {
                $doc->submitted_at = now();
            }

            $doc->save();

            if (!$doc->certificate_path) {
                $allHaveCertificate = false;
            }
        }

        if ($isSubmit) {
            if ($docs->isEmpty() || !$allHaveCertificate) {
                return back()->withErrors(['certificate' => 'Upload the final certificate for every education entry before submitting.']);
            }

            foreach ($docs as $doc) {
                $this->statusService->transition($doc, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            }

            $this->statusService->transition($item, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $item->forceFill(['declaration_accepted_at' => now(), 'submitted_at' => now()])->save();

            return redirect()->route('candidate.verification.show')->with('status', 'Education verification submitted for review.');
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
            ->whereHas('verificationType', fn ($q) => $q->where('key', 'education'))
            ->with('verificationType')
            ->first();

        abort_if(!$item || $item->status === VerificationStatus::WAITING_PAYMENT, 404);

        return $item;
    }

    /**
     * Lazily creates one doc row per existing education entry (mirrors
     * VerificationCheckoutController::itemsForCheckout's lazy-creation
     * pattern) so an education entry added later automatically gets its
     * own upload section next time this page loads.
     */
    private function docsFor(Candidate $candidate, CandidateVerificationItem $item)
    {
        $educations = $candidate->educations()->get();
        $existingEducationIds = CandidateEducationVerificationDoc::where('candidate_verification_item_id', $item->id)
            ->pluck('candidate_education_id')->all();

        foreach ($educations as $education) {
            if (!in_array($education->id, $existingEducationIds, true)) {
                CandidateEducationVerificationDoc::create([
                    'candidate_verification_item_id' => $item->id,
                    'candidate_education_id' => $education->id,
                    'status' => VerificationStatus::WAITING_DOCUMENTS,
                ]);
            }
        }

        return CandidateEducationVerificationDoc::where('candidate_verification_item_id', $item->id)
            ->with('education')->get();
    }
}
