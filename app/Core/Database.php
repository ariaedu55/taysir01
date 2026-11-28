<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    /** Connexion PDO unique, ouverte au premier appel. */
    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = config('database');
            // Recette isolée (config/recette.php) : base dédiée et, en option, compte SQL dédié
            $recette = require BASE_PATH . '/config/recette.php';
            if ($recette !== null) {
                $c['name'] = $recette['base'];
                $c['port'] = $recette['port'] ?? $c['port'];
                if ($recette['user'] !== null) {
                    [$c['user'], $c['password']] = [$recette['user'], $recette['password']];
                }
            }
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
            self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$pdo;
    }
}
