<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around OpenAI's Chat Completions API, shared across every
 * AI-driven feature in this app (CV parsing, job match reasoning, career
 * coach content, verification summaries). Uses the same OPENAI_API_KEY as
 * shulesoft_newversion.
 */
class OpenAiClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * @param string $system System prompt.
     * @param string $user User prompt.
     * @param bool $json Whether to force a JSON object response.
     * @return array{success:bool,content:?string,error:?string}
     */
    public function chat(string $system, string $user, bool $json = false, ?int $maxTokens = null): array
    {
        $apiKey = config('openai.api_key');

        if (empty($apiKey)) {
            Log::warning('OpenAiClient: no API key configured; skipping call');
            return ['success' => false, 'content' => null, 'error' => 'not_configured'];
        }

        $payload = [
            'model' => config('openai.default_model', 'gpt-4o-mini'),
            'max_tokens' => $maxTokens ?? config('openai.max_tokens', 800),
            'temperature' => config('openai.temperature', 0.7),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(config('openai.request_timeout', 30))
                ->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            Log::error('OpenAiClient: network error', ['error' => $e->getMessage()]);
            return ['success' => false, 'content' => null, 'error' => $e->getMessage()];
        }

        if (!$response->successful()) {
            Log::error('OpenAiClient: request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false, 'content' => null, 'error' => 'http_' . $response->status()];
        }

        if ($response->json('choices.0.finish_reason') === 'length') {
            Log::warning('OpenAiClient: response truncated by max_tokens', ['max_tokens' => $payload['max_tokens']]);
        }

        $content = $response->json('choices.0.message.content');

        return ['success' => true, 'content' => $content, 'error' => null];
    }

    /**
     * Convenience wrapper for JSON-mode calls that decodes the response.
     *
     * @return array|null Decoded JSON object, or null on any failure.
     */
    public function chatJson(string $system, string $user, ?int $maxTokens = null): ?array
    {
        $result = $this->chat($system, $user, json: true, maxTokens: $maxTokens);

        if (!$result['success'] || !$result['content']) {
            return null;
        }

        $decoded = json_decode($result['content'], true);

        return is_array($decoded) ? $decoded : null;
    }
}
