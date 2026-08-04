<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Candidate;
use App\Services\Analytics\UserAgentParser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs one activity_logs row per real page view — candidate/session,
 * device, browser, referrer, and access time — for marketing/product
 * analytics (most-visited pages, device mix, traffic over time).
 *
 * Only real page navigations are logged: GET requests whose response is
 * actual HTML (checked via the response Content-Type, after the request
 * has run) — this naturally excludes JSON/AJAX endpoints, redirects,
 * webhooks, and asset requests without needing to hand-maintain an
 * exclude-list of routes.
 *
 * "Time on page" isn't knowable at request time — the page has to actually
 * be viewed first. A page_token is generated up front and shared into the
 * view (see resources/views/components/layout.blade.php's meta tag +
 * beacon script); when the candidate navigates away, a
 * navigator.sendBeacon() call to ActivityPingController fills in
 * duration_ms on this same row after the fact.
 *
 * Location: only resolves country_id from the candidate's own profile
 * (Candidate::country_id) when they're logged in and have set it — this
 * is not live IP geolocation for anonymous visitors. ip_address is always
 * captured, so real geolocation can be added later (a GeoIP database or
 * API) without needing to touch already-collected rows.
 */
class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $pageToken = null;

        if ($this->looksLikePageRequest($request)) {
            $pageToken = (string) Str::uuid();
            view()->share('activityPageToken', $pageToken);
        }

        $response = $next($request);

        if ($pageToken && $this->isHtmlResponse($response)) {
            $this->record($request, $pageToken);
        }

        return $response;
    }

    private function looksLikePageRequest(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        return !$request->is('webhooks/*', 'reference/*', 'up', '_debugbar/*');
    }

    private function isHtmlResponse(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function record(Request $request, string $pageToken): void
    {
        try {
            /** @var Candidate|null $candidate */
            $candidate = Auth::guard('candidate')->user();
            $ua = app(UserAgentParser::class)->parse($request->userAgent());

            ActivityLog::create([
                'candidate_id' => $candidate?->id,
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'page_token' => $pageToken,
                'method' => $request->method(),
                'path' => '/' . ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'referrer_path' => $this->refererPath($request),
                'ip_address' => $request->ip(),
                'country_id' => $candidate?->country_id,
                'device_type' => $ua['device_type'],
                'browser' => $ua['browser'],
                'platform' => $ua['platform'],
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Analytics must never break the actual page.
            Log::warning('TrackUserActivity: failed to record page view', ['error' => $e->getMessage()]);
        }
    }

    private function refererPath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (!$referer) {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        return $path ? substr($path, 0, 512) : null;
    }
}
