<?php

namespace App\Services\Applications;

use App\Models\Application;
use App\Models\Candidate;
use App\Services\Verification\VerificationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles the "apply to a job" write-through: one row goes into the origin
 * school/client's own applications table (so their existing recruiter
 * tooling sees it immediately, unmodified), and one row goes into
 * talent.applications (the rich, candidate-facing source of truth for
 * stage tracking, AI health, interview details). The two are linked by a
 * pair of reference columns rather than duplicating data — see the
 * Database Design section of the implementation plan.
 */
class ApplicationService
{
    public function apply(Candidate $candidate, string $sourceSchema, int $jobPostingId): Application
    {
        if (!in_array($sourceSchema, ['shulesoft', 'safaribook'], true)) {
            throw new \InvalidArgumentException("Invalid source schema: {$sourceSchema}");
        }

        // A withdrawn application doesn't block re-applying — "Apply Again"
        // on the same job posting reuses this exact method to create a
        // genuinely new application (and a new origin row), rather than
        // resurrecting the withdrawn one.
        $existing = Application::where('candidate_id', $candidate->id)
            ->where('source_schema', $sourceSchema)
            ->where('source_job_posting_id', $jobPostingId)
            ->whereNull('withdrawn_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $jobPosting = DB::connection($sourceSchema)->table('job_postings')
            ->where('id', $jobPostingId)
            ->where('status', 'active')
            ->first();

        if (!$jobPosting) {
            throw ValidationException::withMessages(['job' => 'This job is no longer accepting applications.']);
        }

        // Note on atomicity: 'shulesoft'/'safaribook' and the default 'talent'
        // connection are separate PDO sessions (even though the same physical
        // Postgres server), so a single Laravel transaction can't span all of
        // them — Postgres has no automatic distributed-transaction support
        // here. Each step below is its own atomic single-row write, ordered so
        // that if something fails after step 1, the candidate-facing side
        // (step 2, which is what every read in this app goes through) simply
        // never gets created rather than existing in a half-linked state.
        $originConnection = DB::connection($sourceSchema);

        // 1. Origin row — what the school's/client's own recruiter tools see.
        // schema_name must be copied from the job posting explicitly: this is
        // a raw query-builder insert, so it never goes through the origin
        // app's SModel::creating hook that would normally stamp it — and the
        // origin app's recruiter views filter on schema_name via a global
        // scope, so a NULL value here makes the row silently invisible to
        // the school even though it exists (confirmed: shulesoft has this
        // column; safaribook's applications/job_postings tables don't have
        // a schema_name column at all, so it's omitted there).
        $originRow = [
            'job_posting_id' => $jobPostingId,
            'name' => $candidate->full_name,
            'email' => $candidate->email ?: "candidate-{$candidate->id}@talent.shulesoft.africa",
            'phone' => $candidate->phone,
            'application_source' => 'Talent Network',
            'status' => 'new',
            'application_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (property_exists($jobPosting, 'schema_name') && $jobPosting->schema_name) {
            $originRow['schema_name'] = $jobPosting->schema_name;
        }

        $originId = $originConnection->table('applications')->insertGetId($originRow);

        // 2. Network-side rich record — the source of truth every candidate
        // read in this app goes through.
        $health = $this->computeHealthScore($candidate);
        $application = Application::create([
            'candidate_id' => $candidate->id,
            'source_schema' => $sourceSchema,
            'source_job_posting_id' => $jobPostingId,
            'source_application_id' => $originId,
            'last_seen_status' => 'new',
            'applied_at' => now(),
            'ai_health_score' => $health['score'],
            'ai_health_label' => $health['label'],
            'ai_health_why' => $health['why'],
            'ai_health_missing' => $health['missing'],
        ]);

        // 3. Back-reference on the origin row, so it too can be traced to its
        // network application if the school's own tooling ever wants it.
        $originConnection->table('applications')
            ->where('id', $originId)
            ->update(['talent_application_id' => $application->id]);

        return $application;
    }

    /**
     * Rule-based hiring-confidence score, standing in for the AI-assisted
     * version until Phase 4 — same graceful-degradation approach used for
     * job match scoring, never blocking a core flow on OpenAI availability.
     *
     * @return array{score:int, label:string, why:array<int, string>, missing:?string}
     */
    private function computeHealthScore(Candidate $candidate): array
    {
        $score = 30;
        $why = [];

        if ($candidate->verificationItems()->where('status', VerificationStatus::VERIFIED)->exists()) {
            $score += 20;
            $why[] = 'Verified Identity';
        }

        if ($candidate->experiences()->exists()) {
            $score += 20;
            $why[] = 'Experience Matches';
        }

        if ($candidate->educations()->exists()) {
            $score += 15;
            $why[] = 'Education on File';
        }

        if ($candidate->profile_strength >= 50) {
            $score += 15;
            $why[] = 'Complete Profile';
        }

        $missing = match (true) {
            !$candidate->verificationItems()->where('status', VerificationStatus::VERIFIED)->exists() => 'Identity Verification',
            !$candidate->experiences()->exists() => 'Work Experience',
            !$candidate->educations()->exists() => 'Education',
            default => null,
        };

        $label = match (true) {
            $score >= 85 => 'High',
            $score >= 65 => 'Medium',
            default => 'Low',
        };

        return ['score' => min(99, $score), 'label' => $label, 'why' => $why, 'missing' => $missing];
    }
}
