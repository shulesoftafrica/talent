<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\OfficerUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('officer')->check()) {
            return redirect()->route('officer.dashboard');
        }

        return view('officer.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $officer = OfficerUser::where('email', $data['email'])->first();

        if (!$officer || !Hash::check($data['password'], $officer->password)) {
            return back()->withErrors(['email' => 'Those credentials don\'t match our records.'])->onlyInput('email');
        }

        if (!$officer->hasVerificationAccess()) {
            return back()->withErrors(['email' => 'Your account does not have Talent Verification Officer access.'])->onlyInput('email');
        }

        Auth::guard('officer')->login($officer, remember: true);
        $request->session()->regenerate();

        return redirect()->route('officer.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('officer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('officer.login');
    }
}
