<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Services\Onboarding\HrOnboardingGateway;
use App\Services\Onboarding\OfferAnswerRejectedException;
use App\Services\Onboarding\UploadRejectedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The new hire's onboarding checklist (REQ-HRX-04): upload documents, give
 * the typed details (identity number, TIN, pension), and fix anything HR
 * returned. Opens only after the offer is accepted.
 */
class OnboardingChecklistController extends Controller
{
    private const FUNDS = ['NSSF', 'PSSSF', 'other'];
    private const ID_TYPES = ['national_id', 'passport', 'driving_licence'];

    public function __construct(private readonly HrOnboardingGateway $hr)
    {
    }

    private function candidate(): Candidate
    {
        return Auth::guard('candidate')->user();
    }

    private function owned(Application $application): object
    {
        abort_unless($application->candidate_id === $this->candidate()->id, 404);
        $hr = $this->hr->hrApplication($application);
        abort_unless($hr && ! empty($hr->offer_status), 404);

        return $hr;
    }

    public function show(Application $application): View|RedirectResponse
    {
        $hr = $this->owned($application);

        if ($hr->offer_status !== 'accepted') {
            return redirect()->route('candidate.applications.offer', $application);
        }

        $items = $this->hr->items($application);

        return view('candidate.onboarding', [
            'candidate' => $this->candidate(),
            'application' => $application,
            'hr' => $hr,
            'items' => $items,
            'job' => $application->jobPosting(),
            'active' => ($hr->onboarding_status ?? null) === 'active',
            'templates' => $items->mapWithKeys(fn ($i) => [$i->id => $i->template_path && $this->hr->editable($i) ? $this->hr->templateUrl($i) : null]),
        ]);
    }

    public function submit(Request $request, Application $application, int $item): RedirectResponse
    {
        $this->owned($application);
        $row = $this->hr->item($application, $item);
        abort_unless($row, 404);

        [$files, $values] = $this->collect($request, $row);

        try {
            $this->hr->submit($application, $row, $files, $values, $request->ip(), $request->userAgent());
        } catch (UploadRejectedException|OfferAnswerRejectedException $e) {
            return back()->withErrors(["item_{$row->id}" => $e->getMessage()])->withInput();
        }

        return redirect()->route('candidate.applications.onboarding', $application)->with('status', "\"{$row->label}\" submitted. The school will review it.");
    }

    public function template(Application $application, int $item): RedirectResponse
    {
        $this->owned($application);
        $row = $this->hr->item($application, $item);
        abort_unless($row && $row->template_path, 404);

        return redirect()->away($this->hr->templateUrl($row));
    }

    /**
     * Validates the request for one item's type and returns [files, typed values].
     *
     * @return array{0: array<int, \Illuminate\Http\UploadedFile>, 1: array<string, mixed>}
     */
    private function collect(Request $request, object $item): array
    {
        $key = "item_{$item->id}";
        $files = array_values(array_filter((array) $request->file('files', [])));
        $hasNewFiles = $files !== [];
        $values = [];

        $fail = fn (string $message) => throw ValidationException::withMessages([$key => $message]);
        $needsFile = fn () => $hasNewFiles || $item->files->isNotEmpty() ?: $fail('Please attach a file.');

        switch ($item->type) {
            case 'file':
            case 'form_return':
            case 'photo':
            case 'multi_file':
                $needsFile();
                break;

            case 'pension_declaration':
                $member = $request->input('member');
                if (! in_array($member, ['yes', 'no'], true)) {
                    $fail('Say whether you are a member of a pension fund.');
                }
                $values['member'] = $member === 'yes';
                if ($member === 'yes') {
                    $fund = $request->input('fund');
                    if (! in_array($fund, self::FUNDS, true)) {
                        $fail('Choose your pension fund.');
                    }
                    $number = trim((string) $request->input('membership_number'));
                    if ($number === '' || mb_strlen($number) > 40) {
                        $fail('Enter your membership number.');
                    }
                    $values += ['fund' => $fund, 'membership_number' => $number];
                    if ($fund === 'other') {
                        $other = trim((string) $request->input('fund_other'));
                        if ($other === '' || mb_strlen($other) > 100) {
                            $fail('Enter the name of your pension fund.');
                        }
                        $values['fund_other'] = $other;
                    }
                }
                break;

            case 'identity_document':
                $type = $request->input('doc_type');
                if (! in_array($type, self::ID_TYPES, true)) {
                    $fail('Choose the type of identity document.');
                }
                $number = strtoupper(preg_replace('/\s+/', '', (string) $request->input('number')));
                if (! preg_match('/^[A-Z0-9\-\/]{5,30}$/', $number)) {
                    $fail('Enter the document number (5 to 30 letters or digits).');
                }
                $values = ['doc_type' => $type, 'number' => $number];
                $needsFile();
                break;

            case 'tin':
                $tin = preg_replace('/[\s\-]/', '', (string) $request->input('tin'));
                if (! preg_match('/^\d{9}$/', $tin)) {
                    $fail('A TIN has exactly 9 digits.');
                }
                $values['tin'] = $tin;
                break;
        }

        if (count($files) > (int) $item->max_files) {
            $fail("You can upload at most {$item->max_files} file(s) here.");
        }

        return [$files, $values];
    }
}
