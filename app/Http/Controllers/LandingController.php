<?php

namespace App\Http\Controllers;

use App\Models\Constant\ReferCountry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::guard('candidate')->check()) {
            return redirect()->route('candidate.jobs');
        }

        return view('landing', [
            'countries' => ReferCountry::orderBy('country')->get(['id', 'country']),
        ]);
    }
}
