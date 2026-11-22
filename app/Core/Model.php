<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

abstract class Model
{
    /** Nom de la table, à définir dans chaque modèle. */
    protected static string $table = '';

    protected static function db(): PDO
    {
        return Database::connection();
    }

    /** Exécute une requête préparée. Toujours passer les valeurs dans $params, jamais dans $sql. */
    protected static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public static function find(int $id): ?array
    {
        $row = static::query('SELECT * FROM `' . static::$table . '` WHERE id = ? LIMIT 1', [$id])->fetch();

        return $row === false ? null : $row;
    }

    public static function all(string $orderBy = 'id'): array
    {
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy);

        return static::query('SELECT * FROM `' . static::$table . '` ORDER BY `' . $column . '`')->fetchAll();
    }
}
