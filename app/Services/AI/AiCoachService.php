<?php

namespace App\Services\AI;

use App\Models\Candidate;
use App\Models\Training;
use App\Services\Verification\VerificationStatus;

/**
 * Candidate-facing AI advice (Profile Review, Job Coach, Career Readiness).
 * Every method tries a real OpenAI call first, then falls back to
 * rule-based content built from the candidate's actual data — the same
 * graceful-degradation pattern used everywhere else in this app, since
 * OpenAI availability/quota is an external dependency this app never
 * blocks a core feature on.
 */
class AiCoachService
{
    public function __construct(
        private readonly OpenAiClient $openAi,
        private readonly AcademyCourseRecommender $academyCourses,
    ) {
    }

    /**
     * @return array{items: array<int, array{label:string, impact:string}>}
     */
    public function reviewProfile(Candidate $candidate): array
    {
        $gaps = $this->profileGaps($candidate);

        $ai = $this->openAi->chatJson(
            system: 'You are a career coach for a school-staffing platform in Africa. Given a candidate profile summary and a list of concrete gaps, return JSON {"items": [{"label": string, "impact": "+N%"}]} with 2-4 short, specific, encouraging suggestions ranked by impact. Only reference gaps actually given — never invent unrelated advice.',
            user: json_encode(['profession' => $candidate->profession, 'gaps' => $gaps]),
            candidateId: $candidate->id,
            feature: AiFeature::PROFILE_REVIEW,
        );

        if ($ai && !empty($ai['items'])) {
            return ['items' => $ai['items']];
        }

        $items = collect($gaps)->take(4)->map(fn ($gap) => ['label' => $gap['label'], 'impact' => $gap['impact']])->all();

        if (empty($items)) {
            $items = [['label' => 'Your profile looks complete — keep it updated as you gain experience.', 'impact' => '']];
        }

        return ['items' => $items];
    }

    /**
     * @return array{title:string, topics: array<int, array{title:string, body:string}>}
     */
    public function jobCoach(Candidate $candidate, object $job): array
    {
        $missingGap = $this->profileGaps($candidate)[0]['label'] ?? null;

        $ai = $this->openAi->chatJson(
            system: 'You are a career coach for a school-staffing platform in Africa, helping a candidate prepare for a specific job. Return JSON {"topics": [{"title": string, "body": string}]} with exactly 4 short, concrete, encouraging topics covering: interview preparation, any missing profile item, a relevant certification suggestion, and one piece of career advice for this role. Base it only on the job and candidate info given. IMPORTANT: the identity of the specific school/employer hiring for this role is intentionally hidden from the candidate until they apply, and you are never told it either — never name, invent, or guess a company or school name (e.g. do not suggest researching "ShuleSoft" or any other specific organization). Always refer to the employer generically as "the school" or "this school".',
            user: json_encode(['job_title' => $job->title, 'department' => $job->department, 'location' => $job->location, 'candidate_profession' => $candidate->profession, 'missing_gap' => $missingGap]),
            candidateId: $candidate->id,
            feature: AiFeature::JOB_COACH,
            meta: ['job_title' => $job->title],
        );

        if ($ai && !empty($ai['topics'])) {
            return ['title' => $job->title, 'topics' => $ai['topics']];
        }

        return [
            'title' => $job->title,
            'topics' => [
                ['title' => 'Improve your interview chances', 'body' => 'Practice answering common questions for this role with a friend or mentor before you interview.'],
                ['title' => 'Missing documents', 'body' => $missingGap ? "Add your {$missingGap} to strengthen this application." : 'Your documents look complete for this role.'],
                ['title' => 'Suggested certifications', 'body' => 'A relevant certification for your field is often valued by schools like this one.'],
                ['title' => 'Interview preparation', 'body' => 'Review the school\'s requirements and prepare two questions to ask the panel.'],
            ],
        ];
    }

