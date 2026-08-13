<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\FeedbackItem;
use App\Models\OfficerUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The "product intelligence" side of the Help & Feedback feature (see
 * FeedbackController on the candidate side for the submission API this
 * reads from). index() doubles as both an operational queue (filters,
 * search, per-item triage) and a lightweight dashboard (open/urgent/new
 * counts, top problems, sentiment split, most-requested improvements) —
 * kept as one page rather than two per the feature spec's "operational
 * visibility, not a BI tool" framing.
 */
class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $officer = Auth::guard('officer')->user();

        $query = FeedbackItem::with('candidate')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->query('search') . '%';
                $q->where(function ($q) use ($term) {
                    $q->where('message', 'ilike', $term)
                        ->orWhereHas('candidate', fn ($c) => $c->where('full_name', 'ilike', $term));
                });
            });

        $items = $query->latest()->paginate(20)->withQueryString();

        return view('officer.feedback.index', [
            'officer' => $officer,
            'items' => $items,
            'filters' => $request->only(['category', 'status', 'priority', 'search']),
            'stats' => $this->dashboardStats(),
            'topProblems' => $this->topProblems(),
            'sentiment' => $this->sentimentSplit(),
            'topIdeas' => $this->topIdeas(),
        ]);
    }

    public function show(FeedbackItem $item): View
    {
        $item->load('candidate');

        $previous = FeedbackItem::where('candidate_id', $item->candidate_id)
            ->where('id', '!=', $item->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('officer.feedback.show', [
            'officer' => Auth::guard('officer')->user(),
            'item' => $item,
            'previous' => $previous,
            'assignableOfficers' => $this->assignableOfficers(),
        ]);
    }

    public function updateStatus(FeedbackItem $item, Request $request): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(FeedbackItem::STATUSES)]]);

        $item->status = $data['status'];
        if ($data['status'] === 'resolved' && !$item->resolved_at) {
            $item->resolved_at = now();
        }
        $item->save();

        return redirect()->route('officer.feedback.show', $item)->with('status', __('feedback.ops_saved'));
    }

    public function assign(FeedbackItem $item, Request $request): RedirectResponse
    {
        $data = $request->validate(['officer_id' => ['nullable', 'integer']]);

        $item->assigned_officer_id = $data['officer_id'] ?: null;
        $item->save();

        return redirect()->route('officer.feedback.show', $item)->with('status', __('feedback.ops_saved'));
    }

    public function respond(FeedbackItem $item, Request $request): RedirectResponse
    {
        $data = $request->validate(['staff_response' => ['required', 'string', 'max:2000']]);

        $item->staff_response = $data['staff_response'];
        $item->responded_at = now();
        if ($item->status === 'new') {
            $item->status = 'in_review';
        }
        $item->save();

        return redirect()->route('officer.feedback.show', $item)->with('status', __('feedback.ops_saved'));
    }

    public function addNote(FeedbackItem $item, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('internal_notes', $data)) {
            $item->internal_notes = $data['internal_notes'];
        }
        if (array_key_exists('resolution', $data)) {
            $item->resolution = $data['resolution'];
        }
        $item->save();

        return redirect()->route('officer.feedback.show', $item)->with('status', __('feedback.ops_saved'));
    }

    private function dashboardStats(): array
    {
        $open = FeedbackItem::where('status', '!=', 'resolved')->count();
        $urgent = FeedbackItem::where('priority', 'critical')->where('status', '!=', 'resolved')->count();
        $newToday = FeedbackItem::whereDate('created_at', now()->toDateString())->count();
        $resolvedThisWeek = FeedbackItem::where('status', 'resolved')->where('resolved_at', '>=', now()->startOfWeek())->count();

        $avgResponseMinutes = FeedbackItem::whereNotNull('responded_at')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (responded_at - created_at)) / 60) as avg_minutes'))
            ->value('avg_minutes');

        return [
            'open' => $open,
            'urgent' => $urgent,
            'new_today' => $newToday,
            'resolved_this_week' => $resolvedThisWeek,
            'avg_response' => $this->formatDuration($avgResponseMinutes),
        ];
    }

    private function formatDuration(?float $minutes): string
    {
        if (!$minutes) {
            return '—';
        }

        if ($minutes < 60) {
            return round($minutes) . 'm';
        }

        return round($minutes / 60, 1) . 'h';
    }

    private function topProblems(): array
    {
        return FeedbackItem::where('category', 'problem')
            ->whereNotNull('subcategory')
            ->select('subcategory', DB::raw('count(*) as total'))
            ->groupBy('subcategory')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['label' => __('feedback.problem_' . $row->subcategory), 'count' => $row->total])
            ->all();
    }

    private function sentimentSplit(): array
    {
        $rows = FeedbackItem::where('category', 'feedback')
            ->whereNotNull('sentiment')
            ->select('sentiment', DB::raw('count(*) as total'))
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        $total = $rows->sum();
        if ($total === 0) {
            return ['like' => 0, 'neutral' => 0, 'dislike' => 0, 'total' => 0];
        }

        return [
            'like' => (int) round(($rows->get('like', 0) / $total) * 100),
            'neutral' => (int) round(($rows->get('neutral', 0) / $total) * 100),
            'dislike' => (int) round(($rows->get('dislike', 0) / $total) * 100),
            'total' => $total,
        ];
    }

    private function topIdeas(): array
    {
        return FeedbackItem::where('category', 'idea')
            ->whereNotNull('message')
            ->latest()
            ->limit(6)
            ->pluck('message')
            ->all();
    }

    private function assignableOfficers(): array
    {
        return DB::connection('admin')->table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('permission_role as pr', 'pr.role_id', '=', 'ru.role_id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('p.name', OfficerUser::VERIFICATION_PERMISSION)
            ->distinct()
            ->orderBy('u.name')
            ->pluck('u.name', 'u.id')
            ->all();
    }
}
