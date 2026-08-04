<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives the "time on page" beacon fired by layout.blade.php when a
 * candidate leaves a page (navigator.sendBeacon, so it fires reliably even
 * on tab close). No CSRF token — sendBeacon can't attach custom headers —
 * but the page_token is a per-view UUID nobody outside that one page
 * render knows, and this can only ever update duration_ms on a row that
 * doesn't have one yet, so there's nothing meaningful to forge.
 */
class ActivityPingController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'duration_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
        ]);

        ActivityLog::where('page_token', $data['token'])
            ->whereNull('duration_ms')
            ->update(['duration_ms' => $data['duration_ms']]);

        return response()->json(['success' => true]);
    }
}
