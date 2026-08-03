<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Self-hosted Ollama fallback used automatically by OpenAiClient. Talks the
 * OpenAI-compatible /v1/chat/completions endpoint Ollama exposes, so the
 * request/response shape — including a real usage object — matches
 * OpenAiClient closely enough that both providers can share one cost-log
 * write path in OpenAiClient::logUsage().
 */
class OllamaClient
{
    /**
     * @return array{success:bool,content:?string,error:?string,usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}}
     */
    public function chat(string $system, string $user, bool $json = false, ?int $maxTokens = null): array
    {
        $baseUrl = rtrim((string) config('ollama.url'), '/');

        if (!$baseUrl) {
            return ['success' => false, 'content' => null, 'error' => 'not_configured', 'usage' => $this->emptyUsage()];
        }

        $payload = [
            'model' => config('ollama.model', 'llama3:latest'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'stream' => false,
        ];

        if ($maxTokens) {
            $payload['max_tokens'] = $maxTokens;
        }

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::timeout(config('ollama.request_timeout', 120))
                ->post("{$baseUrl}/v1/chat/completions", $payload);
        } catch (\Throwable $e) {
            Log::error('OllamaClient: network error', ['error' => $e->getMessage()]);
            return ['success' => false, 'content' => null, 'error' => $e->getMessage(), 'usage' => $this->emptyUsage()];
        }

        $usage = $this->extractUsage($response->json('usage'));

        if (!$response->successful()) {
            Log::error('OllamaClient: request failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'content' => null, 'error' => 'http_' . $response->status(), 'usage' => $usage];
        }

        $content = $response->json('choices.0.message.content');

        return ['success' => true, 'content' => $content, 'error' => null, 'usage' => $usage];
    }

    /**
     * @param  array<string, mixed>|null  $usage
     * @return array{prompt_tokens:int,completion_tokens:int,total_tokens:int}
     */
    private function extractUsage(?array $usage): array
    {
        $usage ??= [];
        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => (int) ($usage['total_tokens'] ?? $prompt + $completion),
        ];
    }

    /**
     * @return array{prompt_tokens:int,completion_tokens:int,total_tokens:int}
     */
    private function emptyUsage(): array
    {
        return ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
    }
}
