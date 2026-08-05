<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Shulesoft unified notification service — a single
 * endpoint that sends email, SMS, or WhatsApp from one JSON object.
 * See https://notifications.shulesoft.africa/docs/getting-started
 *
 * schema_name and the bearer token are injected from config on every call
 * so callers never repeat them — just pass the channel-specific fields
 * (channel, to, message, and e.g. subject for email or type for WhatsApp).
 *
 * Every call fails soft: on any network error, non-2xx response, or a
 * response the API itself reports as unsuccessful, it logs and returns
 * null rather than throwing — no notification-channel outage should ever
 * block a candidate-facing flow (signup, OTP, etc.).
 */
class UnifiedNotificationClient
{
    /**
     * @param array $payload Channel-specific fields — see the docs for the
     *                        full per-channel field list (email: subject;
     *                        whatsapp: type=official|wasender, attachment*,
     *                        metadata, tags, webhook_url...). schema_name
     *                        is added automatically and should not be passed.
     * @return array{success:bool,message_id:mixed,external_id:?string,status:?string,provider:?string,data:array}|null
     */
    public function send(array $payload): ?array
    {
        $baseUrl = config('services.notification.base_url');
        $bearerToken = config('services.notification.bearer_token');
        $schemaName = config('services.notification.schema_name');

        if (empty($bearerToken) || empty($schemaName)) {
            Log::warning('UnifiedNotificationClient: missing bearer token or schema_name; skipping send', [
                'channel' => $payload['channel'] ?? null,
            ]);

            return null;
        }

        $response = Http::withToken($bearerToken)
            ->acceptJson()
            ->timeout(30)
            ->post(rtrim($baseUrl, '/') . '/api/notifications/send', [
                'schema_name' => $schemaName,
                ...$payload,
            ]);

        if (!$response->successful()) {
            Log::error('UnifiedNotificationClient: send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'channel' => $payload['channel'] ?? null,
            ]);

            return null;
        }

        $decoded = $response->json();

        if (!is_array($decoded) || empty($decoded['success'])) {
            Log::error('UnifiedNotificationClient: API reported failure', ['body' => $decoded]);

            return null;
        }

        return $decoded;
    }
}
