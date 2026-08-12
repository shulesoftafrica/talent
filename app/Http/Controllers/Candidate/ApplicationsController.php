<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Services\Applications\ApplicationService;
use App\Services\Applications\ApplicationStatusMapper;
use App\Services\Jobs\SchoolNameResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationsController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly SchoolNameResolver $schoolNames,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $allApps = $candidate->applications()->orderByDesc('applied_at')->get();
        $activeApps = $allApps->whereNull('withdrawn_at');
        $withdrawnApps = $allApps->whereNotNull('withdrawn_at');

        $apps = $activeApps->map(fn (Application $app) => $this->decorate($app));
        $withdrawn = $withdrawnApps->map(fn (Application $app) => $this->decorateWithdrawn($app))->values();

        $attention = $apps->where('urgency', 'attention')->values();
        $waiting = $apps->where('urgency', 'waiting')->values();
        $completed = $apps->where('urgency', 'completed')->values();

        $selectedUuid = $request->query('selected', $apps->first()['uuid'] ?? null);
        $selected = $apps->firstWhere('uuid', $selectedUuid) ?? $apps->first();

        // Set on the redirect right after a successful apply (see apply()
        // below and Auth\OtpController::verify() for the public-vacancy
        // login/CV auto-apply path) — shows a one-time "Application
        // submitted" confirmation with a profile-completion nudge, rather
        // than silently landing back on the same dashboard the candidate
        // was just on.
        $justApplied = $request->boolean('applied') && $selected;

        return view('candidate.applications', [
            'candidate' => $candidate,
            'attention' => $attention,
            'waiting' => $waiting,
            'completed' => $completed,
            'withdrawn' => $withdrawn,
            'hasApplications' => $apps->isNotEmpty(),
            'selected' => $selected,
            'progressStats' => $this->progressStats($apps, $allApps->count()),
            'offerPromptApplicationId' => session('withdrawal_offer_prompt'),
            'justApplied' => $justApplied,
            'profileCompletion' => $justApplied ? $this->profileCompletion($candidate) : [],
        ]);
    }

    /**
     * Same per-section signals as ProfileController::profileCompletion() —
     * duplicated rather than shared because that method is private to a
     * controller with a different constructor; kept here as the small,
     * stable list it is rather than introducing a shared service for two
     * call sites.
     *
     * @return array<int, array{label: string, pct: int}>
     */
    private function profileCompletion(Candidate $candidate): array
    {
        $sections = [
            ['label' => 'Personal Information', 'pct' => $candidate->full_name && $candidate->current_location ? 100 : 60],
            ['label' => 'Experience', 'pct' => $candidate->experiences->isNotEmpty() ? 100 : 0],
            ['label' => 'Education', 'pct' => $candidate->educations->isNotEmpty() ? 100 : 0],
            ['label' => 'Portfolio', 'pct' => min(100, $candidate->portfolioItems->count() * 50)],
            ['label' => 'Skills', 'pct' => min(100, $candidate->skills->count() * 25)],
        ];

        if (config('services.verification_enabled')) {
            $sections[] = ['label' => 'Verification', 'pct' => (int) $candidate->verificationItems()->where('status', \App\Services\Verification\VerificationStatus::VERIFIED)->count() * 17];
        }

        return $sections;
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_schema' => ['required', Rule::in(['shulesoft', 'safaribook'])],
            'job_posting_id' => ['required', 'integer'],
            'ref' => ['nullable', 'string', 'max:40'],
        ]);

        $application = $this->applications->apply(
            Auth::guard('candidate')->user(),
            $data['source_schema'],
            (int) $data['job_posting_id'],
            $data['ref'] ?? null,
        );

        return redirect()
            ->route('candidate.applications.index', ['selected' => $application->uuid, 'applied' => 1])
            ->with('status', 'Application submitted.');
    }

    private function decorate(Application $app): array
    {
        $job = $app->jobPosting();
        $meta = $app->statusMeta();
        $interview = $app->scheduledInterview();
        $stepLabels = ApplicationStatusMapper::stepLabels();

        $steps = collect($stepLabels)->map(function ($label, $idx) use ($meta) {
            $reached = !$meta['is_rejected'] && $idx <= $meta['step_index'];
            return [
                'label' => $label,
                'mark' => $idx < $meta['step_index'] ? '✓' : ($idx === $meta['step_index'] ? '●' : ''),
                'reached' => $reached,
                'current' => $idx === $meta['step_index'],
            ];
        });

        return [
            'id' => $app->id,
            'uuid' => $app->uuid,
            'title' => $job->title ?? 'Job posting no longer available',
            'school' => $this->schoolNames->resolve($app->source_schema, $job->created_by ?? null, $job->department ?? null),
            'location' => $job->location ?? null,
            'applied_on' => $app->applied_at->format('d M Y'),
            'status_label' => $meta['label'],
            'urgency' => $meta['urgency'],
            'stage_context' => $meta['stage_context'],
            'next_action_label' => $meta['next_action_label'],
            'next_action_sub' => $meta['next_action_sub'],
            'is_rejected' => $meta['is_rejected'],
            'steps' => $steps,
            'has_interview' => (bool) $interview,
            'interview_date' => $interview ? Carbon::parse($interview->interview_date)->format('d M Y') : null,
            'interview_time' => $interview && $interview->interview_time ? Carbon::parse($interview->interview_time)->format('g:i A') : null,
            'interview_type' => $interview && $interview->interview_type ? ucwords(str_replace('_', ' ', $interview->interview_type)) : null,
            'interview_duration' => $interview->duration ?? null,
            'interview_location' => $interview->location ?? null,
            'interview_response' => $app->interview_response,
            'interview_response_note' => $app->interview_response_note,
            'awaiting_interview_response' => $meta['label'] === 'Interview Invited' && !$app->interview_response,
            'health_score' => $app->ai_health_score,
            'health_label' => $app->ai_health_label,
            'health_why' => $app->ai_health_why ?? [],
            'health_missing' => $app->ai_health_missing,
            'companion' => $this->companionFor($meta),
            // Automatic withdrawal is only offered before an offer is
            // accepted — at Hired, the candidate is guided to contact the
            // school directly instead (see the Manage modal).
            'can_withdraw' => $meta['label'] !== 'Hired',
        ];
    }

    private function decorateWithdrawn(Application $app): array
    {
        $job = $app->jobPosting();
        $sourceSchema = $app->source_schema;

        $jobStillOpen = DB::connection($sourceSchema)->table('job_postings')
            ->where('id', $app->source_job_posting_id)
            ->where('status', 'active')
            ->exists();

        return [
            'id' => $app->id,
            'uuid' => $app->uuid,
            'title' => $job->title ?? 'Job posting no longer available',
            'school' => $this->schoolNames->resolve($sourceSchema, $job->created_by ?? null, $job->department ?? null),
            'withdrawn_on' => $app->withdrawn_at->format('d M Y'),
            'reason' => $app->withdrawal_reason,
            'reason_other' => $app->withdrawal_reason_other,
            'source_schema' => $sourceSchema,
            'source_job_posting_id' => $app->source_job_posting_id,
            'can_reapply' => $jobStillOpen,
        ];
    }

    /**
     * Simple rule-based "AI companion" panel content per stage — the same
     * graceful-degradation approach used elsewhere until Phase 4 layers in
     * real OpenAI-generated coaching content.
     */
    private function companionFor(array $meta): array
    {
        return match (true) {
            $meta['label'] === 'Interview Invited' => [
                'title' => '✨ AI Interview Coach',
                'items' => [
                    'Review the role description and prepare two questions to ask the panel',
                    'Bring: national ID, degree certificate, and any relevant references',
                    'Plan to arrive or join at least 10 minutes early',
                ],
            ],
            $meta['label'] === 'Hired' => [
                'title' => '🎓 Onboarding Guide',
                'items' => [
                    'Documents to prepare: signed contract, ID copy, bank details',
                    'Confirm your start date and reporting time with the school',
                    'Recommended: complete your ShuleSoft profile ahead of day one',
                ],
            ],
            $meta['is_rejected'] => [
                'title' => 'Keep Moving Forward',
                'items' => [
                    'Review your profile for gaps schools commonly screen for',
                    'Check Job Matches for other roles that fit your profile',
                ],
            ],
            default => [
                'title' => 'While You Wait',
                'items' => array_values(array_filter([
                    'Keep your profile complete and up to date',
                    config('services.verification_enabled') ? 'Verify your education or employment history to stand out' : null,
                    'Check Job Matches for other open roles',
                ])),
            ],
        };
    }

    private function progressStats($apps, int $totalSubmittedIncludingWithdrawn): array
    {
        $last90 = $apps; // all of this candidate's history for now — no volume yet to bother windowing

        return [
            // Counts every application ever submitted, including withdrawn
            // ones — withdrawing doesn't erase that you applied.
            ['label' => 'Applications Submitted', 'value' => $totalSubmittedIncludingWithdrawn],
            ['label' => 'Interviews', 'value' => $last90->where('status_label', 'Interview Invited')->count() + $last90->where('status_label', 'Interview Completed')->count()],
            ['label' => 'Offers', 'value' => 0],
            ['label' => 'Hired', 'value' => $last90->where('status_label', 'Hired')->count()],
        ];
    }
}
