<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateLicenseVerification;
use App\Models\CandidateVerificationItem;
use App\Services\Verification\FraudPreventionService;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LicenseVerificationController extends Controller
{
    public function __construct(
        private readonly FraudPreventionService $fraud,
        private readonly VerificationStatusService $statusService,
    ) {
    }

    public function show(): View
    {
        $item = $this->itemFor($this->candidate());

        return view('candidate.verification.license', [
            'candidate' => $this->candidate(),
            'item' => $item,
            'detail' => $item->licenseVerification ?? new CandidateLicenseVerification(),
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
            'license_name' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'verification_url' => ['nullable', 'url', 'max:500'],
            'certificate' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'action' => ['required', 'in:draft,submit'],
            'declaration' => ['required_if:action,submit'],
        ]);

        $detail = CandidateLicenseVerification::firstOrNew(['candidate_verification_item_id' => $item->id]);
        $isNew = !$detail->exists;
        $detail->candidate_id = $candidate->id;
        $detail->candidate_verification_item_id = $item->id;
        $detail->fill([
            'license_name' => $data['license_name'] ?? $detail->license_name,
            'license_number' => $data['license_number'] ?? $detail->license_number,
            'issuing_authority' => $data['issuing_authority'] ?? $detail->issuing_authority,
            'expiry_date' => $data['expiry_date'] ?? $detail->expiry_date,
            'verification_url' => $data['verification_url'] ?? $detail->verification_url,
        ]);

        if (!empty($data['license_number'])) {
            $detail->duplicate_number_flag = $this->fraud->isDuplicateNumber(
                CandidateLicenseVerification::class,
                'license_number',
                $data['license_number'],
                $candidate->id,
            );
        }

        if ($request->hasFile('certificate')) {
            $this->fraud->validatePdfUpload($request->file('certificate'), 'certificate');

            if ($detail->certificate_path) {
                Storage::disk('local')->delete($detail->certificate_path);
            }

            $detail->certificate_path = $request->file('certificate')->store("license-verification/{$candidate->id}", 'local');
        }

        if ($isNew) {
            $detail->status = VerificationStatus::WAITING_DOCUMENTS;
        }

        if ($isSubmit) {
            if (!$detail->certificate_path || !$detail->license_name || !$detail->license_number || !$detail->issuing_authority) {
                return back()->withErrors(['certificate' => 'License name, number, issuing authority, and the license certificate are required before submitting.']);
            }

            $detail->declaration_accepted_at = now();
            $detail->submitted_at = now();
        }

        $detail->save();

        if ($isSubmit) {
            $this->statusService->transition($detail, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $this->statusService->transition($item, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
            $item->forceFill(['declaration_accepted_at' => now(), 'submitted_at' => now()])->save();

            return redirect()->route('candidate.verification.show')->with('status', 'License verification submitted for review.');
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
            ->whereHas('verificationType', fn ($q) => $q->where('key', 'professional_license'))
            ->with('verificationType')
            ->first();

        abort_if(!$item || $item->status === VerificationStatus::WAITING_PAYMENT, 404);

        return $item;
    }
}
