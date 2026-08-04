<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Mints an access token for the Shulesoft Billing Platform via the OAuth
 * client-credentials grant, using a pre-provisioned client
 * (SHULESOFT_CLIENT_ID / SHULESOFT_CLIENT_SECRET — see the platform's own
 * dashboard at api.safaribank.africa/dashboard/organization, org "Shulesoft
 * Company Limited"). The static BILLING_ACCESS_TOKEN this app previously
 * relied on pointed at a personal_access_tokens row that no longer exists
 * (confirmed via direct read of billing.personal_access_tokens) — this
 * client-credentials token is the real, currently-working path.
 *
 * Token is cached for ~89 days (platform issues ~90-day tokens);
 * ShulesoftBillingClient falls back to the static token if this is ever
 * unavailable (e.g. credentials not configured in a given environment).
 */
class ShulesoftAuthService
{
    private const CACHE_KEY_ACCESS_TOKEN = 'shulesoft_access_token';
    private const CACHE_KEY_TOKEN_EXPIRES = 'shulesoft_token_expires_at';

    private const TOKEN_LIFETIME = 89 * 24 * 60 * 60; // 89 days, 1-day safety buffer under the platform's 90-day expiry

    public static function getAccessToken(): ?string
    {
        $token = Cache::get(self::CACHE_KEY_ACCESS_TOKEN);
        $expiresAt = Cache::get(self::CACHE_KEY_TOKEN_EXPIRES);

        if ($token && $expiresAt && time() < $expiresAt) {
            return $token;
        }

        try {
            return self::refreshAccessToken();
        } catch (Exception $e) {
            Log::warning('ShulesoftAuthService: could not mint access token, caller should fall back to static token', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public static function refreshAccessToken(): string
    {
        $clientId = config('services.shulesoft_billing.client_id');
        $clientSecret = config('services.shulesoft_billing.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new Exception('SHULESOFT_CLIENT_ID / SHULESOFT_CLIENT_SECRET not configured.');
        }

        $apiUrl = rtrim(config('services.shulesoft_billing.api_url'), '/');

        $response = self::httpClient()->post($apiUrl . '/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => '*',
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to get access token from client credentials: ' . $response->body());
        }

        $token = $response->json('access_token');

        if (!$token) {
            throw new Exception('Client-credentials response had no access_token: ' . $response->body());
        }

        $expiresAt = time() + self::TOKEN_LIFETIME;
        Cache::put(self::CACHE_KEY_ACCESS_TOKEN, $token, self::TOKEN_LIFETIME);
        Cache::put(self::CACHE_KEY_TOKEN_EXPIRES, $expiresAt, self::TOKEN_LIFETIME);

        Log::info('ShulesoftAuthService: access token refreshed', ['expires_at' => date('Y-m-d H:i:s', $expiresAt)]);

        return $token;
    }

    private static function httpClient()
    {
        return Http::timeout((int) config('services.shulesoft_billing.timeout', 30))
            ->connectTimeout((int) config('services.shulesoft_billing.connect_timeout', 5))
            ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json']);
    }

    /**
     * Debugging helper — clears the cached token so the next call re-authenticates from scratch.
     */
    public static function clearAuthCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACCESS_TOKEN);
        Cache::forget(self::CACHE_KEY_TOKEN_EXPIRES);
    }
}
