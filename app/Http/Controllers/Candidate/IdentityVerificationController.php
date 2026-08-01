<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateIdentityVerification;
use App\Models\CandidateVerificationItem;
use App\Services\Verification\FraudPreventionService;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IdentityVerificationController extends Controller
{
    public function __construct(
        private readonly FraudPreventionService $fraud,
        private readonly VerificationStatusService $statusService,
    ) {
    }

    public function show(): View
    {
        $item = $this->itemFor($this->candidate());

        return view('candidate.verification.identity', [
            'candidate' => $this->candidate(),
            'item' => $item,
            'detail' => $item->identityVerification ?? new CandidateIdentityVerification(),
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

        $data = $request->validate([
            'primary_doc_type' => ['nullable', 'in:national_id,passport,driving_licence'],
            'primary_doc' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'tin_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'local_government_letter' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'pension_fund_number' => ['nullable', 'string', 'max:100'],
            'action' => ['required', 'in:draft,submit'],
            'declaration' => ['required_if:action,submit'],
        ]);

        $detail = CandidateIdentityVerification::firstOrNew(['candidate_verification_item_id' => $item->id]);
        $isNew = !$detail->exists;
        $detail->candidate_id = $candidate->id;
        $detail->candidate_verification_item_id = $item->id;

        if (!empty($data['primary_doc_type'])) {
            $detail->primary_doc_type = $data['primary_doc_type'];
        }

        if ($request->hasFile('primary_doc')) {
            $this->fraud->validatePdfUpload($request->file('primary_doc'), 'primary_doc');
            $this->replaceFile($detail, 'primary_doc_path', $request->file('primary_doc'), $candidate->id);
            $detail->primary_doc_size_bytes = $request->file('primary_doc')->getSize();
        }

        if ($request->hasFile('tin_certificate')) {
            $this->fraud->validatePdfUpload($request->file('tin_certificate'), 'tin_certificate');
            $this->replaceFile($detail, 'tin_certificate_path', $request->file('tin_certificate'), $candidate->id);
        }

        if ($request->hasFile('local_government_letter')) {
            $this->fraud->validatePdfUpload($request->file('local_government_letter'), 'local_government_letter');
            $this->replaceFile($detail, 'local_government_letter_path', $request->file('local_government_letter'), $candidate->id);
        }

        if ($request->has('pension_fund_number')) {
            $detail->pension_fund_number = $data['pension_fund_number'];
        }

        if ($isNew) {
            $detail->status = VerificationStatus::WAITING_DOCUMENTS;
        }

        if ($isSubmit) {
            if (!$detail->primary_doc_path || !$detail->primary_doc_type) {
                return back()->withErrors(['primary_doc' => 'Choose a document type and upload your primary identity document before submitting.']);
            }

            $detail->declaration_accepted_at = now();
            $detail->submitted_at = now();
        }

        $detail->save();

        if ($isSubmit) {
            $this->statusService->transition($detail, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $this->statusService->transition($item, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $item->forceFill(['declaration_accepted_at' => now(), 'submitted_at' => now()])->save();

            return redirect()->route('candidate.verification.show')->with('status', 'Identity verification submitted for review.');
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
            ->whereHas('verificationType', fn ($q) => $q->where('key', 'identity'))
            ->with('verificationType')
            ->first();

        abort_if(!$item || $item->status === VerificationStatus::WAITING_PAYMENT, 404);

        return $item;
    }

    private function replaceFile(CandidateIdentityVerification $detail, string $column, UploadedFile $file, int $candidateId): void
    {
        if ($detail->{$column}) {
            Storage::disk('local')->delete($detail->{$column});
        }

        $detail->{$column} = $file->store("identity-verification/{$candidateId}", 'local');
    }
}
