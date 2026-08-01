<?php

namespace App\Http\Middleware;

use App\Services\Applications\NotificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opportunistically checks the logged-in candidate's applications for
 * origin-side status changes and creates notifications for the
 * attention-worthy ones, on every authenticated page load. See
 * NotificationService for why this is lazy rather than queued/cron'd.
 */
class SyncApplicationNotifications
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($candidate = Auth::guard('candidate')->user()) {
            $this->notifications->syncForCandidate($candidate);
        }

        return $next($request);
    }
}
