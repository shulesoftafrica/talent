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

        $send = fn () => Http::withToken($bearerToken)
            ->acceptJson()
            ->timeout(30)
            ->post(rtrim($baseUrl, '/') . '/api/notifications/send', [
                'schema_name' => $schemaName,
                ...$payload,
            ]);

        $response = $send();

        // The API enforces a hard 2-requests-per-second cap and tells us
        // exactly how long to wait in its own 429 body — confirmed live
        // that this fires often enough in normal (non-batch) usage to be
        // worth one respectful retry rather than silently dropping the
        // notification, same lesson already applied to the bulk invite
        // script's own call pattern.
        if ($response->status() === 429) {
            $retryAfter = (float) ($response->json('retry_after') ?? 1);
            usleep((int) (min($retryAfter, 5) * 1_000_000));
            $response = $send();
        }

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

        // `success` here only means the API accepted the request -- confirmed
        // live: it stays true even when the downstream provider (e.g. Resend)
        // actually failed to deliver (a monthly sending quota being
        // exhausted silently dropped OTP emails for hours, with every caller
        // told the send succeeded). The real outcome is the nested delivery
        // status; only a terminal 'failed' is treated as a failure here --
        // 'pending'/'queued' are still legitimately in flight and should not
        // be reported as broken.
        $deliveryStatus = $decoded['data']['status'] ?? null;
        $isFailed = ($deliveryStatus === 'failed') || !empty($decoded['data']['is_failed']);

        if ($isFailed) {
            Log::error('UnifiedNotificationClient: delivery failed', [
                'status' => $deliveryStatus,
                'provider' => $decoded['data']['provider'] ?? $decoded['provider'] ?? null,
                'error_message' => $decoded['data']['error_message'] ?? null,
                'channel' => $payload['channel'] ?? null,
                'to' => $payload['to'] ?? null,
            ]);

            return null;
        }

        return $decoded;
    }
}
