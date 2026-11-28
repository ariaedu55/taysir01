<?php

/*
 * Répétition de la migration 014 (notes, modèles, étapes ; 2C) sur une base FICTIVE (docs/gestion-phase-2.md, C7,
 * conditions C-2C-2, N5 et N8) : aller ; colonne email_normalized identique à BookingLimiter::email() et index utilisé ;
 * unicité de la version publiée ; garde-fous du retour arrière (note, étape, dossier sans e-mail) ; retour arrière
 * identique ; interruption puis --reprendre ; relance sans effet. Supprime la base.
 *
 * Usage : php -n -d extension_dir=… -d extension=pdo_mysql -d extension=mbstring bin/repetition-014.php
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
$base = 'taysir_014_' . bin2hex(random_bytes(5)) . '_recette';
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

// Instantané comparable d'un aller à l'autre : les dates de création des modèles de départ diffèrent forcément
$etat = static function (PDO $db) use ($instantane): array {
    $resultat = $instantane($db);
    if (isset($resultat['procedure_templates'])) {
        $resultat['procedure_templates'][1] = array_map(static fn (array $l): array => array_diff_key($l, ['created_at' => 1, 'updated_at' => 1]), $resultat['procedure_templates'][1]);
    }

    return $resultat;
};
// Même règle que BookingLimiter::email() ; le fichier est relu plus bas pour s'assurer qu'il n'a pas divergé
$normaliserPhp = static function (string $email): string {
    $email = mb_strtolower(trim($email, ' '));
    if (str_contains($email, '@')) {
        [$local, $domaine] = explode('@', $email, 2);
        $email = explode('+', $local, 2)[0] . '@' . $domaine;
    }

    return $email;
};

$code = 1;
try {
    $migrer(['--jusqua=013']);
    $db = new PDO($dsn . ';dbname=' . $base, $utilisateur, $motDePasse, $pdoOptions);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $base) {
        throw new RuntimeException('Mauvaise base de recette.');
    }
    // Jeu fictif : un compte, sa fiche, un dossier de la 1B, des rendez-vous aux adresses piégées (N8)
    $db->exec("INSERT INTO users (id, first_name, last_name, email, role, password_hash, consent_at) VALUES (501, 'Recette', 'Client', 'client014@recette.test', 'client', 'x', NOW())");
    $db->exec("INSERT INTO people (id, first_name, last_name, email, origin, import_ref) VALUES (601, 'Recette', 'Client', 'client014@recette.test', 'reprise', 'user:501')");
    $db->exec('UPDATE users SET person_id = 601 WHERE id = 501');
    $db->exec("INSERT INTO service_requests (reference, user_id, person_id, service, first_name, last_name, email, phone, updated_at)
        VALUES ('TAY-014-1', 501, 601, 'visa', 'Recette', 'Client', 'client014@recette.test', '770000000', '2026-11-20 08:00:00')");
    $adresses = ['Ami.Test+Promo@Exemple.SN', '  espace@exemple.sn  ', 'sans-arobase', '+@exemple.sn', 'a@b@c.sn', 'simple@exemple.sn'];
    $rdv = $db->prepare("INSERT INTO appointments (reference, person_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, updated_at)
        VALUES (?, 601, 'renseignement', 'telephone', '2026-12-01', 'matin', 'Recette', 'Client', ?, '770000000', '2026-11-20 08:00:00')");
    foreach ($adresses as $i => $adresse) {
        $rdv->execute(['RDV-014-' . ($i + 1), $adresse]);
    }
    $etat013 = $etat($db);

    echo "\nMigration 014 :\n";
    $migrer(['--jusqua=014']);
    $verifier((int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('notes', 'procedure_templates', 'request_steps')")->fetchColumn() === 3, '014 crée notes, procedure_templates et request_steps');
    $modeles = $db->query('SELECT template_key, version, status, verified_at FROM procedure_templates ORDER BY template_key')->fetchAll(PDO::FETCH_NUM);
    $verifier(count($modeles) === 3 && array_unique(array_column($modeles, 2)) === ['brouillon'] && array_filter(array_column($modeles, 3)) === [], '014 : trois modèles de départ, en brouillon, non vérifiés (D5)');
    $restrict = (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND CONSTRAINT_NAME IN ('notes_person_fk', 'notes_appointment_fk', 'notes_author_fk', 'procedure_templates_verified_fk', 'procedure_templates_created_fk',
            'request_steps_request_fk', 'request_steps_done_fk', 'service_requests_template_fk', 'service_requests_opened_by_fk') AND DELETE_RULE = 'RESTRICT'")->fetchColumn();
    $verifier($restrict === 9, '014 : neuf clés étrangères en RESTRICT', (string) $restrict);
    $defaut = $db->query("SELECT COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'visibility'")->fetch(PDO::FETCH_NUM);
    $verifier(($defaut[0] === null || strtoupper((string) $defaut[0]) === 'NULL') && $defaut[1] === 'NO', 'E1 : visibilité obligatoire et sans valeur par défaut', json_encode($defaut));
    $dossier = $db->query("SELECT email, phone, updated_at FROM service_requests WHERE reference = 'TAY-014-1'")->fetch(PDO::FETCH_NUM);
    $verifier($dossier === ['client014@recette.test', '770000000', '2026-11-20 08:00:00'], 'Dossier de la 1B identique (P18), updated_at conservé');

    // N8 : colonne générée identique à BookingLimiter::email(), sur les cas piégés
    $source = (string) file_get_contents($racine . '/app/Services/BookingLimiter.php');
    $verifier(str_contains($source, "mb_strtolower(trim(\$email, ' '))") && str_contains($source, "explode('@', \$email, 2)") && str_contains($source, "explode('+', \$local, 2)[0]"), 'N8 : la normalisation PHP est bien celle comparée ici');
    $sql = $db->query("SELECT email, email_normalized FROM appointments WHERE reference LIKE 'RDV-014-%' ORDER BY id")->fetchAll(PDO::FETCH_NUM);
    $ecarts = array_filter($sql, static fn (array $l): bool => $normaliserPhp($l[0]) !== $l[1]);
    $verifier($ecarts === [] && count($sql) === 6, 'N8 : SQL et PHP identiques (majuscules, espaces, alias, sans « @ », « + » seul, deux « @ »)', json_encode(array_values($ecarts), JSON_UNESCAPED_UNICODE));
    $plan = $db->prepare("EXPLAIN SELECT COUNT(*) FROM appointments WHERE email_normalized = ? AND (status = 'demande' OR (status = 'confirme' AND (starts_at_utc IS NULL OR starts_at_utc > UTC_TIMESTAMP())))");
    $plan->execute(['simple@exemple.sn']);
    $cles = implode(',', array_map(static fn (array $l): string => (string) ($l['key'] ?? ''), $plan->fetchAll()));
    $verifier(str_contains($cles, 'appointments_email_normalized_index'), 'N8 : EXPLAIN utilise l’index de l’e-mail normalisé', $cles);

    // N5 : une seule version publiée par clé, garantie par la base
    $db->exec("UPDATE procedure_templates SET status = 'publie', verified_at = NOW() WHERE template_key = 'france-campus'");
    $db->exec("INSERT INTO procedure_templates (template_key, version, title, service, status, steps) VALUES ('france-campus', 2, 'France v2', 'admission', 'brouillon', '[]')");
    $doublon = null;
    try {
        $db->exec("UPDATE procedure_templates SET status = 'publie' WHERE template_key = 'france-campus' AND version = 2");
    } catch (PDOException $exception) {
        $doublon = (int) ($exception->errorInfo[1] ?? 0);
    }
    $verifier($doublon === 1062, 'N5 : deux versions publiées d’un même modèle refusées par la base', var_export($doublon, true));
    $db->exec("DELETE FROM procedure_templates WHERE template_key = 'france-campus' AND version = 2");
    $db->exec("UPDATE procedure_templates SET status = 'brouillon', verified_at = NULL WHERE template_key = 'france-campus'");
    $etat014 = $etat($db);

    echo "\nGarde-fous du retour arrière :\n";
    $retour = static function () use ($db, $instructions, $racine): ?int {
        try {
            foreach ($instructions("{$racine}/database/rollback/014_notes_modeles.down.sql") as $sql) {
                $db->query($sql)->closeCursor();
            }
        } catch (PDOException $exception) {
            return (int) ($exception->errorInfo[1] ?? 0);
        }

        return null;
    };
    foreach ([
        'une note' => ["INSERT INTO notes (person_id, visibility, body, author_id, author_poste) VALUES (601, 'interne', 'Recette', 501, 'conseiller')", 'DELETE FROM notes'],
        'une étape' => ["INSERT INTO request_steps (request_id, position, title) SELECT id, 1, 'Recette' FROM service_requests WHERE reference = 'TAY-014-1'", 'DELETE FROM request_steps'],
        'un dossier sans e-mail' => ["INSERT INTO service_requests (reference, person_id, service, first_name, last_name, email, phone) VALUES ('TAY-014-2', 601, 'visa', 'Sans', 'Email', NULL, '770000000')", "DELETE FROM service_requests WHERE reference = 'TAY-014-2'"],
    ] as $cas => [$ajout, $retrait]) {
        $db->exec($ajout);
        $avant = $etat($db);
        $arret = $retour();
        $verifier($arret === 1242 && $etat($db) === $avant, "Retour arrière refusé avec {$cas}, rien de modifié", var_export($arret, true));
        $db->exec($retrait);
    }

    echo "\nRetour arrière :\n";
    $arret = $retour();
    $verifier($arret === null, 'Retour arrière de 014 sans erreur', var_export($arret, true));
    $comparer($etat($db), $etat013, 'Retour arrière de 014 : schéma, données et journal identiques');

    echo "\nInterruption et reprise :\n";
    foreach (array_slice($instructions("{$racine}/database/migrations/014_notes_modeles.sql"), 0, 4) as $sql) {
        $db->exec($sql);
    }
    $migrer(['--jusqua=014'], true);
    $verifier((int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '014_notes_modeles.sql'")->fetchColumn() === 0, '014 interrompue : absente du journal, échec clair à la relance');
    $migrer(['--jusqua=014', '--reprendre=014_notes_modeles.sql:5']);
    $comparer($etat($db), $etat014, 'Reprise de 014 à l’instruction 5 : identique à l’aller complet');
    $migrer(['--jusqua=014']);
    $comparer($etat($db), $etat014, 'Une relance complète laisse tout intact');

    $code = in_array(false, $resultats, true) ? 1 : 0;
} catch (Throwable $exception) {
    fwrite(STDERR, "\nArrêt : " . $exception->getMessage() . "\n");
} finally {
    $serveur->exec("DROP DATABASE IF EXISTS `{$base}`");
}

echo "\n" . count($resultats) . ' contrôles, ' . count(array_filter($resultats)) . ' réussis, ' . count(array_filter($resultats, static fn (bool $ok): bool => !$ok)) . " en échec.\nBase {$base} supprimée.\n";
exit($code);
