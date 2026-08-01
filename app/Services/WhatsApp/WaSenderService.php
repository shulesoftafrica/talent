<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Minimal WaSender API client, used only as a fallback when the Meta WhatsApp
 * Business API is unavailable (see MetaWhatsAppService::fallbackToWaSender).
 * Copied from shulesoft's App\Services\WaSenderService.
 *
 * @link https://wasenderapi.com
 */
class WaSenderService
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('WASENDER_API_BASE_URL', 'https://www.wasenderapi.com/api'), '/');
        $this->token = env('WASENDER_API_TOKEN', '');
        $this->timeout = (int) env('WASENDER_TIMEOUT_SECONDS', 30);
    }

    /**
     * Send a plain text WhatsApp message.
     *
     * @param string $phoneNumber International format, e.g. +255700000000
     * @throws Exception
     */
    public function sendMessage(string $phoneNumber, string $message): array
    {
        if (empty($this->token)) {
            throw new Exception('WaSender API token not configured');
        }

        $response = Http::withToken($this->token)
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/send-message', [
                'to' => $phoneNumber,
                'text' => $message,
            ]);

        Log::channel('single')->info('WaSender API Response', [
            'status' => $response->status(),
            'phone' => $phoneNumber,
        ]);

        if (!$response->successful()) {
            throw new Exception('WaSender API request failed with status ' . $response->status());
        }

        return $response->json() ?? [];
    }
}
