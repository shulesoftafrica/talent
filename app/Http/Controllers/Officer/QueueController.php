<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\CandidateVerificationItem;
use App\Models\OfficerActionLog;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class QueueController extends Controller
{
    /** Business SLA window for reviewing a submitted verification item. */
    private const SLA_DAYS = 3;

    public function __construct(private readonly VerificationStatusService $statusService)
    {
    }

    public function index(Request $request): View
    {
        $officer = Auth::guard('officer')->user();

        $items = CandidateVerificationItem::whereIn('status', VerificationStatus::AWAITING_OFFICER)
            ->with(['candidate', 'verificationType'])
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (CandidateVerificationItem $item) => $this->decorate($item));

        $selectedId = (int) $request->query('selected', $items->first()['id'] ?? 0);
        $selected = $items->firstWhere('id', $selectedId) ?? $items->first();

        return view('officer.queue', [
            'officer' => $officer,
            'items' => $items,
            'selected' => $selected,
        ]);
    }

    public function approve(CandidateVerificationItem $item, Request $request): RedirectResponse
    {
        $officer = Auth::guard('officer')->user();

        $item->forceFill([
            'verified_on' => now()->toDateString(),
            'reviewed_by' => $officer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->statusService->transition($item, VerificationStatus::VERIFIED, 'officer', $officer->id, $request->input('notes'));

        $this->log($item, $officer, 'approve', $request->input('notes'));

        return redirect()->route('officer.queue')->with('status', 'Approved.');
    }

    public function reject(CandidateVerificationItem $item, Request $request): RedirectResponse
    {
        $officer = Auth::guard('officer')->user();

        $item->forceFill([
            'reviewed_by' => $officer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->statusService->transition($item, VerificationStatus::REJECTED, 'officer', $officer->id, $request->input('notes'));

        $this->log($item, $officer, 'reject', $request->input('notes'));

        return redirect()->route('officer.queue')->with('status', 'Rejected.');
    }

    public function addNote(CandidateVerificationItem $item, Request $request): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);

        $this->log($item, Auth::guard('officer')->user(), 'note', $data['notes']);

        return redirect()->route('officer.queue', ['selected' => $item->id])->with('status', 'Note added.');
    }

    private function log(CandidateVerificationItem $item, $officer, string $action, ?string $notes): void
    {
        OfficerActionLog::create([
            'candidate_verification_item_id' => $item->id,
            'officer_id' => $officer->id,
            'officer_name' => $officer->name,
            'action' => $action,
            'notes' => $notes,
        ]);
    }

    private function decorate(CandidateVerificationItem $item): array
    {
        $candidate = $item->candidate;
        $daysWaiting = (int) floor($item->submitted_at?->diffInDays(now()) ?? 0);
        $slaRemaining = self::SLA_DAYS - $daysWaiting;

        $priority = match (true) {
            $slaRemaining < 0 => ['label' => 'High Risk', 'icon' => '🔴', 'key' => 'high'],
            $slaRemaining <= 1 => ['label' => 'Needs Decision', 'icon' => '🟡', 'key' => 'decision'],
            default => ['label' => 'On Track', 'icon' => '🟢', 'key' => 'ready'],
        };

        $slaLabel = $slaRemaining < 0
            ? 'Overdue by ' . abs($slaRemaining) . ' day' . (abs($slaRemaining) === 1 ? '' : 's')
            : $slaRemaining . ' day' . ($slaRemaining === 1 ? '' : 's') . ' remaining';

        $evidence = $candidate->portfolioItems->map(fn ($p) => $p->title)->all();
        if ($candidate->cv_path) {
            array_unshift($evidence, 'Candidate CV');
        }

        return [
            'id' => $item->id,
            'candidate_name' => $candidate->full_name,
            'candidate_id' => $candidate->id,
            'type' => $item->verificationType->name,
            'payer' => $item->payer,
            'submitted_at' => $item->submitted_at?->format('d M Y'),
            'priority' => $priority,
            'sla_label' => $slaLabel,
            'candidate_info' => [
                ['label' => 'Location', 'value' => $candidate->current_location ?: '—'],
                ['label' => 'Profession', 'value' => $candidate->profession ?: '—'],
                ['label' => 'Phone', 'value' => $candidate->phone],
                ['label' => 'Email', 'value' => $candidate->email ?: '—'],
                ['label' => 'Trust Score', 'value' => $candidate->trust_score . '/100'],
                ['label' => 'Premium Status', 'value' => $candidate->is_premium ? 'Premium' : 'Free'],
                ['label' => 'Candidate Since', 'value' => $candidate->created_at->format('M Y')],
            ],
            'evidence' => $evidence,
            'checklist' => $this->checklistFor($item),
            'submission' => $this->submissionFor($item),
            'notes' => $item->actionLogs->where('action', 'note')->map(fn ($log) => "{$log->officer_name}: {$log->notes} (" . $log->created_at->format('d M') . ')')->all(),
            'history' => $item->actionLogs->whereIn('action', ['approve', 'reject'])->map(fn ($log) => [
                'label' => ucfirst($log->action), 'date' => $log->created_at->format('d M Y'),
            ])->values()->all(),
        ];
    }

    private function checklistFor(CandidateVerificationItem $item): array
    {
        $candidate = $item->candidate;

        return match ($item->verificationType->key) {
            'identity' => [
                ['label' => 'Full name on file', 'done' => (bool) $candidate->full_name],
                ['label' => 'Phone verified', 'done' => (bool) $candidate->phone_verified_at],
            ],
            'education' => [
                ['label' => 'Education entries on profile', 'done' => $candidate->educations()->exists()],
                ['label' => 'CV on file', 'done' => (bool) $candidate->cv_path],
            ],
            'employment_history' => [
                ['label' => 'Experience entries on profile', 'done' => $candidate->experiences()->exists()],
                ['label' => 'CV on file', 'done' => (bool) $candidate->cv_path],
            ],
            default => [
                ['label' => 'Profile complete', 'done' => $candidate->profile_strength >= 50],
            ],
        };
    }

    /**
     * Read-only view of what the candidate actually submitted for this
     * category (Upgrade Spec Part 58) — separate from checklistFor()'s
     * generic profile-completeness signals. One section per submitted
     * sub-entry for the "many" categories (education/employment/references),
     * a single section for the "one" categories.
     */
    private function submissionFor(CandidateVerificationItem $item): array
    {
        return match ($item->verificationType->key) {
            'identity' => $this->identitySubmission($item),
            'education' => $this->educationSubmission($item),
            'professional_license' => $this->licenseSubmission($item),
            'employment_history' => $this->employmentSubmission($item),
            'background_check' => $this->backgroundSubmission($item),
            'references' => $this->referencesSubmission($item),
            default => [],
        };
    }

    private function identitySubmission(CandidateVerificationItem $item): array
    {
        $detail = $item->identityVerification;
        if (!$detail) {
            return [];
        }

        return [[
            'title' => 'Primary Government Identity',
            'fields' => [
                ['label' => 'Document Type', 'value' => $detail->primary_doc_type ?: '—'],
                ['label' => 'Primary Document', 'value' => $detail->primary_doc_path ? 'View file' : 'Not uploaded', 'url' => $this->fileUrl($detail->primary_doc_path)],
                ['label' => 'Tax ID Certificate', 'value' => $detail->tin_certificate_path ? 'View file' : '—', 'url' => $this->fileUrl($detail->tin_certificate_path)],
                ['label' => 'Local Govt Letter', 'value' => $detail->local_government_letter_path ? 'View file' : '—', 'url' => $this->fileUrl($detail->local_government_letter_path)],
                ['label' => 'Pension Fund Number', 'value' => $detail->pension_fund_number ?: '—'],
            ],
        ]];
    }

    private function educationSubmission(CandidateVerificationItem $item): array
    {
        return $item->educationDocs()->with('education')->get()->map(fn ($doc) => [
            'title' => $doc->education?->degree . ' — ' . $doc->education?->school,
            'fields' => [
                ['label' => 'Certificate', 'value' => $doc->certificate_path ? 'View file' : 'Not uploaded', 'url' => $this->fileUrl($doc->certificate_path)],
                ['label' => 'Transcript', 'value' => $doc->transcript_path ? 'View file' : '—', 'url' => $this->fileUrl($doc->transcript_path)],
            ],
        ])->all();
    }

    private function licenseSubmission(CandidateVerificationItem $item): array
    {
        $detail = $item->licenseVerification;
        if (!$detail) {
            return [];
        }

        return [[
            'title' => $detail->license_name ?: 'Professional License',
            'fields' => [
                ['label' => 'License Number', 'value' => ($detail->license_number ?: '—') . ($detail->duplicate_number_flag ? ' ⚠ duplicate' : '')],
                ['label' => 'Issuing Authority', 'value' => $detail->issuing_authority ?: '—'],
                ['label' => 'Expiry Date', 'value' => optional($detail->expiry_date)->format('d M Y') ?: '—'],
                ['label' => 'Verification URL', 'value' => $detail->verification_url ?: '—'],
                ['label' => 'Certificate', 'value' => $detail->certificate_path ? 'View file' : 'Not uploaded', 'url' => $this->fileUrl($detail->certificate_path)],
            ],
        ]];
    }

    private function employmentSubmission(CandidateVerificationItem $item): array
    {
        return $item->employmentDocs()->with('experience')->get()->map(fn ($doc) => [
            'title' => $doc->experience?->title . ' — ' . ($doc->employer_name ?: $doc->experience?->organization),
            'fields' => [
                ['label' => 'Employer Address', 'value' => $doc->employer_address ?: '—'],
                ['label' => 'Employer Website', 'value' => $doc->employer_website ?: '—'],
                ['label' => 'HR Email', 'value' => ($doc->hr_email ?: '—') . ($doc->hr_email_low_confidence ? ' ⚠ personal email' : '')],
                ['label' => 'Supervisor', 'value' => $doc->supervisor_name ?: '—'],
                ['label' => 'Supervisor Email', 'value' => ($doc->supervisor_email ?: '—') . ($doc->supervisor_email_low_confidence ? ' ⚠ personal email' : '')],
                ['label' => 'Supervisor Phone', 'value' => $doc->supervisor_phone ?: '—'],
            ],
        ])->all();
    }

    private function backgroundSubmission(CandidateVerificationItem $item): array
    {
        $detail = $item->backgroundVerification;
        if (!$detail) {
            return [];
        }

        return [[
            'title' => 'Criminal Background Clearance',
            'fields' => [
                ['label' => 'Country Issued', 'value' => $detail->country_issued ?: '—'],
                ['label' => 'Certificate Number', 'value' => ($detail->certificate_number ?: '—') . ($detail->duplicate_number_flag ? ' ⚠ duplicate' : '')],
                ['label' => 'Issue Date', 'value' => optional($detail->issue_date)->format('d M Y') ?: '—'],
                ['label' => 'Expiry Date', 'value' => optional($detail->expiry_date)->format('d M Y') ?: '—'],
                ['label' => 'Certificate', 'value' => $detail->certificate_path ? 'View file' : 'Not uploaded', 'url' => $this->fileUrl($detail->certificate_path)],
            ],
        ]];
    }

    private function referencesSubmission(CandidateVerificationItem $item): array
    {
        return $item->references()->with('experience')->get()->map(fn ($ref) => [
            'title' => $ref->full_name . ' — ' . $ref->position,
            'fields' => [
                ['label' => 'Company', 'value' => $ref->experience?->organization ?: '—'],
                ['label' => 'Relationship', 'value' => ucwords(str_replace('_', ' ', $ref->relationship))],
                ['label' => 'Corporate Email', 'value' => ($ref->corporate_email ?: '—') . ($ref->low_confidence_email ? ' ⚠ personal email' : '')],
                ['label' => 'Phone', 'value' => $ref->phone ?: '—'],
            ],
        ])->all();
    }

    private function fileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('local')->temporaryUrl($path, now()->addMinutes(15));
    }
}
