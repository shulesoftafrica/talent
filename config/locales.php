<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Keyed by the locale code stored in the session and used as the lang/
    | subdirectory name. "native" is shown in the language switcher so each
    | option reads correctly in its own language, not the current locale.
    |
    */

    'supported' => [
        'en' => ['label' => 'English', 'native' => 'English', 'flag' => '🇬🇧'],
        'sw' => ['label' => 'Swahili', 'native' => 'Kiswahili', 'flag' => '🇹🇿'],
        'fr' => ['label' => 'French', 'native' => 'Français', 'flag' => '🇫🇷'],
    ],

    'default' => 'en',
];
