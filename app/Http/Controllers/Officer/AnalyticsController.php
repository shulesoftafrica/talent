<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Constant\ReferCountry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Aggregate-only view of activity_logs — most-visited pages, device/browser
 * mix, traffic over time, top countries. Deliberately no per-candidate
 * browsing drill-down (unlike AiUsageController's candidate detail view) —
 * "which pages get traffic" is a legitimate marketing question, "what did
 * this specific candidate browse" is a different, more sensitive one that
 * wasn't asked for.
 */
class AnalyticsController extends Controller
{
    private const ALLOWED_DAYS = [7, 30, 90];

    public function index(Request $request): View
    {
        $days = in_array($request->integer('days'), self::ALLOWED_DAYS, true) ? $request->integer('days') : 30;
        $since = now()->subDays($days)->startOfDay();

        $base = fn () => ActivityLog::where('created_at', '>=', $since);

        $totals = $base()->selectRaw('
                count(*) as views,
                count(distinct candidate_id) as candidates,
                count(distinct session_id) as sessions,
                avg(duration_ms) as avg_duration_ms
            ')->first();

        $topPages = $base()
            ->selectRaw('coalesce(route_name, path) as page_key')
            ->selectRaw('count(*) as views')
            ->selectRaw('avg(duration_ms) as avg_duration_ms')
            ->groupBy('page_key')
            ->orderByDesc('views')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'label' => $this->humanizeRoute($row->page_key),
                'views' => $row->views,
                'avg_duration_ms' => $row->avg_duration_ms,
            ]);

        $deviceMix = $base()->select('device_type')->selectRaw('count(*) as views')
            ->groupBy('device_type')->orderByDesc('views')->get();

        $browserMix = $base()->select('browser')->selectRaw('count(*) as views')
            ->groupBy('browser')->orderByDesc('views')->limit(6)->get();

        $platformMix = $base()->select('platform')->selectRaw('count(*) as views')
            ->groupBy('platform')->orderByDesc('views')->limit(6)->get();

        $dailyTraffic = $base()
            ->selectRaw('date(created_at) as day')
            ->selectRaw('count(*) as views')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $maxDailyViews = max(1, $dailyTraffic->max('views') ?? 1);

        $topCountriesRaw = $base()->whereNotNull('country_id')
            ->select('country_id')->selectRaw('count(*) as views')
            ->groupBy('country_id')->orderByDesc('views')->limit(8)->get();

        $countryNames = ReferCountry::whereIn('id', $topCountriesRaw->pluck('country_id'))
            ->pluck('country', 'id');

        $topCountries = $topCountriesRaw->map(fn ($row) => [
            'name' => $countryNames->get($row->country_id, "#{$row->country_id}"),
            'views' => $row->views,
        ]);

        return view('officer.analytics', [
            'officer' => $request->user('officer'),
            'days' => $days,
            'totals' => $totals,
            'topPages' => $topPages,
            'deviceMix' => $deviceMix,
            'browserMix' => $browserMix,
            'platformMix' => $platformMix,
            'dailyTraffic' => $dailyTraffic,
            'maxDailyViews' => $maxDailyViews,
            'topCountries' => $topCountries,
        ]);
    }

    private function humanizeRoute(?string $key): string
    {
        if (!$key) {
            return 'Unknown';
        }

        if (!str_contains($key, '.')) {
            return $key;
        }

        $short = str_replace(['candidate.', 'officer.'], '', $key);

        return ucwords(str_replace(['.', '-', '_'], ' ', $short));
    }
}
