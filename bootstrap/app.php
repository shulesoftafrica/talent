<?php

use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Neither guard has a 'login'-named route — candidates authenticate
        // via OTP from the landing page, officers via a plain form — so send
        // unauthenticated guests to the right one instead of letting the
        // default Authenticate middleware throw looking for route('login').
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('officer.*')
            ? route('officer.login')
            : route('landing'));

        // External callback from the Shulesoft Billing Platform — no
        // session/CSRF token to send, verified instead via the shared
        // webhook secret header inside BillingWebhookController.
        $middleware->validateCsrfTokens(except: ['webhooks/billing', 'activity/ping']);

        $middleware->web(append: [SetLocale::class, TrackUserActivity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
