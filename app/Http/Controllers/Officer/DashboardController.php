<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Services\Verification\VerificationStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $officer = Auth::guard('officer')->user();

        $stats = [
            ['label' => 'Total Candidates', 'value' => number_format(DB::table('candidates')->count()), 'sub' => 'On the network'],
            ['label' => 'Premium Candidates', 'value' => number_format(DB::table('candidates')->where('is_premium', true)->count()), 'sub' => 'Paying subscribers'],
            ['label' => 'Verification Items Pending', 'value' => number_format(DB::table('candidate_verification_items')->whereIn('status', VerificationStatus::AWAITING_OFFICER)->count()), 'sub' => 'Awaiting officer review'],
            ['label' => 'Verification Items Verified', 'value' => number_format(DB::table('candidate_verification_items')->where('status', VerificationStatus::VERIFIED)->count()), 'sub' => 'Approved to date'],
            ['label' => 'Applications Submitted', 'value' => number_format(DB::table('applications')->count()), 'sub' => 'Via Talent Network'],
            ['label' => 'Candidates Hired', 'value' => '—', 'sub' => 'Tracked per application status'],
        ];

        $regions = DB::table('candidates')
            ->select('current_location', DB::raw('count(*) as total'))
            ->whereNotNull('current_location')
            ->groupBy('current_location')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $professions = DB::table('candidates')
            ->select('profession', DB::raw('count(*) as total'))
            ->whereNotNull('profession')
            ->groupBy('profession')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $totalCandidates = max(1, DB::table('candidates')->count());

        return view('officer.dashboard', [
            'officer' => $officer,
            'stats' => $stats,
            'regions' => $regions,
            'professions' => $professions,
            'totalCandidates' => $totalCandidates,
        ]);
    }
}
