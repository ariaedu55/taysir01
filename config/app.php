<?php

declare(strict_types=1);

return [
    'name' => 'Taysir Orientation Voyage',
    // Adresse publique du site, sans « / » final : liens absolus des e-mails et des tâches planifiées.
    // En production : 'https://taysir-orientation.com' (dans config/app.local.php).
    'url' => 'http://127.0.0.1:8080',
    // Activer uniquement en développement dans config/app.local.php.
    'debug' => false,
    'timezone' => 'Africa/Dakar',
    'locale' => 'fr',
];
