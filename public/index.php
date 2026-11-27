<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

// Serveur de recette : il s'identifie, pour que le robot vérifie qu'il parle bien à SON serveur (config/recette.php)
$recette = require BASE_PATH . '/config/recette.php';
if ($recette !== null) {
    header('X-Taysir-Recette: ' . $recette['base']);
}

$router = new App\Core\Router();
require BASE_PATH . '/routes/web.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
