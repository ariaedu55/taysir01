<?php

/*
 * Applique les migrations SQL de database/migrations/ dans l'ordre de leur nom.
 * Ouvre la base existante ou la crée si le compte peut le faire. Chaque fichier n'est appliqué qu'une fois
 * (table `migrations`).
 *
 * Usage, depuis la racine du projet :
 *   php -d extension=pdo_mysql database/migrate.php
 * Sous WAMP en ligne de commande, ajouter : -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

$base = dirname(__DIR__);
$config = is_file("{$base}/config/database.local.php")
    ? require "{$base}/config/database.local.php"
    : require "{$base}/config/database.php";

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$name = $config['name'];
// Recette isolée (config/recette.php) : base dédiée et, en option, compte SQL dédié
$recette = require "{$base}/config/recette.php";
if ($recette !== null) {
    $name = $recette['base'];
    $config['port'] = $recette['port'] ?? $config['port'];
    if ($recette['user'] !== null) {
        [$config['user'], $config['password']] = [$recette['user'], $recette['password']];
    }
}
// --jusqua=006 : s'arrêter après cette migration (répétition des migrations sur une copie)
// --reprendre=010_files_affectation.sql:2 : reprendre une migration interrompue à partir de l'instruction 2
// (les précédentes ont déjà été appliquées : MySQL valide chaque changement de schéma au fil de l'eau)
$jusqua = null;
$reprendre = [];
foreach ($argv as $argument) {
    if (preg_match('/^--jusqua=(\d{3})$/', $argument, $m)) {
        $jusqua = $m[1];
    }
    if (preg_match('/^--reprendre=([0-9a-z_]+\.sql):(\d+)$/', $argument, $m)) {
        $reprendre[$m[1]] = (int) $m[2];
    }
}
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
    fwrite(STDERR, "Le nom de la base doit contenir des lettres, des chiffres ou des tirets bas.\n");
    exit(1);
}

$serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset']);
$databaseDsn = $serverDsn . ';dbname=' . $name;
try {
    $db = new PDO($databaseDsn, $config['user'], $config['password'], $options);
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) !== 1049) {
        throw $exception;
    }

    $server = new PDO($serverDsn, $config['user'], $config['password'], $options);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO($databaseDsn, $config['user'], $config['password'], $options);
}

$db->exec('CREATE TABLE IF NOT EXISTS migrations (
    name       VARCHAR(190) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$applied = $db->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $migration = basename($file);
    if (in_array($migration, $applied, true)) {
        continue;
    }
    if ($jusqua !== null && strcmp(substr($migration, 0, 3), $jusqua) > 0) {
        break;
    }
    // Instructions séparées par « ; » en fin de ligne (pas de procédures stockées dans ces fichiers).
    // MySQL valide chaque changement de schéma au fil de l'eau : en cas d'échec, on dit précisément où l'on s'est arrêté
    // (reprise : docs/gestion-phase-1.md, « Migrations interrompues »).
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)), static fn (string $s): bool => $s !== ''));
    foreach ($statements as $i => $statement) {
        if ($i + 1 < ($reprendre[$migration] ?? 1)) {
            echo "Déjà appliquée, sautée : {$migration}, instruction " . ($i + 1) . "
";
            continue;
        }
        try {
            $db->exec($statement);
        } catch (PDOException $exception) {
            fwrite(STDERR, sprintf("ÉCHEC : %s, instruction %d sur %d.
%s
Les instructions 1 à %d sont appliquées ; la migration N'EST PAS enregistrée.
Reprise : vérifier l'état (SHOW CREATE TABLE), puis relancer avec --reprendre=%s:%d
ou annuler les instructions déjà passées (database/rollback/). Détail : docs/gestion-phase-1.md, « Migrations interrompues ».
",
                $migration, $i + 1, count($statements), $exception->getMessage(), $i, $migration, $i + 1));
            exit(1);
        }
    }
    $db->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$migration]);
    echo "Appliquée : {$migration}\n";
    $count++;
}

echo $count === 0 ? "Base à jour ({$name}).\n" : "{$count} migration(s) appliquée(s) sur {$name}.\n";
