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
    // Instructions séparées par « ; » en fin de ligne (pas de procédures stockées dans ces fichiers).
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    foreach (preg_split('/;\s*$/m', $sql) as $statement) {
        if (trim($statement) !== '') {
            $db->exec($statement);
        }
    }
    $db->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$migration]);
    echo "Appliquée : {$migration}\n";
    $count++;
}

echo $count === 0 ? "Base à jour ({$name}).\n" : "{$count} migration(s) appliquée(s) sur {$name}.\n";