    /**
     * @return array{recommendations: array, nextSteps: array, completedCerts: array}
     */
    public function careerReadiness(Candidate $candidate): array
    {
        $trainings = Training::query()
            ->where(fn ($q) => $q->whereNull('profession')->orWhere('profession', $candidate->profession))
            ->orderBy('sort_order')
            ->get();

        $enrolledIds = $candidate->trainings()->pluck('training_id', 'training_id');

        $recommendations = $trainings->map(function (Training $training) use ($candidate, $enrolledIds) {
            $enrollment = $enrolledIds->has($training->id)
                ? $candidate->trainings()->where('training_id', $training->id)->first()
                : null;

            $meta = array_filter([
                $training->duration ? ['k' => 'Duration', 'v' => $training->duration] : null,
                $training->next_training_date ? ['k' => 'Next Training', 'v' => $training->next_training_date] : null,
                $training->seats_available ? ['k' => 'Seats Available', 'v' => (string) $training->seats_available] : null,
                $training->organizer ? ['k' => 'Organizer', 'v' => $training->organizer] : null,
                ['k' => 'Price', 'v' => $training->price_label],
            ]);

            return [
                'id' => $training->id,
                'title' => $training->title,
                'priority' => $training->priority_label,
                'why' => $training->why,
                'meta' => $meta,
                'completed' => $enrollment?->status === 'completed',
                'enrolled' => (bool) $enrollment,
                'certificate_id' => $enrollment?->certificate_id,
            ];
        });

        // Academy LMS course recommendations, matched to this candidate's
        // profession/skills/experience, lead the list; curated talent trainings follow.
        $courseRecommendations = $this->academyCourses->recommend($candidate);
        $recommendations = array_merge($courseRecommendations, $recommendations->all());

        $gaps = $this->profileGaps($candidate);
        $nextSteps = collect($gaps)->map(fn ($gap) => ['label' => $gap['label'], 'impact' => $gap['impact'], 'done' => false])->all();

        $completedCerts = $candidate->certifications()->where('status', 'Verified')->get()
            ->map(fn ($c) => ['name' => $c->name, 'issuer' => $c->issuer])->all();

        return ['recommendations' => $recommendations, 'nextSteps' => $nextSteps, 'completedCerts' => $completedCerts];
    }

    /**
     * Concrete, ranked profile gaps derived from real candidate data —
     * shared by all three methods above so their advice stays consistent
     * with each other.
     *
     * @return array<int, array{label:string, impact:string}>
     */
    private function profileGaps(Candidate $candidate): array
    {
        $gaps = [];

        // Verification is behind a global kill-switch (VERIFICATION_ENABLED)
        // until the business launches it — suggesting these gaps while the
        // feature is hidden everywhere else would send candidates to a dead
        // end, so they're only ever offered once it's live.
        if (config('services.verification_enabled')) {
            if (!$candidate->verificationItems()->where('status', VerificationStatus::VERIFIED)->exists()) {
                $gaps[] = ['label' => 'Verify your Identity', 'impact' => '+12%'];
            }

            if (!$candidate->educations()->where('status', 'Verified')->exists() && $candidate->educations()->exists()) {
                $gaps[] = ['label' => 'Verify your Education', 'impact' => '+8%'];
            }

            if (!$candidate->experiences()->where('is_verified', true)->exists() && $candidate->experiences()->exists()) {
                $gaps[] = ['label' => 'Verify your Employment History', 'impact' => '+6%'];
            }
        }

        if ($candidate->portfolioItems()->count() === 0) {
            $gaps[] = ['label' => 'Add a Portfolio Item', 'impact' => '+5%'];
        }

        if ($candidate->skills()->count() < 3) {
            $gaps[] = ['label' => 'Add more Skills', 'impact' => '+4%'];
        }

        if (!$candidate->trainings()->where('status', 'completed')->exists()) {
            $gaps[] = ['label' => 'Complete a recommended training', 'impact' => '+5%'];
        }

        return $gaps;
    }
}
