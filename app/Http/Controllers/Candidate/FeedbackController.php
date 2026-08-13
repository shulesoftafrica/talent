<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\FeedbackItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Backend for the Help & Feedback bubble — one endpoint creates every kind
 * of submission (help/feedback/problem/idea/quick-rating), and one returns
 * the candidate's own history for the modal's "Your previous requests"
 * section. Deliberately not a full ticketing API: no edit/delete from the
 * candidate side, no threaded replies — see the feature's own design note
 * about staying a fast collection tool, not a live-chat platform.
 */
class FeedbackController extends Controller
{
    private const SUBCATEGORIES = [
        'help' => ['profile_completion', 'verification', 'cannot_apply', 'job_matches', 'other'],
        'problem' => ['job_application_problem', 'profile_problem', 'verification_problem', 'incorrect_information', 'notification_problem', 'website_error', 'other'],
        'feedback' => ['job_matching', 'profile', 'applications', 'verification', 'notifications', 'user_experience', 'other'],
        // 'idea' has no fixed subcategory list — free text only.
    ];

    public function store(Request $request): JsonResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $data = $request->validate([
            'category' => ['required', Rule::in(FeedbackItem::CATEGORIES)],
            'subcategory' => ['nullable', 'string', 'max:60'],
            'sentiment' => ['nullable', Rule::in(['like', 'neutral', 'dislike'])],
            'message' => ['nullable', 'string', 'max:2000'],
            'context_label' => ['nullable', 'string', 'max:120'],
            'context_path' => ['nullable', 'string', 'max:255'],
            'related_application_uuid' => ['nullable', 'string'],
        ]);

        if (in_array($data['category'], ['help', 'problem', 'idea'], true) && trim((string) ($data['message'] ?? '')) === '') {
            return response()->json(['success' => false, 'message' => 'Please describe it a little so we can help.'], 422);
        }

        if (isset($data['subcategory']) && isset(self::SUBCATEGORIES[$data['category']])
            && !in_array($data['subcategory'], self::SUBCATEGORIES[$data['category']], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid subcategory for this category.'], 422);
        }

        $relatedApplication = !empty($data['related_application_uuid'])
            ? Application::where('uuid', $data['related_application_uuid'])->where('candidate_id', $candidate->id)->first()
            : null;

        $item = FeedbackItem::create([
            'candidate_id' => $candidate->id,
            'category' => $data['category'],
            'subcategory' => $data['subcategory'] ?? null,
            'sentiment' => $data['sentiment'] ?? null,
            'message' => $data['message'] ?? null,
            'context_label' => $data['context_label'] ?? null,
            'context_path' => $data['context_path'] ?? null,
            'related_application_id' => $relatedApplication?->id,
            'related_source_schema' => $relatedApplication?->source_schema,
            'related_job_posting_id' => $relatedApplication?->source_job_posting_id,
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'status' => 'new',
            'priority' => FeedbackItem::classifyPriority($data['category'], $data['subcategory'] ?? null),
        ]);

        return response()->json(['success' => true, 'id' => $item->id]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $items = $candidate->feedbackItems()
            ->where(fn ($q) => $q->where('category', '!=', 'feedback')->orWhereNull('sentiment')->orWhere('category', 'feedback'))
            ->whereNotIn('subcategory', self::quickRatingEventKeys())
            ->latest()
            ->limit(10)
            ->get(['id', 'category', 'subcategory', 'message', 'status', 'created_at'])
            ->map(fn (FeedbackItem $item) => [
                'id' => $item->id,
                'label' => self::historyLabel($item),
                'status' => $item->status,
                'status_label' => self::statusLabel($item->status),
                'submitted_at' => $item->created_at->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'items' => $items]);
    }

    public static function quickRatingEventKeys(): array
    {
        return ['after_profile_completion', 'after_job_application', 'after_verification', 'after_career_assessment'];
    }

    private static function historyLabel(FeedbackItem $item): string
    {
        $categoryLabels = [
            'help' => 'Help request',
            'problem' => 'Problem report',
            'feedback' => 'Feedback',
            'idea' => 'Suggestion',
        ];

        $subLabels = [
            'profile_completion' => 'profile completion',
            'verification' => 'verification',
            'cannot_apply' => 'applying for a job',
            'job_matches' => 'job matches',
            'job_application_problem' => 'a job application',
            'profile_problem' => 'your profile',
            'verification_problem' => 'verification',
            'incorrect_information' => 'incorrect information',
            'notification_problem' => 'notifications',
            'website_error' => 'a website error',
            'job_matching' => 'job matching',
            'profile' => 'profile',
            'applications' => 'applications',
            'notifications' => 'notifications',
            'user_experience' => 'the experience',
            'other' => 'something else',
        ];

        $base = $categoryLabels[$item->category] ?? 'Request';
        $sub = $subLabels[$item->subcategory] ?? null;

        return $sub ? "{$base} — {$sub}" : $base;
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'New',
            'in_review' => 'In Review',
            'responded' => 'Responded',
            'resolved' => 'Resolved',
            default => ucfirst($status),
        };
    }
}
