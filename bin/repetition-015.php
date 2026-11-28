<?php

/*
 * Répétition de la migration 015 (tâches ; 2D) sur une base FICTIVE (docs/gestion-phase-2.md, C7) : aller, clés
 * RESTRICT, unicité du jeton, garde-fou, retour à l'identique, interruption, reprise et relance. Supprime la base.
 *
 * Usage : php -n -d extension_dir=… -d extension=pdo_mysql -d extension=mbstring bin/repetition-015.php
 *         [--port=33317] [--sql-user=… --sql-password=…] [--exiger=10.11]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

$racine = dirname(__DIR__);
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $m)) {
        $options[$m[1]] = $m[2] ?? true;
    }
}
$config = is_file("{$racine}/config/database.local.php") ? require "{$racine}/config/database.local.php" : require "{$racine}/config/database.php";
$port = is_string($options['port'] ?? null) && ctype_digit($options['port']) ? (int) $options['port'] : (int) $config['port'];
$utilisateur = is_string($options['sql-user'] ?? null) ? $options['sql-user'] : $config['user'];
$motDePasse = is_string($options['sql-password'] ?? null) ? $options['sql-password'] : $config['password'];
$base = 'taysir_015_' . bin2hex(random_bytes(5)) . '_recette';
$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $port);
$pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$serveur = new PDO($dsn, $utilisateur, $motDePasse, $pdoOptions);
$version = (string) $serveur->query('SELECT VERSION()')->fetchColumn();
if (is_string($options['exiger'] ?? null) && !str_starts_with($version, $options['exiger'])) {
    fwrite(STDERR, "Serveur {$version} : la version exigée est {$options['exiger']}. Rien n'est touché.\n");
    exit(1);
}
echo "Serveur : {$version} sur {$config['host']}:{$port}\n";
$serveur->exec("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$resultats = [];
$verifier = static function (bool $ok, string $etape) use (&$resultats): void {
    $resultats[] = $ok;
    echo ($ok ? '  OK    ' : '  ÉCHEC ') . $etape . "\n";
};
$instructions = static function (string $fichier): array {
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($fichier));
    $liste = array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)), static fn (string $s): bool => $s !== ''));
    if ($liste === []) {
        throw new RuntimeException("Fichier SQL absent ou vide : {$fichier}");
    }

    return $liste;
};
$instantane = static function (PDO $db): array {
    $resultat = [];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if ($table === 'migrations') {
            $resultat[$table] = $db->query('SELECT name FROM migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
            continue;
        }
        $ddl = preg_replace('/ AUTO_INCREMENT=\d+/', '', $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1]);
        $resultat[$table] = [$ddl, $db->query("SELECT * FROM `{$table}`")->fetchAll()];
    }

    return $resultat;
};
$comparer = static function (array $actuel, array $attendu, string $etape) use ($verifier): void {
    $ecarts = [];
    foreach (array_unique([...array_keys($actuel), ...array_keys($attendu)]) as $table) {
        if (($actuel[$table] ?? null) !== ($attendu[$table] ?? null)) {
            $ecarts[] = $table;
        }
    }
    $verifier($ecarts === [], $etape . ($ecarts === [] ? '' : ' (écart : ' . implode(', ', $ecarts) . ')'));
};
$php = require __DIR__ . '/php-enfant.php';
$env = array_merge(getenv(), ['TAYSIR_DB' => $base, 'TAYSIR_DB_PORT' => (string) $port]);
if (isset($options['sql-user']) || isset($options['sql-password'])) {
    $env += ['TAYSIR_DB_USER' => $utilisateur, 'TAYSIR_DB_PASSWORD' => $motDePasse];
}
$migrer = static function (array $arguments) use ($php, $racine, $env): string {
    $processus = proc_open([...$php, $racine . '/database/migrate.php', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes, $racine, $env);
    $sortie = stream_get_contents($tubes[1]) . stream_get_contents($tubes[2]);
    $code = proc_close($processus);
    if ($code !== 0) {
        throw new RuntimeException('Résultat inattendu du migrateur (' . implode(' ', $arguments) . ") :\n" . $sortie);
    }
    return $sortie;
};

$code = 1;
try {
    $migrer(['--jusqua=014']);
    $db = new PDO($dsn . ';dbname=' . $base, $utilisateur, $motDePasse, $pdoOptions);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $base) {
        throw new RuntimeException('Mauvaise base de recette.');
    }
    $db->exec("INSERT INTO users (id, first_name, last_name, email, role, password_hash, consent_at) VALUES (701, 'Recette', 'Agent', 'agent015@recette.test', 'conseiller', 'x', NOW())");
    $etat014 = $instantane($db);

    echo "\nMigration 015 :\n";
    $migrer(['--jusqua=015']);
    $restrict = (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME = 'tasks' AND DELETE_RULE = 'RESTRICT'")->fetchColumn();
    $verifier($restrict === 5, '015 : table tasks, cinq clés étrangères en RESTRICT (' . $restrict . ')');
    $nullable = $db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'assigned_to'")->fetchColumn();
    $verifier($nullable === 'NO', '015 : responsable obligatoire');
    $etat015 = $instantane($db);

    echo "\nUnicité du jeton de formulaire :\n";
    $insererTache = $db->prepare('INSERT INTO tasks (title, assigned_to, created_by, form_token) VALUES (?, 701, 701, ?)');
    $jeton = str_repeat('a', 64);
    $insererTache->execute(['Recette', $jeton]);
    $avantDoublon = $instantane($db);
    $doublonRefuse = false;
    try {
        $insererTache->execute(['Seconde soumission', $jeton]);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1062
            || !str_contains($exception->errorInfo[2] ?? '', 'tasks_form_token_unique')) {
            throw $exception;
        }
        $doublonRefuse = true;
    }
    $verifier($doublonRefuse && $instantane($db) === $avantDoublon, 'Même jeton : erreur 1062 sur l’index unique, une seule tâche intacte');

    echo "\nGarde-fou et retour arrière :\n";
    $retour = static function () use ($db, $instructions, $racine): ?int {
        try {
            foreach ($instructions("{$racine}/database/rollback/015_taches.down.sql") as $sql) {
                $db->query($sql)->closeCursor();
            }
        } catch (PDOException $exception) {
            return (int) ($exception->errorInfo[1] ?? 0);
        }

        return null;
    };
    $avant = $instantane($db);
    $arret = $retour();
    $verifier($arret === 1242 && $instantane($db) === $avant, 'Retour arrière refusé tant qu’une tâche existe, rien de modifié');
    $db->exec('DELETE FROM tasks');
    $verifier($retour() === null, 'Retour arrière de 015 sans erreur');
    $comparer($instantane($db), $etat014, 'Retour arrière de 015 : schéma, données et journal identiques');

    echo "\nRéapplication :\n";
    $migrer(['--jusqua=015']);
    $comparer($instantane($db), $etat015, 'Réapplication de 015 : identique au premier aller');
    if ($retour() !== null) {
        throw new RuntimeException('Impossible de revenir à 014 avant la simulation d’interruption.');
    }

    echo "\nInterruption et reprise :\n";
    $etapes015 = $instructions("{$racine}/database/migrations/015_taches.sql");
    // La 015 crée sa table en une instruction DDL. Simuler l’arrêt après le DDL, avant l’écriture dans migrations.
    foreach ($etapes015 as $sql) {
        $db->exec($sql);
    }
    $etatInterrompu = $etat015;
    $etatInterrompu['migrations'] = $etat014['migrations'];
    $comparer($instantane($db), $etatInterrompu, 'Après l’interruption, la table est complète et 015 reste absente du journal');

    $insererTache->execute(['Tâche présente lors de la reprise', $jeton]);
    $attenduReprise = $instantane($db);
    $attenduReprise['migrations'] = $etat015['migrations'];
    $instructionSuivante = count($etapes015) + 1;
    $sortieReprise = $migrer(['--jusqua=015', "--reprendre=015_taches.sql:{$instructionSuivante}"]);
    $verifier(str_contains($sortieReprise, 'Déjà appliquée, sautée : 015_taches.sql, instruction ' . count($etapes015)),
        'Le migrateur saute le dernier DDL déjà exécuté');
    $comparer($instantane($db), $attenduReprise, 'La reprise conserve la tâche et enregistre 015 une seule fois');

    $db->exec('DELETE FROM tasks');
    $comparer($instantane($db), $etat015, 'Après reprise, schéma, données de référence et journal identiques au premier aller');
    $migrer(['--jusqua=015']);
    $comparer($instantane($db), $etat015, 'Une relance complète laisse tout intact');

    $code = in_array(false, $resultats, true) ? 1 : 0;
} catch (Throwable $exception) {
    fwrite(STDERR, "\nArrêt : " . $exception->getMessage() . "\n");
} finally {
    $serveur->exec("DROP DATABASE IF EXISTS `{$base}`");
}

echo "\n" . count($resultats) . ' contrôles, ' . count(array_filter($resultats)) . ' réussis, ' . count(array_filter($resultats, static fn (bool $ok): bool => !$ok)) . " en échec.\nBase {$base} supprimée.\n";
exit($code);
