<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (!array_key_exists($locale, config('locales.supported'))) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        return back();
    }
}
