<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Help & Feedback bubble submission — see the migration's
 * docblock for why every category shares this one table.
 */
class FeedbackItem extends Model
{
    protected $fillable = [
        'candidate_id', 'category', 'subcategory', 'sentiment', 'message',
        'context_label', 'context_path',
        'related_application_id', 'related_source_schema', 'related_job_posting_id',
        'user_agent', 'status', 'priority',
        'assigned_officer_id', 'staff_response', 'resolution', 'internal_notes',
        'responded_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public const CATEGORIES = ['help', 'feedback', 'problem', 'idea'];
    public const STATUSES = ['new', 'in_review', 'responded', 'resolved'];
    public const PRIORITIES = ['critical', 'high', 'normal', 'feedback'];

    /**
     * Every selectable subcategory across the four categories, and the
     * priority it maps to — this is the whole "Priority Logic" spec turned
     * into data instead of scattered if/else. A subcategory not listed here
     * (defensive: the frontend always sends one of these) falls back to
     * 'normal' for help/problem, 'feedback' for feedback/idea.
     *
     * Verification-related problem reports are classified Critical rather
     * than High: the spec's Critical bucket names "wrong identity/
     * verification information" and the High bucket names "verification
     * blocked" — both are realistically the same candidate-selectable
     * option (problem.verification_problem), and identity/security-adjacent
     * reports are better over-triaged than under-triaged.
     */
    private const PRIORITY_MAP = [
        'help' => [
            'cannot_apply' => 'high',
            'profile_completion' => 'normal',
            'verification' => 'normal',
            'job_matches' => 'normal',
            'other' => 'normal',
        ],
        'problem' => [
            'job_application_problem' => 'high',
            'verification_problem' => 'critical',
            'incorrect_information' => 'high',
            'profile_problem' => 'normal',
            'notification_problem' => 'normal',
            'website_error' => 'normal',
            'other' => 'normal',
        ],
    ];

    public static function classifyPriority(string $category, ?string $subcategory): string
    {
        if (in_array($category, ['feedback', 'idea'], true)) {
            return 'feedback';
        }

        return self::PRIORITY_MAP[$category][$subcategory] ?? 'normal';
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function relatedApplication(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'related_application_id');
    }

    /**
     * Soft cross-connection reference (admin.users) — see the migration's
     * comment on assigned_officer_id for why this isn't a real FK. Callers
     * needing the officer's name should look it up explicitly rather than
     * relying on Eloquent eager-loading here.
     */
    public function assignedOfficerName(): ?string
    {
        if (!$this->assigned_officer_id) {
            return null;
        }

        return OfficerUser::find($this->assigned_officer_id)?->name;
    }
}
