<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Services\Onboarding\HrOnboardingGateway;
use App\Services\Onboarding\OfferAnswerRejectedException;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\UploadRejectedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The new hire's onboarding checklist at /app/onboarding/{application}
 * (REQ-HRX-07). Only the owning candidate may open or change it (403 otherwise);
 * it is available once the offer is accepted and the school has opened the
 * checklist. Everything is saved through OnboardingService, into Talent's own
 * table only. No typed value ever travels in a URL.
 */
class OnboardingChecklistController extends Controller
{
    public function __construct(private readonly OnboardingService $onboarding, private readonly HrOnboardingGateway $origin)
    {
    }

    private function candidate(): Candidate
    {
        return Auth::guard('candidate')->user();
    }

    private function owned(Application $application): object
    {
        abort_unless($application->candidate_id === $this->candidate()->id, 403);
        $hr = $this->origin->hrApplication($application);
        abort_unless($hr && ! empty($hr->offer_status), 404);

        return $hr;
    }

    public function show(Application $application): View|RedirectResponse
    {
        $hr = $this->owned($application);

        if ($hr->offer_status !== 'accepted') {
            return redirect()->route('candidate.applications.offer', $application);
        }

        $overview = $this->onboarding->overview($application);

        return view('candidate.onboarding', [
            'candidate' => $this->candidate(),
            'application' => $application,
            'hr' => $hr,
            'overview' => $overview,
            'items' => $overview['items'] ?? collect(),
            'job' => $application->jobPosting(),
            'templates' => collect($overview['items'] ?? [])->mapWithKeys(fn ($i) => [$i->id => $i->template_path && $i->editable ? true : false]),
            'verifiedId' => $overview ? (bool) $this->onboarding->verifiedIdentity($this->candidate()) : false,
            'hasAvatar' => (bool) $this->candidate()->avatar_path,
        ]);
    }

    /** Save this item as a draft, or (intent=submit) save and submit it. */
    public function save(Request $request, Application $application, int $item): RedirectResponse
    {
        $this->owned($application);
        $files = array_values(array_filter((array) $request->file('files', [])));

        return $this->attempt($application, $item, function () use ($request, $application, $item, $files) {
            $args = [$application, $this->candidate(), $item, $request->except(['files', 'labels', 'remove']), $files, (array) $request->input('labels', []), (array) $request->input('remove', [])];

            if ($request->input('intent') === 'submit') {
                $this->onboarding->submitItem(...$args);

                return 'Submitted. The school will review it.';
            }
            $this->onboarding->save(...$args);

            return 'Saved. You can change it until you submit.';
        });
    }

    public function removeFile(Application $application, int $item, string $file): RedirectResponse
    {
        $this->owned($application);

        return $this->attempt($application, $item, function () use ($application, $item, $file) {
            $this->onboarding->save($application, $this->candidate(), $item, [], [], [], [$file]);

            return 'File removed.';
        });
    }

    public function useVerifiedId(Application $application, int $item): RedirectResponse
    {
        $this->owned($application);

        return $this->attempt($application, $item, function () use ($application, $item) {
            $this->onboarding->useVerifiedId($application, $this->candidate(), $item);

            return 'Your verified ID was added. Check the document type and number, then submit.';
        });
    }

    public function useProfilePhoto(Application $application, int $item): RedirectResponse
    {
        $this->owned($application);

        return $this->attempt($application, $item, function () use ($application, $item) {
            $this->onboarding->useProfilePhoto($application, $this->candidate(), $item);

            return 'Your profile photo was added.';
        });
    }

    /** Submit for review: only when every required item is complete. */
    public function submitAll(Application $application): RedirectResponse
    {
        $this->owned($application);

        try {
            $count = $this->onboarding->submitAll($application);
        } catch (OfferAnswerRejectedException $e) {
            return back()->withErrors(['submit_all' => $e->getMessage()]);
        }

        return redirect()->route('candidate.onboarding', $application)->with('status', $count > 0
            ? 'Submitted for review. The school will check your documents and message you.'
            : 'Everything is already submitted.');
    }

    public function template(Application $application, int $item): RedirectResponse
    {
        $this->owned($application);
        $row = $this->origin->checklist($application)->firstWhere('id', $item);
        abort_unless($row && $row->template_path && $row->status !== 'locked', 404);

        return redirect()->away($this->origin->templateUrl($row));
    }

    /** Runs one action on an item and turns a refusal into a message next to that item. */
    private function attempt(Application $application, int $item, callable $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (UploadRejectedException|OfferAnswerRejectedException $e) {
            return back()->withErrors(["item_{$item}" => $e->getMessage()])->withInput();
        }

        return redirect()->route('candidate.onboarding', $application)->with('status', $message)->withFragment("item-{$item}");
    }
}
