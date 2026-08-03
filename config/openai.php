<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 800),
    'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),
    'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Cost tracking
    |--------------------------------------------------------------------------
    |
    | USD per 1,000,000 tokens, by model, used by OpenAiClient to estimate
    | the cost of every call for ai_usage_logs. 'default' covers any model
    | not listed here (kept intentionally conservative — update this table
    | when pricing changes or a new model is introduced).
    */
    'pricing_per_million_tokens' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'default' => ['input' => 0.15, 'output' => 0.60],
    ],
];
