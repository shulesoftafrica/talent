<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use App\Services\Applications\ApplicationStatusMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Application extends Model
{
    use HasUuidRouteKey;

    protected $fillable = [
        'candidate_id', 'source_schema', 'source_job_posting_id', 'source_application_id', 'source_channel',
        'last_seen_status', 'ai_health_score', 'ai_health_label', 'ai_health_why', 'ai_health_missing',
        'interview_date', 'interview_type', 'interview_duration', 'applied_at',
        'interview_response', 'interview_response_note', 'interview_responded_at',
        'withdrawal_reason', 'withdrawal_reason_other', 'withdrawn_at',
        'new_employer_name', 'new_position', 'new_start_date', 'found_via_shulesoft',
    ];

    protected function casts(): array
    {
        return [
            'ai_health_why' => 'array',
            'interview_date' => 'date',
            'applied_at' => 'datetime',
            'interview_responded_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'new_start_date' => 'date',
            'found_via_shulesoft' => 'boolean',
        ];
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    private ?object $originRowCache = null;
    private bool $originRowLoaded = false;

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * The live row from shulesoft.applications or safaribook.applications —
     * the recruiter-side source of truth for status/review data. Fetched
     * once per model instance and cached in-memory only (never persisted),
     * so display always reflects whatever the school's own tools show.
     */
    public function originRow(): ?object
    {
        if (!$this->originRowLoaded) {
            $this->originRowCache = $this->source_application_id
                ? DB::connection($this->source_schema)->table('applications')->find($this->source_application_id)
                : null;
            $this->originRowLoaded = true;
        }

        return $this->originRowCache;
    }

    public function jobPosting(): ?object
    {
        return DB::connection($this->source_schema)->table('job_postings')->find($this->source_job_posting_id);
    }

    private ?object $scheduledInterviewCache = null;
    private bool $scheduledInterviewLoaded = false;

    /**
     * The latest interview row from shulesoft.interviews or
     * safaribook.interviews, if the school booked one via its own
     * "Schedule Interview" flow — live, never synced/stored on this model,
     * same reasoning as originRow(). Many schools currently just flip the
     * application status without booking a structured date, so this is
     * often null even at the 'Interview Invited' stage — that reflects
     * what the school actually recorded, not a display bug.
     *
     * Excludes 'cancelled' rows deliberately: the school cancelling an
     * interview updates this same row in place (no new row created), so
     * without this filter a cancelled interview kept showing here exactly
     * as if it were still upcoming.
     */
    public function scheduledInterview(): ?object
    {
        if (!$this->scheduledInterviewLoaded) {
            $this->scheduledInterviewCache = $this->source_application_id
                ? DB::connection($this->source_schema)->table('interviews')
                    ->where('application_id', $this->source_application_id)
                    ->where('status', '!=', 'cancelled')
                    ->orderByDesc('id')
                    ->first()
                : null;
            $this->scheduledInterviewLoaded = true;
        }

        return $this->scheduledInterviewCache;
    }

    public function status(): string
    {
        return $this->originRow()->status ?? 'new';
    }

    public function statusMeta(): array
    {
        return ApplicationStatusMapper::resolve($this->status());
    }
}
