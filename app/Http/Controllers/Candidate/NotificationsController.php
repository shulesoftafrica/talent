<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function open(Notification $notification): RedirectResponse
    {
        if ($notification->candidate_id !== Auth::guard('candidate')->id()) {
            abort(403);
        }

        if (!$notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return redirect($notification->action_url ?? route('candidate.applications.index'));
    }
}
