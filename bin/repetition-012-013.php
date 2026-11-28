<?php

/*
 * Répétition des migrations 012 (agenda) et 013 (réglages d'agenda) sur une base FICTIVE
 * (docs/gestion-phase-2.md, C7 ; décision 2B, condition B5). Repris du script de qualification MariaDB de la 2B.
 *
 * 1. Crée une base au nom unique `taysir_012_013_<aléa>_recette` (jamais d'autre base touchée), migre jusqu'à 011
 *    et y place des rendez-vous dans les quatre anciens statuts.
 * 2. 012 : reprise des statuts (termine → honore), des agences et des heures historiques, updated_at conservé ;
 *    fuseau de Paris ; index unique du créneau actif ; neuf clés étrangères en RESTRICT.
 * 3. 013 : huit durées et RESTRICT sur les jetons d'annulation.
 * 4. Retours arrière 013 puis 012 : schéma, données et journal identiques à chaque étape.
 * 5. Interruptions simulées (012 après l'instruction 3, 013 après l'instruction 2), puis --reprendre : état identique
 *    à l'aller complet ; une relance laisse tout intact.
 * 6. Supprime la base.
 *
 * Usage : php -n -d extension_dir=… -d extension=pdo_mysql -d extension=mbstring bin/repetition-012-013.php
 *         [--port=33317] [--sql-user=… --sql-password=…] [--exiger=10.11]
 *   --port   : instance de recette séparée (par exemple MariaDB 10.11 isolée sur 127.0.0.1) ;
 *   --exiger : refuse de tourner si la version du serveur ne commence pas par ce préfixe (« 10.11 » pour MariaDB du VPS).
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
$base = 'taysir_012_013_' . bin2hex(random_bytes(5)) . '_recette';
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
$migrer = static function (array $arguments, bool $echecAttendu = false) use ($php, $racine, $env): void {
    $processus = proc_open([...$php, $racine . '/database/migrate.php', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes, $racine, $env);
    $sortie = stream_get_contents($tubes[1]) . stream_get_contents($tubes[2]);
    $code = proc_close($processus);
    if (($code !== 0) !== $echecAttendu) {
        throw new RuntimeException('Résultat inattendu du migrateur (' . implode(' ', $arguments) . ") :\n" . $sortie);
    }
};

$code = 1;
try {
    $migrer(['--jusqua=011']);
    $db = new PDO($dsn . ';dbname=' . $base, $utilisateur, $motDePasse, $pdoOptions);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $base) {
        throw new RuntimeException('Mauvaise base de recette.');
    }
    $ajout = $db->prepare("INSERT INTO appointments (reference, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, status, scheduled_at, updated_at)
        VALUES (?, 'renseignement', ?, '2026-11-21', 'matin', 'Recette', 'Fictive', 'agenda@recette.test', '770000000', ?, ?, '2026-11-20 08:00:00')");
    foreach ([['demande', 'agence-dakar', null], ['confirme', 'agence-ziguinchor', '2026-11-21 09:00:00'],
        ['termine', 'visio', '2026-11-21 10:00:00'], ['annule', 'telephone', null]] as $i => [$statut, $mode, $heure]) {
        $ajout->execute(['RECETTE-' . ($i + 1), $mode, $statut, $heure]);
    }
    $etat011 = $instantane($db);

    echo "\nMigration 012 :\n";
    $migrer(['--jusqua=012']);
    $lignes = $db->query('SELECT status, agency_id, starts_at_utc, ends_at_utc, updated_at FROM appointments ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    $lignes = array_map(static fn (array $l): array => [$l[0], $l[1] === null ? null : (int) $l[1], $l[2], $l[3], $l[4]], $lignes);
    $verifier($lignes === [
        ['demande', 1, null, null, '2026-11-20 08:00:00'],
        ['confirme', 2, '2026-11-21 09:00:00', null, '2026-11-20 08:00:00'],
        ['honore', 1, '2026-11-21 10:00:00', null, '2026-11-20 08:00:00'],
        ['annule', 1, null, null, '2026-11-20 08:00:00'],
    ], '012 reprend statuts, agences et heures historiques sans changer updated_at');
    $verifier($db->query("SELECT timezone FROM agencies WHERE code = 'paris'")->fetchColumn() === 'Europe/Paris', '012 configure le fuseau de Paris');
    $index = $db->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'appointments_active_start_unique' AND NON_UNIQUE = 0 ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_COLUMN);
    $verifier($index === ['assigned_to', 'active_starts_at_utc'], '012 crée l’index unique sur le responsable et le début actif');
    $restrict = (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('appointments', 'availability_rules', 'availability_exceptions', 'appointment_events') AND DELETE_RULE = 'RESTRICT'")->fetchColumn();
    $verifier($restrict === 9, '012 : neuf clés étrangères en RESTRICT (huit nouvelles et celle vers la fiche personne)');
    $etat012 = $instantane($db);

    echo "\nMigration 013 :\n";
    $migrer(['--jusqua=013']);
    $durees = array_map('intval', $db->query('SELECT motive, minutes FROM appointment_durations')->fetchAll(PDO::FETCH_KEY_PAIR));
    ksort($durees, SORT_STRING);
    $verifier($durees === ['admission' => 60, 'logement' => 30, 'orientation-filieres' => 45, 'orientation-metiers' => 45,
        'prise-en-charge' => 30, 'renseignement' => 30, 'visa' => 30, 'voyage' => 30], '013 initialise les huit durées prévues');
    $regle = $db->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'appointment_cancellation_fk'")->fetchColumn();
    $verifier($regle === 'RESTRICT', '013 impose RESTRICT sur les jetons d’annulation');
    $etat013 = $instantane($db);

    echo "\nRetours arrière :\n";
    foreach (['013_reglages_agenda' => $etat012, '012_agenda' => $etat011] as $nom => $attendu) {
        foreach ($instructions("{$racine}/database/rollback/{$nom}.down.sql") as $sql) {
            $db->exec($sql);
        }
        $comparer($instantane($db), $attendu, "Retour arrière de {$nom} : schéma, données et journal identiques");
    }

    echo "\nInterruptions et reprises :\n";
    foreach (array_slice($instructions("{$racine}/database/migrations/012_agenda.sql"), 0, 3) as $sql) {
        $db->exec($sql);
    }
    $migrer(['--jusqua=012'], true);
    $verifier((int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '012_agenda.sql'")->fetchColumn() === 0, '012 interrompue : absente du journal, échec clair à la relance');
    $migrer(['--jusqua=012', '--reprendre=012_agenda.sql:4']);
    $comparer($instantane($db), $etat012, 'Reprise de 012 à l’instruction 4 : identique à l’aller complet');
    foreach (array_slice($instructions("{$racine}/database/migrations/013_reglages_agenda.sql"), 0, 2) as $sql) {
        $db->exec($sql);
    }
    $migrer(['--jusqua=013'], true);
    $verifier((int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '013_reglages_agenda.sql'")->fetchColumn() === 0, '013 interrompue : absente du journal, échec clair à la relance');
    $migrer(['--jusqua=013', '--reprendre=013_reglages_agenda.sql:3']);
    $comparer($instantane($db), $etat013, 'Reprise de 013 à l’instruction 3 : identique à l’aller complet');
    $migrer(['--jusqua=013']);
    $comparer($instantane($db), $etat013, 'Une relance complète laisse le lot et son journal intacts');

    $code = in_array(false, $resultats, true) ? 1 : 0;
} catch (Throwable $exception) {
    fwrite(STDERR, "\nArrêt : " . $exception->getMessage() . "\n");
} finally {
    $serveur->exec("DROP DATABASE IF EXISTS `{$base}`");
}

echo "\n" . count($resultats) . ' contrôles, ' . count(array_filter($resultats)) . ' réussis, ' . count(array_filter($resultats, static fn (bool $ok): bool => !$ok)) . " en échec.\nBase {$base} supprimée.\n";
exit($code);
