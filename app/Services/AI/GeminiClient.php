<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper around Google's Gemini API, via its OpenAI-compatible endpoint —
 * same request/response shape as OpenAiClient (messages array, response_format,
 * choices[0].message.content, usage.{prompt,completion,total}_tokens), just a
 * different base URL and API key. That compatibility is deliberate: it lets
 * this client stay a near-identical, self-contained twin of OpenAiClient
 * rather than a bespoke integration.
 *
 * Not wired into OpenAiClient's own fallback chain — this is used directly
 * by CvParserService as the *first* attempt for CV parsing specifically
 * (Gemini Flash-Lite is dramatically cheaper than OpenAI for this kind of
 * short, structured-extraction call), falling back to the existing OpenAI
 * (+ Ollama) chain only if Gemini fails or isn't configured. Every other
 * AI feature in the app (career coach, job match, etc.) is untouched and
 * keeps going straight to OpenAiClient as before.
 */
class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';

    /**
     * @param string $feature One of App\Services\AI\AiFeature's constants, tagging this call for cost reporting.
     * @param array<string, mixed> $meta Small extra context stored alongside the usage log (e.g. job id).
     * @return array{success:bool,content:?string,error:?string}
     */
    public function chat(
        string $system,
        string $user,
        bool $json = false,
        ?int $maxTokens = null,
        ?int $candidateId = null,
        string $feature = 'unknown',
        array $meta = [],
    ): array {
        $result = $this->attemptGemini($system, $user, $json, $maxTokens);

        $this->logUsage($result['model'], $result['status'], $result['usage'], $result['error'], $candidateId, $feature, $meta);

        if ($result['success']) {
            return ['success' => true, 'content' => $result['content'], 'error' => null];
        }

        return ['success' => false, 'content' => null, 'error' => $result['error']];
    }

    /**
     * Convenience wrapper for JSON-mode calls that decodes the response.
     *
     * @return array|null Decoded JSON object, or null on any failure.
     */
    public function chatJson(
        string $system,
        string $user,
        ?int $maxTokens = null,
        ?int $candidateId = null,
        string $feature = 'unknown',
        array $meta = [],
    ): ?array {
        $result = $this->chat($system, $user, json: true, maxTokens: $maxTokens, candidateId: $candidateId, feature: $feature, meta: $meta);

        if (!$result['success'] || !$result['content']) {
            return null;
        }

        $decoded = json_decode($result['content'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{success:bool,content:?string,error:?string,model:string,status:string,usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}}
     */
    private function attemptGemini(string $system, string $user, bool $json, ?int $maxTokens): array
    {
        $apiKey = config('gemini.api_key');
        $model = config('gemini.model', 'gemini-2.5-flash-lite');
        $emptyUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        if (empty($apiKey)) {
            return ['success' => false, 'content' => null, 'error' => 'not_configured', 'model' => $model, 'status' => 'not_configured', 'usage' => $emptyUsage];
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens ?? config('gemini.max_tokens', 800),
            'temperature' => config('gemini.temperature', 0.3),
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
                ->timeout(config('gemini.request_timeout', 15))
                ->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            Log::error('GeminiClient: network error', ['error' => $e->getMessage()]);
            return ['success' => false, 'content' => null, 'error' => $e->getMessage(), 'model' => $model, 'status' => 'failed', 'usage' => $emptyUsage];
        }

        $usage = $response->json('usage') ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $totalTokens = (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens));
        $tokenUsage = ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens, 'total_tokens' => $totalTokens];

        if (!$response->successful()) {
            Log::error('GeminiClient: request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'content' => null, 'error' => 'http_' . $response->status(), 'model' => $model, 'status' => 'http_error', 'usage' => $tokenUsage];
        }

        $content = $response->json('choices.0.message.content');

        return ['success' => true, 'content' => $content, 'error' => null, 'model' => $model, 'status' => 'success', 'usage' => $tokenUsage];
    }

    private function estimateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = config('gemini.pricing_per_million_tokens', []);
        $rates = $pricing[$model] ?? $pricing['default'] ?? ['input' => 0, 'output' => 0];

        $cost = ($promptTokens / 1_000_000) * $rates['input']
            + ($completionTokens / 1_000_000) * $rates['output'];

        return round($cost, 6);
    }

    /**
     * @param array{prompt_tokens:int,completion_tokens:int,total_tokens:int} $usage
     * @param array<string, mixed> $meta
     */
    private function logUsage(
        string $model,
        string $status,
        array $usage,
        ?string $error,
        ?int $candidateId,
        string $feature,
        array $meta,
    ): void {
        // Unlike OllamaClient (a silent last-resort fallback), Gemini is
        // the primary provider here — a skipped, not-configured attempt is
        // still logged (same convention as OpenAiClient), since "is Gemini
        // even set up yet" is useful operational signal on its own.
        try {
            AiUsageLog::create([
                'candidate_id' => $candidateId,
                'provider' => 'gemini',
                'feature' => $feature,
                'model' => $model,
                'prompt_tokens' => $usage['prompt_tokens'],
                'completion_tokens' => $usage['completion_tokens'],
                'total_tokens' => $usage['total_tokens'],
                'estimated_cost_usd' => $this->estimateCost($model, $usage['prompt_tokens'], $usage['completion_tokens']),
                'status' => $status,
                'error' => $error,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Cost logging must never break the actual AI-powered feature.
            Log::error('GeminiClient: failed to write ai_usage_logs row', ['error' => $e->getMessage()]);
        }
    }
}
