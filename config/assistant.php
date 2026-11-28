<?php

declare(strict_types=1);

use App\Services\Assistant\Settings;

// L’administration prend la main dès son premier enregistrement. L’environnement sert de réglage initial.
$stored = Settings::read();
$key = $stored === null ? env('DEEPSEEK_API_KEY') : $stored['api_key'];
$mock = (bool) env('ASSISTANT_MOCK', false);

return [
    'enabled' => $stored === null ? ((bool) env('ASSISTANT_ENABLED', false) && ($key !== null || $mock)) : $key !== null,
    'mock' => $mock,
    'api_key' => $key,
    'key_source' => $key === null ? null : ($stored === null ? 'serveur' : 'administration'),
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
