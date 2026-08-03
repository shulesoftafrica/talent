<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Candidate;
use App\Services\AI\AiFeature;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiUsageController extends Controller
{
    public function index(Request $request): View
    {
        $totals = AiUsageLog::selectRaw('count(*) as requests, coalesce(sum(total_tokens), 0) as tokens, coalesce(sum(estimated_cost_usd), 0) as cost')
            ->first();

        $byCandidate = AiUsageLog::query()
            ->whereNotNull('candidate_id')
            ->select('candidate_id')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(total_tokens), 0) as tokens')
            ->selectRaw('coalesce(sum(estimated_cost_usd), 0) as cost')
            ->selectRaw('max(created_at) as last_request_at')
            ->groupBy('candidate_id')
            ->orderByDesc('cost')
            ->paginate(20)
            ->withQueryString();

        $candidateIds = $byCandidate->pluck('candidate_id');
        $candidates = Candidate::whereIn('id', $candidateIds)->get(['id', 'full_name', 'phone', 'email'])->keyBy('id');

        $unattributed = AiUsageLog::whereNull('candidate_id')
            ->selectRaw('count(*) as requests, coalesce(sum(total_tokens), 0) as tokens, coalesce(sum(estimated_cost_usd), 0) as cost')
            ->first();

        $byFeature = AiUsageLog::query()
            ->select('feature')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(estimated_cost_usd), 0) as cost')
            ->groupBy('feature')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'feature' => $row->feature,
                'label' => AiFeature::label($row->feature),
                'requests' => $row->requests,
                'cost' => $row->cost,
            ]);

        $byProvider = AiUsageLog::query()
            ->select('provider')
            ->selectRaw("count(*) filter (where status = 'success') as successes")
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(estimated_cost_usd), 0) as cost')
            ->groupBy('provider')
            ->orderByDesc('cost')
            ->get();

        $selectedCandidate = null;
        $requestLog = null;

        if ($request->filled('candidate')) {
            $selectedCandidate = Candidate::find($request->integer('candidate'));

            if ($selectedCandidate) {
                $requestLog = $selectedCandidate->aiUsageLogs()->latest('created_at')->paginate(25, ['*'], 'log_page');
            }
        }

        return view('officer.ai-usage', [
            'officer' => $request->user('officer'),
            'totals' => $totals,
            'byCandidate' => $byCandidate,
            'candidates' => $candidates,
            'unattributed' => $unattributed,
            'byFeature' => $byFeature,
            'byProvider' => $byProvider,
            'selectedCandidate' => $selectedCandidate,
            'requestLog' => $requestLog,
        ]);
    }
}
