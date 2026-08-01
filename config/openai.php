<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 800),
    'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),
    'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 30),
];
