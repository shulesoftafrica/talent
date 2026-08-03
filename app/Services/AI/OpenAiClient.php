<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper around OpenAI's Chat Completions API, shared across every
 * AI-driven feature in this app (CV parsing, career coach content). Uses
 * the same OPENAI_API_KEY as shulesoft_newversion.
 *
 * Falls back to the self-hosted Ollama server (OllamaClient, see
 * config/ollama.php) automatically whenever OpenAI fails for any reason —
 * no key configured, quota exceeded, network error, or an HTTP error
 * response. The caller never needs to know which provider actually
 * answered; only the logged ai_usage_logs row's `provider` column
 * distinguishes them.
 *
 * Every OpenAI attempt is logged to ai_usage_logs, including a skipped
 * one (status 'not_configured', $0 cost) — that's useful signal on its
 * own ("how often are we relying on the free fallback because OpenAI
 * isn't set up"). The Ollama attempt is logged the same way, except when
 * Ollama is *also* unconfigured (OLLAMA_URL empty), since then no request
 * was attempted there at all. See AiFeature for the feature tags and
 * config/openai.php for the per-model pricing used to estimate OpenAI
 * cost (Ollama calls are always $0, being self-hosted).
 */
class OpenAiClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private readonly OllamaClient $ollama)
    {
    }

    /**
     * @param string $system System prompt.
     * @param string $user User prompt.
     * @param bool $json Whether to force a JSON object response.
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
        $openAiResult = $this->attemptOpenAi($system, $user, $json, $maxTokens);

        $this->logUsage(
            'openai',
            $openAiResult['model'],
            $openAiResult['status'],
            $openAiResult['usage'],
            $openAiResult['error'],
            $candidateId,
            $feature,
            $meta,
        );

        if ($openAiResult['success']) {
            return ['success' => true, 'content' => $openAiResult['content'], 'error' => null];
        }

        // OpenAI didn't come through — try the self-hosted fallback before
        // giving up. The caller's own graceful degradation (rule-based
        // content) is the last resort after this.
        $ollamaResult = $this->ollama->chat($system, $user, $json, $maxTokens);

        if ($ollamaResult['error'] !== 'not_configured') {
            $ollamaStatus = match (true) {
                $ollamaResult['success'] => 'success',
                str_starts_with((string) $ollamaResult['error'], 'http_') => 'http_error',
                default => 'failed',
            };

            $this->logUsage(
                'ollama',
                config('ollama.model', 'llama3:latest'),
                $ollamaStatus,
                $ollamaResult['usage'],
                $ollamaResult['success'] ? null : $ollamaResult['error'],
                $candidateId,
                $feature,
                $meta,
            );
        }

        if ($ollamaResult['success']) {
            return ['success' => true, 'content' => $ollamaResult['content'], 'error' => null];
        }

        return ['success' => false, 'content' => null, 'error' => $openAiResult['error']];
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
    private function attemptOpenAi(string $system, string $user, bool $json, ?int $maxTokens): array
    {
        $apiKey = config('openai.api_key');
        $model = config('openai.default_model', 'gpt-4o-mini');
        $emptyUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        if (empty($apiKey)) {
            Log::warning('OpenAiClient: no API key configured; falling back to Ollama');
            return ['success' => false, 'content' => null, 'error' => 'not_configured', 'model' => $model, 'status' => 'not_configured', 'usage' => $emptyUsage];
        }

        $payload = [
            'model' => $model,
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
            return ['success' => false, 'content' => null, 'error' => $e->getMessage(), 'model' => $model, 'status' => 'failed', 'usage' => $emptyUsage];
        }

        $usage = $response->json('usage') ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $totalTokens = (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens));
        $tokenUsage = ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens, 'total_tokens' => $totalTokens];

        if (!$response->successful()) {
            Log::error('OpenAiClient: request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'content' => null, 'error' => 'http_' . $response->status(), 'model' => $model, 'status' => 'http_error', 'usage' => $tokenUsage];
        }

        if ($response->json('choices.0.finish_reason') === 'length') {
            Log::warning('OpenAiClient: response truncated by max_tokens', ['max_tokens' => $payload['max_tokens']]);
        }

        $content = $response->json('choices.0.message.content');

        return ['success' => true, 'content' => $content, 'error' => null, 'model' => $model, 'status' => 'success', 'usage' => $tokenUsage];
    }

    private function estimateCost(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        if ($provider !== 'openai') {
            return 0.0;
        }

        $pricing = config('openai.pricing_per_million_tokens', []);
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
        string $provider,
        string $model,
        string $status,
        array $usage,
        ?string $error,
        ?int $candidateId,
        string $feature,
        array $meta,
    ): void {
        try {
            AiUsageLog::create([
                'candidate_id' => $candidateId,
                'provider' => $provider,
                'feature' => $feature,
                'model' => $model,
                'prompt_tokens' => $usage['prompt_tokens'],
                'completion_tokens' => $usage['completion_tokens'],
                'total_tokens' => $usage['total_tokens'],
                'estimated_cost_usd' => $this->estimateCost($provider, $model, $usage['prompt_tokens'], $usage['completion_tokens']),
                'status' => $status,
                'error' => $error,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Cost logging must never break the actual AI-powered feature.
            Log::error('OpenAiClient: failed to write ai_usage_logs row', ['error' => $e->getMessage()]);
        }
    }
}
