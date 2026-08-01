<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Reusable client for the unified notification service
 * (https://notifications.shulesoft.africa/docs/getting-started) — sends a
 * single email/SMS notification. Copied from shulesoft_newversion's
 * App\Services\Notifications\UnifiedNotificationClient (WhatsApp OTP goes
 * through MetaWhatsAppService instead, per product direction).
 *
 * Every call fails soft: on any network error, non-2xx response, or
 * malformed payload it logs and returns null rather than throwing.
 */
class UnifiedNotificationClient
{
    /**
     * @param array $payload Must include schema_name, channel (email|sms), to,
     *                       message, and for email optionally attachment (base64),
     *                       attachment_name, attachment_type, subject.
     * @return array{success:bool,message_id:mixed,status:?string}|null
     */
    public function send(array $payload): ?array
    {
        $baseUrl = config('services.notification.base_url');
        $apiKey = config('services.notification.api_token');
        $bearerToken = config('services.notification.bearer_token');

        if (empty($apiKey) && empty($bearerToken)) {
            Log::warning('UnifiedNotificationClient: no API token configured; skipping send', ['channel' => $payload['channel'] ?? null]);
            return null;
        }

        $url = rtrim($baseUrl, '/') . '/api/notifications/send';

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if (!empty($apiKey)) {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }
        if (!empty($bearerToken)) {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('UnifiedNotificationClient: network error', ['error' => $error, 'channel' => $payload['channel'] ?? null]);
            return null;
        }

        $decoded = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
            Log::error('UnifiedNotificationClient: send failed', [
                'http_code' => $httpCode,
                'body' => $response,
                'channel' => $payload['channel'] ?? null,
            ]);
            return null;
        }

        if (empty($decoded['success'])) {
            Log::error('UnifiedNotificationClient: API reported failure', ['body' => $decoded]);
            return null;
        }

        return $decoded;
    }
}
