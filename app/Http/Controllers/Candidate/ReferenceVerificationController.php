<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateVerificationItem;
use App\Models\CandidateVerificationReference;
use App\Services\Verification\FraudPreventionService;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferenceVerificationController extends Controller
{
    private const RELATIONSHIPS = [
        'direct_supervisor', 'line_manager', 'department_manager',
        'business_owner', 'hr_representative', 'project_lead', 'other',
    ];

    public function __construct(
        private readonly FraudPreventionService $fraud,
        private readonly VerificationStatusService $statusService,
    ) {
    }

    public function show(): View
    {
        $candidate = $this->candidate();
        $item = $this->itemFor($candidate);

        return view('candidate.verification.references', [
            'candidate' => $candidate,
            'item' => $item,
            'references' => $item->references()->with('experience')->get(),
            'experiences' => $candidate->experiences()->get(),
            'relationships' => self::RELATIONSHIPS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $candidate = $this->candidate();
        $item = $this->itemFor($candidate);

        $data = $request->validate([
            'candidate_experience_id' => ['required', Rule::exists('candidate_experiences', 'id')->where('candidate_id', $candidate->id)],
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'relationship' => ['required', Rule::in(self::RELATIONSHIPS)],
            'corporate_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'worked_together_from' => ['nullable', 'date'],
            'worked_together_to' => ['nullable', 'date'],
        ]);

        $data['low_confidence_email'] = $this->fraud->isFreeEmailProvider($data['corporate_email'] ?? null);
        $data['status'] = VerificationStatus::WAITING_DOCUMENTS;

        $item->references()->create($data);

        return back()->with('status', 'Reference added.');
    }

    public function destroy(CandidateVerificationReference $reference): RedirectResponse
    {
        $item = $this->itemFor($this->candidate());
        abort_unless($reference->candidate_verification_item_id === $item->id, 404);

        if (in_array($reference->status, [VerificationStatus::DOCUMENTS_SUBMITTED, VerificationStatus::UNDER_REVIEW], true)) {
            return back()->withErrors(['reference' => 'This reference is already submitted for review and can no longer be removed.']);
        }

        $reference->delete();

        return back()->with('status', 'Reference removed.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $candidate = $this->candidate();
        $item = $this->itemFor($candidate);

        if ($this->fraud->hasActiveSubmissionInReview($item)) {
            return back()->withErrors(['submit' => 'This verification is already under review. Please wait for a decision before resubmitting.']);
        }

        $request->validate(['declaration' => ['required']]);

        $references = $item->references()->get();

        if ($references->isEmpty()) {
            return back()->withErrors(['reference' => 'Add at least one professional reference before submitting.']);
        }

        foreach ($references as $reference) {
            $this->statusService->transition($reference, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
        }

        $this->statusService->transition($item, VerificationStatus::DOCUMENTS_SUBMITTED, 'candidate', $candidate->id);
        $item->forceFill(['declaration_accepted_at' => now(), 'submitted_at' => now()])->save();

        return redirect()->route('candidate.verification.show')->with('status', 'References submitted.');
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
            ->whereHas('verificationType', fn ($q) => $q->where('key', 'references'))
            ->with('verificationType')
            ->first();

        abort_if(!$item || $item->status === VerificationStatus::WAITING_PAYMENT, 404);

        return $item;
    }
}
