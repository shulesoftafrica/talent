<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth alongside AuthController::login's check — re-verifies on
 * every request in case the role/permission link is ever revoked while an
 * officer still has an active session.
 */
class EnsureOfficerHasVerificationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $officer = Auth::guard('officer')->user();

        if (!$officer || !$officer->hasVerificationAccess()) {
            Auth::guard('officer')->logout();
            $request->session()->invalidate();

            abort(403, 'Your account does not have Talent Verification Officer access.');
        }

        return $next($request);
    }
}
