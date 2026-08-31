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
    // the actual answer — confirmed live that even a trivial 10-token
    // request takes ~5s of pure inference, so a realistic ~800-token
    // prompt runs well past a minute on this (CPU-only) host.
    'model' => env('OLLAMA_MODEL', 'qwen3:latest'),

    // This path is reached synchronously, inside a normal web request
    // (e.g. a candidate's job-match page load) — it is NOT a background
    // job. Cloudflare's own proxy timeout (~100s) is shorter than the
    // "generous" 120-280s this used to allow, so a slow Ollama response
    // could never actually reach the browser either way — the request
    // just died at the CDN with the page hung the whole time. Kept short
    // enough that OpenAI-fails + Ollama-fails always finishes well inside
    // Cloudflare's window, falling through fast to the caller's own
    // rule-based degradation instead of hanging the page for minutes.
    'request_timeout' => (int) env('OLLAMA_REQUEST_TIMEOUT', 10),
];
