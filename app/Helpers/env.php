<?php

declare(strict_types=1);

/*
 * Lecture de .env (racine du projet, jamais versionné ; modèle : .env.example).
 * Format : une ligne CLE=valeur, les lignes vides et celles qui commencent par # sont ignorées,
 * les guillemets autour de la valeur sont retirés. Les variables déjà définies par le serveur ne sont pas écrasées.
 */
function load_env(string $file): void
{
    if (!is_file($file) || !is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name) || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

/** Valeur d'une variable d'environnement (.env ou serveur), ou $default si elle est absente ou vide. */
function env(string $name, mixed $default = null): mixed
{
    $value = $_ENV[$name] ?? getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true', 'on', 'yes' => true,
        'false', 'off', 'no' => false,
        default => $value,
    };
}
