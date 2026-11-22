<?php

declare(strict_types=1);

// Valeurs par défaut de WAMP en local. Ne pas mettre les identifiants de production dans ce fichier :
// créer plutôt config/database.local.php, qui est chargé à la place s'il existe.
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'taysir_orientation',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];
