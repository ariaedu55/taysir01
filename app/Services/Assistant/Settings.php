<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use RuntimeException;

final class Settings
{
    private const FILE_LIMIT = 2048;

    /** null signifie que les réglages d’environnement s’appliquent encore. */
    public static function read(): ?array
    {
        $path = self::path();
        if (is_link(dirname($path)) || (is_dir(dirname($path)) && !is_readable(dirname($path)))) {
            return ['api_key' => null];
        }
        if (!is_link($path) && !file_exists($path)) {
            return null;
        }

        // Un fichier présent mais inutilisable désactive l’assistant, y compris avec une ancienne clé d’environnement.
        $raw = is_file($path) && !is_link($path) ? @file_get_contents($path, false, null, 0, self::FILE_LIMIT) : false;
        $stored = is_string($raw) && strlen($raw) < self::FILE_LIMIT ? json_decode($raw, true) : null;
        $key = is_array($stored) ? ($stored['api_key'] ?? null) : null;

        return ['api_key' => is_string($key) && self::validKey($key) ? $key : null];
    }

    public static function validKey(string $key): bool
    {
        return preg_match('/\A[\x21-\x7e]{8,512}\z/', $key) === 1;
    }

    public static function save(?string $key): void
    {
        if ($key !== null && !self::validKey($key)) {
            throw new RuntimeException('Format de clé invalide.');
        }

        $path = self::path();
        $directory = dirname($path);
        if (is_link($directory) || is_link($path)
            || (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory))
            || !@chmod($directory, 0700)) {
            throw new RuntimeException('Le stockage privé de l’assistant est indisponible.');
        }

        $payload = json_encode(['api_key' => $key], JSON_THROW_ON_ERROR);
        $temporary = $path . '.' . bin2hex(random_bytes(8));
        $file = @fopen($temporary, 'xb');
        if ($file === false) {
            throw new RuntimeException('Impossible de préparer le réglage de l’assistant.');
        }

        try {
            if (!@chmod($temporary, 0600) || fwrite($file, $payload) !== strlen($payload) || !fflush($file)) {
                throw new RuntimeException('Impossible d’écrire le réglage de l’assistant.');
            }
            fclose($file);
            if (!@rename($temporary, $path)) {
                throw new RuntimeException('Impossible d’enregistrer le réglage de l’assistant.');
            }
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private static function path(): string
    {
        $recette = require BASE_PATH . '/config/recette.php';

        return ($recette['storage'] ?? BASE_PATH . '/storage') . '/secrets/deepseek.json';
    }
}
