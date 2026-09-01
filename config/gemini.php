<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gemini (primary provider for CV parsing)
    |--------------------------------------------------------------------------
    |
    | Google's Gemini Flash / Flash-Lite models are dramatically cheaper than
    | OpenAI's for this kind of short, structured-extraction call — used as
    | the first attempt for CvParserService specifically (see AiFeature::
    | CV_PARSE), not wired into OpenAiClient's general fallback chain used by
    | every other AI feature in the app. Talks Gemini's own OpenAI-compatible
    | endpoint, so the request/response shape matches OpenAiClient/
    | OllamaClient closely enough to share the same usage-log shape. Leave
    | GEMINI_API_KEY empty to disable it — CvParserService then falls
    | straight through to the existing OpenAI (+ Ollama) chain, exactly as
    | it did before this existed.
    */
    'api_key' => env('GEMINI_API_KEY'),

    // "Flash-Lite" is the cheapest/fastest tier — right fit for a short,
    // structured-JSON extraction task like this. Override via env if a
    // newer model generation is preferred without a code change.
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),

    'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 800),
    'temperature' => (float) env('GEMINI_TEMPERATURE', 0.3),

    // This path runs synchronously inside a normal web request (a
    // candidate's CV upload) — see config/ollama.php's request_timeout
    // comment for why a short, bounded timeout matters here (Cloudflare's
    // own proxy timeout is much shorter than a "generous" AI budget).
    'request_timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Cost tracking
    |--------------------------------------------------------------------------
    |
    | USD per 1,000,000 tokens, by model, used by GeminiClient to estimate
    | the cost of every call for ai_usage_logs. 'default' covers any model
    | not listed here. Update when pricing changes or a new model is
    | introduced — figures below are Flash-Lite/Flash's public per-token
    | pricing, an order of magnitude below gpt-4o-mini.
    */
    'pricing_per_million_tokens' => [
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'default' => ['input' => 0.10, 'output' => 0.40],
    ],
];
