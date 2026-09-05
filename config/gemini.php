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
    // structured-JSON extraction task like this. gemini-2.5-flash-lite is
    // no longer available to new API keys — confirmed live via Google's
    // own 404 response, which named gemini-3.5-flash-lite as the
    // replacement. Override via env if a newer model generation is
    // preferred without a code change.
    'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),

    'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 800),
    'temperature' => (float) env('GEMINI_TEMPERATURE', 0.3),

    // This path runs synchronously inside a normal web request (a
    // candidate's CV upload) — see config/ollama.php's request_timeout
    // comment for why a bounded timeout matters here (Cloudflare's own
    // proxy timeout is much shorter than a "generous" AI budget). Unlike
    // Ollama, Gemini is a real cloud API rather than a CPU-bound local
    // model that can never realistically finish in time — 15s turned out
    // too tight in practice (confirmed live: a real, successful CV parse
    // already took ~11.4s, leaving too little margin, and frequent 15002ms
    // timeouts showed up in production). 25s leaves real headroom while
    // the OpenAI+Ollama fallback chain after it still comfortably fits
    // under Cloudflare's ~100s ceiling.
    'request_timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 25),

    /*
    |--------------------------------------------------------------------------
    | Cost tracking
    |--------------------------------------------------------------------------
    |
    | USD per 1,000,000 tokens, by model, used by GeminiClient to estimate
    | the cost of every call for ai_usage_logs. 'default' covers any model
    | not listed here. The 3.5 generation's confirmed live to exist (its
    | predecessor 2.5-flash-lite does not, for new keys) but its exact
    | per-token pricing wasn't available to verify here — carried over
    | from 2.5-flash-lite's public pricing as a same-tier estimate; check
    | Google's current pricing page and correct this if it's materially
    | off, since it only affects the cost *estimate* shown in ai_usage_logs,
    | not actual billing (Google bills your account directly regardless).
    */
    'pricing_per_million_tokens' => [
        'gemini-3.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-3.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'default' => ['input' => 0.10, 'output' => 0.40],
    ],
];
