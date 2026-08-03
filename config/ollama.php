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
    // Points at the local Ollama instance directly (no external hostname —
    // external access to it was disabled for security, so this only works
    // when the app and Ollama run on the same host).
    'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),

    // qwen3 is the only model installed on the live server. It's a
    // "thinking" model that spends real time on chain-of-thought before
    // the actual answer, so this is meaningfully slower than a plain
    // completion model — acceptable since this path only ever runs
    // because OpenAI has already failed.
    'model' => env('OLLAMA_MODEL', 'qwen3:latest'),

    // Self-hosted inference is much slower than OpenAI, and this path only
    // runs when OpenAI has already failed, so it's given a generous budget.
    'request_timeout' => (int) env('OLLAMA_REQUEST_TIMEOUT', 120),
];
