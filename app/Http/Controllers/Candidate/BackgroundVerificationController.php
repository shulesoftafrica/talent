<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateBackgroundVerification;
use App\Models\CandidateVerificationItem;
use App\Services\Verification\FraudPreventionService;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BackgroundVerificationController extends Controller
{
    public function __construct(
        private readonly FraudPreventionService $fraud,
        private readonly VerificationStatusService $statusService,
    ) {
    }

    public function show(): View
    {
        $item = $this->itemFor($this->candidate());

        return view('candidate.verification.background', [
            'candidate' => $this->candidate(),
            'item' => $item,
            'detail' => $item->backgroundVerification ?? new CandidateBackgroundVerification(),
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
            'country_issued' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'certificate' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'action' => ['required', 'in:draft,submit'],
            'declaration' => ['required_if:action,submit'],
        ]);

        $detail = CandidateBackgroundVerification::firstOrNew(['candidate_verification_item_id' => $item->id]);
        $isNew = !$detail->exists;
        $detail->candidate_id = $candidate->id;
        $detail->candidate_verification_item_id = $item->id;
        $detail->fill([
            'country_issued' => $data['country_issued'] ?? $detail->country_issued,
            'certificate_number' => $data['certificate_number'] ?? $detail->certificate_number,
            'issue_date' => $data['issue_date'] ?? $detail->issue_date,
            'expiry_date' => $data['expiry_date'] ?? $detail->expiry_date,
        ]);

        if (!empty($data['certificate_number'])) {
            $detail->duplicate_number_flag = $this->fraud->isDuplicateNumber(
                CandidateBackgroundVerification::class,
                'certificate_number',
                $data['certificate_number'],
                $candidate->id,
            );
        }

        if ($request->hasFile('certificate')) {
            $this->fraud->validatePdfUpload($request->file('certificate'), 'certificate');

            if ($detail->certificate_path) {
                Storage::disk('local')->delete($detail->certificate_path);
            }

            $detail->certificate_path = $request->file('certificate')->store("background-verification/{$candidate->id}", 'local');
        }

        if ($isNew) {
            $detail->status = VerificationStatus::WAITING_DOCUMENTS;
        }

        if ($isSubmit) {
            if (!$detail->certificate_path || !$detail->country_issued || !$detail->certificate_number) {
                return back()->withErrors(['certificate' => 'Country issued, certificate number, and the clearance certificate are required before submitting.']);
            }

            $detail->declaration_accepted_at = now();
            $detail->submitted_at = now();
        }

        $detail->save();

        if ($isSubmit) {
            $this->statusService->transition($detail, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $this->statusService->transition($item, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $item->forceFill(['declaration_accepted_at' => now(), 'submitted_at' => now()])->save();

            return redirect()->route('candidate.verification.show')->with('status', 'Background verification submitted for review.');
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
            ->whereHas('verificationType', fn ($q) => $q->where('key', 'background_check'))
            ->with('verificationType')
            ->first();

        abort_if(!$item || $item->status === VerificationStatus::WAITING_PAYMENT, 404);

        return $item;
    }
}
