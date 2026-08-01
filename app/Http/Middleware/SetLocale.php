<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from (in order) the session, then the
 * browser's Accept-Language header, falling back to config('locales.default').
 * The session value is set by LanguageController::switch() when a candidate,
 * officer, or anonymous visitor picks a language from the switcher.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales.supported'));

        $locale = session('locale');

        if (!$locale || !in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported) ?? config('locales.default');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
