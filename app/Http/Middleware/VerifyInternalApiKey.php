<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards server-to-server endpoints called by ShuleSoft (e.g. the
 * hiring-manager job-match lookup) — a single shared secret, not a
 * per-tenant key, since the caller here is one internal system, not many
 * external third parties. Mirrors ShuleSoft's own SafariBookBillingClient /
 * TenantApiKeyMiddleware convention (X-API-Key header) rather than the
 * HMAC-signature scheme BillingWebhookController uses, since this is a
 * synchronous request/response call, not a fire-and-forget webhook.
 */
class VerifyInternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.shulesoft_internal.api_key');

        if (!$configured) {
            Log::warning('VerifyInternalApiKey: SHULESOFT_INTERNAL_API_KEY not configured — rejecting request', ['ip' => $request->ip()]);

            return response()->json([
                'success' => false,
                'message' => 'Internal API is not configured on this server.',
            ], 503);
        }

        $provided = $request->header('X-API-Key');

        if (!$provided || !hash_equals($configured, $provided)) {
            Log::warning('VerifyInternalApiKey: invalid or missing X-API-Key', ['ip' => $request->ip()]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid API key.',
            ], 401);
        }

        return $next($request);
    }
}
