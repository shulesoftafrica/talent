<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Self-hosted Ollama fallback
    |--------------------------------------------------------------------------
    |
    | Used automatically by OpenAiClient whenever an OpenAI call fails for
    | any reason (no key configured, quota exceeded, network error, etc.).
    | Talks Ollama's OpenAI-compatible /v1/chat/completions endpoint, so no
    | API key is needed. Leave OLLAMA_URL empty to disable the fallback
    | entirely (the app then behaves exactly as it did before this existed).
    */
    'url' => env('OLLAMA_URL', 'https://ai.shulesoft.group'),

    // llama3 responds ~3x faster than qwen3:8b here because qwen3 is a
    // "thinking" model that spends most of its time on chain-of-thought
    // reasoning before the actual answer — not worth the wait on a fallback
    // path that only ever runs because something's already gone wrong.
    'model' => env('OLLAMA_MODEL', 'llama3:latest'),

    // Self-hosted inference is much slower than OpenAI, and this path only
    // runs when OpenAI has already failed, so it's given a generous budget.
    'request_timeout' => (int) env('OLLAMA_REQUEST_TIMEOUT', 120),
];
