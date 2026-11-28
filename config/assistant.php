<?php

declare(strict_types=1);

// Assistant IA (docs/assistant-ia.md). Clé et modèle dans .env, jamais dans ce fichier.
return [
    'enabled' => (bool) env('ASSISTANT_ENABLED', false) && (env('DEEPSEEK_API_KEY') !== null || (bool) env('ASSISTANT_MOCK', false)),
    'mock' => (bool) env('ASSISTANT_MOCK', false),
    'api_key' => env('DEEPSEEK_API_KEY'),
    'model' => env('DEEPSEEK_MODEL', 'deepseek-flash'),
    'endpoint' => 'https://api.deepseek.com/chat/completions',
    'timeout' => 240,           // secondes ; la recherche approfondie peut enchaîner une vingtaine de recherches
    'max_tokens' => 6000,        // budget pour l’analyse et la réponse
    'question_max' => 1500,      // caractères par question
    'history_turns' => 8,        // derniers échanges renvoyés à l'IA
    'context_passages' => 12,    // passages de la plateforme joints à chaque question
    'limits' => [
        'per_ip_10min' => 15,
        'per_ip_day' => 80,
        'site_day' => 3000,
    ],
];
