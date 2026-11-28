<?php

/*
 * Répétition de la migration 011 (personnes) sur une COPIE protégée (docs/gestion-phase-2.md, C7 et K7).
 *
 * 1. Crée une base au nom unique `taysir_p<aléa>_recette` (refus si elle existe ; jamais d'autre base touchée).
 * 2. Point de départ :
 *    - par défaut : migrations 001 à 010, puis le JEU SYNTHÉTIQUE C7 : comptes clients avec demandes et rendez-vous,
 *      rendez-vous sans compte dont deux avec le même e-mail, une réservation anonyme avec l'e-mail d'un compte,
 *      tous les anciens statuts ;
 *    - ou --depuis=sauvegarde.sql : une sauvegarde mysqldump de la production (copie), complétée par le jeu
 *      synthétique (la production ne contient encore ni compte ni demande), puis 010 au plus.
 * 3. Applique 011 et vérifie la reprise (P11) : une personne par compte client, demandes et rendez-vous rattachés,
 *    aucune fusion, jamais de rattachement d'un rendez-vous anonyme à un compte ; updated_at conservés ; RESTRICT.
 * 4. Relance des instructions de reprise : aucun doublon.
 * 5. Garde-fou du retour arrière : il s'arrête sans rien perdre s'il existe un dossier sans compte.
 * 6. Retour arrière : schéma et données identiques au point de départ.
 * 7. Interruption simulée (011 coupée après l'instruction 3) puis reprise avec --reprendre : même résultat.
 * 8. Supprime la copie (sauf --garder).
 *
 * Usage : php -n -d extension_dir=… -d extension=pdo_mysql -d extension=mbstring bin/repetition-011.php
 *         [--depuis=sauvegarde.sql] [--mysql=chemin/mysql] [--sql-user=… --sql-password=…] [--garder]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

$root = dirname(__DIR__);
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $m)) {
        $options[$m[1]] = $m[2] ?? true;
    }
}
$config = is_file("{$root}/config/database.local.php") ? require "{$root}/config/database.local.php" : require "{$root}/config/database.php";
$sqlUser = is_string($options['sql-user'] ?? null) ? $options['sql-user'] : $config['user'];
$sqlPassword = is_string($options['sql-password'] ?? null) ? $options['sql-password'] : $config['password'];
$base = 'taysir_p' . bin2hex(random_bytes(4)) . '_recette';
$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
$pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$server = new PDO($dsn, $sqlUser, $sqlPassword, $pdoOptions);
$existe = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$existe->execute([$base]);
if ((int) $existe->fetchColumn() > 0) {
    fwrite(STDERR, "La base {$base} existe déjà : rien n'est touché. Relancez.\n");
    exit(1);
}
$server->exec("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$php = require __DIR__ . '/php-enfant.php';
$env = array_merge(getenv(), ['TAYSIR_DB' => $base]);
if (isset($options['sql-user']) || isset($options['sql-password'])) {
    $env += ['TAYSIR_DB_USER' => $sqlUser, 'TAYSIR_DB_PASSWORD' => $sqlPassword];
}
$resultats = [];
$verifier = static function (string $etape, string $controle, bool $ok, string $obtenu = '') use (&$resultats): void {
    $resultats[] = $ok;
    echo ($ok ? '  OK    ' : '  ÉCHEC ') . str_pad($etape, 11) . $controle . ($obtenu !== '' ? ' → ' . $obtenu : '') . "\n";
};
$migrer = static function (array $arguments = []) use ($php, $root, $env): array {
    $process = proc_open(array_merge($php, [$root . '/database/migrate.php'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
    $sortie = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

    return [proc_close($process), $sortie];
};
$instructions = static function (string $fichier): array {
    $contenu = is_file($fichier) ? (string) file_get_contents($fichier) : '';
    $sql = preg_replace('/^\s*--.*$/m', '', $contenu);
    $liste = array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)), static fn (string $i): bool => $i !== ''));
    if ($liste === []) {
        throw new RuntimeException("Fichier SQL absent ou vide : {$fichier}");
    }

    return $liste;
};
$migration = $root . '/database/migrations/011_personnes.sql';
// Exécute une instruction et lit son éventuel résultat (le garde-fou du retour arrière est un SELECT)
$executer = static function (PDO $db, string $instruction): void {
    $db->query($instruction)->closeCursor();
};
$retourArriere = $root . '/database/rollback/011_personnes.down.sql';

$code = 1;
try {
    $db = new PDO($dsn . ';dbname=' . $base, $sqlUser, $sqlPassword, $pdoOptions);

    /* ---------- Point de départ ---------- */
    if (is_string($options['depuis'] ?? null)) {
        $sauvegarde = $options['depuis'];
        if (!is_file($sauvegarde)) {
            throw new RuntimeException("Sauvegarde introuvable : {$sauvegarde}");
        }
        $mysql = is_string($options['mysql'] ?? null) ? $options['mysql'] : (glob('C:/wamp64/bin/mysql/mysql*/bin/mysql.exe')[0] ?? 'mysql');
        $fichierOptions = tempnam(sys_get_temp_dir(), 'rpt');
        chmod($fichierOptions, 0600);
        file_put_contents($fichierOptions, sprintf("[client]\nuser=%s\npassword=\"%s\"\nhost=%s\nport=%d\n",
            $sqlUser, addcslashes((string) $sqlPassword, '"\\'), $config['host'] === 'localhost' ? '127.0.0.1' : $config['host'], $config['port']));
        $chargement = proc_open([$mysql, '--defaults-file=' . $fichierOptions, $base],
            [0 => ['file', $sauvegarde, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        $erreur = stream_get_contents($pipes[2]);
        $statut = proc_close($chargement);
        @unlink($fichierOptions);
        if ($statut !== 0) {
            throw new RuntimeException("Chargement de la sauvegarde impossible : {$erreur}");
        }
        echo "Copie {$base} chargée depuis {$sauvegarde}.\n";
    }
    [$retour, $sortie] = $migrer(['--jusqua=010']);
    if ($retour !== 0) {
        throw new RuntimeException("Migrations jusqu'à 010 en échec :\n{$sortie}");
    }
    if ((int) $db->query("SELECT COUNT(*) FROM migrations WHERE name >= '011'")->fetchColumn() > 0) {
        throw new RuntimeException('Le point de départ contient déjà la migration 011 : répétition sans objet.');
    }

    /* Jeu synthétique C7 (ajouté aussi à une copie de production, qui n'a encore ni compte ni demande) */
    $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $u = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, profile, role, password_hash, consent_at) VALUES (?, ?, ?, '77 000 00 00', 'etudiant', ?, ?, NOW())");
    $ids = [];
    foreach ([['Synth', 'Conseil', 'conseil@synth.test', 'conseiller'], ['Awa', 'Synth', 'awa@synth.test', 'client'], ['Lamine', 'Synth', 'lamine@synth.test', 'client'], ['Sans', 'Rien', 'sansrien@synth.test', 'client']] as [$prenom, $nom, $email, $role]) {
        $u->execute([$prenom, $nom, $email, $role, $hash]);
        $ids[$email] = (int) $db->lastInsertId();
    }
    $r = $db->prepare("INSERT INTO service_requests (reference, user_id, service, status, first_name, last_name, email, phone, created_at, updated_at)
        VALUES (?, ?, 'visa', ?, 'Client', 'Synth', 'client@synth.test', '77 000 00 00', '2026-01-05 10:00:00', '2026-02-01 09:30:00')");
    $n = 0;
    foreach (['nouvelle', 'en-cours', 'attente-client', 'terminee', 'annulee'] as $statut) {
        $r->execute(['TAY-SYN-' . ++$n, $ids[$n % 2 ? 'awa@synth.test' : 'lamine@synth.test'], $statut]);
        $db->prepare("INSERT INTO request_events (request_id, user_id, type, status, message, visible) VALUES (?, NULL, 'creation', 'nouvelle', 'Demande envoyée.', 1)")->execute([(int) $db->lastInsertId()]);
    }
    $a = $db->prepare("INSERT INTO appointments (reference, user_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, status, created_at, updated_at)
        VALUES (?, ?, 'renseignement', 'telephone', '2026-03-02', 'matin', ?, ?, ?, '77 000 00 01', ?, '2026-02-10 08:00:00', '2026-02-11 08:00:00')");
    $rdv = [
        // [référence, compte, prénom, nom, e-mail, statut]
        ['RDV-SYN-1', 'awa@synth.test', 'Awa', 'Synth', 'awa@synth.test', 'demande'],
        ['RDV-SYN-2', 'lamine@synth.test', 'Lamine', 'Synth', 'lamine@synth.test', 'confirme'],
        ['RDV-SYN-3', null, 'Haby', 'Sow', 'haby@synth.test', 'termine'],
        ['RDV-SYN-4', null, ' haby ', 'SOW', 'HABY@synth.test ', 'annule'],            // même personne que SYN-3 (e-mail et nom normalisés)
        ['RDV-SYN-5', null, 'Usurpateur', 'Anonyme', 'awa@synth.test', 'demande'],    // e-mail d'un compte : jamais rattaché à ce compte
        ['RDV-SYN-6', null, 'Gora', 'Wade', 'gora@synth.test', 'confirme'],
        ['RDV-SYN-7', null, 'Autre', 'Nom', 'gora@synth.test', 'demande'],            // même e-mail, autre nom : autre fiche, « à rapprocher »
    ];
    foreach ($rdv as [$ref, $compte, $prenom, $nom, $email, $statut]) {
        $a->execute([$ref, $compte !== null ? $ids[$compte] : null, $prenom, $nom, $email, $statut]);
    }
    echo "Jeu synthétique C7 : " . count($ids) . ' comptes, ' . $n . ' demandes (tous statuts), ' . count($rdv) . " rendez-vous (tous statuts).\n";

    /* ---------- Empreintes ---------- */
    $schema = static function () use ($db, $base): string {
        $empreinte = [];
        foreach ([
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'migrations' ORDER BY TABLE_NAME, COLUMN_NAME",
            'SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? ORDER BY TABLE_NAME, CONSTRAINT_NAME',
            "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'migrations' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
        ] as $sql) {
            $s = $db->prepare($sql);
            $s->execute([$base]);
            $empreinte[] = $s->fetchAll(PDO::FETCH_NUM);
        }

        return md5(json_encode($empreinte));
    };
    $donnees = static function () use ($db): string {
        $empreinte = [];
        foreach (['users', 'service_requests', 'request_events', 'appointments'] as $table) {
            $empreinte[$table] = $db->query("SELECT * FROM `{$table}` ORDER BY id")->fetchAll(PDO::FETCH_NUM);
        }

        return md5(json_encode($empreinte));
    };
    $dates = static fn (): string => md5(json_encode([
        $db->query('SELECT id, updated_at FROM service_requests ORDER BY id')->fetchAll(PDO::FETCH_NUM),
        $db->query('SELECT id, updated_at FROM appointments ORDER BY id')->fetchAll(PDO::FETCH_NUM),
        $db->query('SELECT id, updated_at FROM users ORDER BY id')->fetchAll(PDO::FETCH_NUM),
    ]));
    // Fiches attendues : une par compte client, plus une par groupe anonyme (e-mail et nom normalisés), y compris les
    // rendez-vous déjà présents dans une copie de production
    $fichesAttendues = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn()
        + (int) $db->query('SELECT COUNT(*) FROM (SELECT 1 FROM appointments WHERE user_id IS NULL GROUP BY LOWER(TRIM(email)), LOWER(TRIM(first_name)), LOWER(TRIM(last_name))) g')->fetchColumn();
    $schemaAvant = $schema();
    $donneesAvant = $donnees();
    $datesAvant = $dates();
    sleep(1); // une date ON UPDATE CURRENT_TIMESTAMP modifiée par la migration se voit alors à coup sûr

    $verifierReprise = static function (string $etape) use ($db, $verifier, $ids, $base, $dates, $datesAvant, $fichesAttendues): void {
        $clients = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
        $avecFiche = (int) $db->query("SELECT COUNT(DISTINCT person_id) FROM users WHERE role = 'client' AND person_id IS NOT NULL")->fetchColumn();
        $verifier($etape, 'P11 : une personne par compte client', $avecFiche === $clients, "{$avecFiche} sur {$clients}");
        $equipe = (int) $db->query("SELECT COUNT(*) FROM users WHERE role <> 'client' AND person_id IS NOT NULL")->fetchColumn();
        $verifier($etape, 'Aucune fiche créée pour l’équipe', $equipe === 0, (string) $equipe);
        $orphelines = (int) $db->query('SELECT COUNT(*) FROM service_requests WHERE person_id IS NULL')->fetchColumn()
            + (int) $db->query('SELECT COUNT(*) FROM appointments WHERE person_id IS NULL')->fetchColumn();
        $verifier($etape, 'Toutes les demandes et tous les rendez-vous rattachés', $orphelines === 0, $orphelines . ' sans personne');
        $coherent = (int) $db->query('SELECT COUNT(*) FROM service_requests r JOIN users u ON u.id = r.user_id WHERE r.person_id <> u.person_id')->fetchColumn();
        $verifier($etape, 'Demandes d’un compte : sur la fiche du compte', $coherent === 0, $coherent . ' écart(s)');
        $fiche = static fn (string $ref): int => (int) $db->query('SELECT person_id FROM appointments WHERE reference = ' . $db->quote($ref))->fetchColumn();
        $awa = (int) $db->query('SELECT person_id FROM users WHERE id = ' . $ids['awa@synth.test'])->fetchColumn();
        $verifier($etape, 'Rendez-vous anonyme avec l’e-mail d’un compte : autre fiche', $fiche('RDV-SYN-5') !== $awa && $fiche('RDV-SYN-1') === $awa, 'SYN-5 sur ' . $fiche('RDV-SYN-5') . ', compte sur ' . $awa);
        $verifier($etape, 'Même e-mail et même nom normalisés : une seule fiche', $fiche('RDV-SYN-3') === $fiche('RDV-SYN-4'), $fiche('RDV-SYN-3') . ' / ' . $fiche('RDV-SYN-4'));
        $verifier($etape, 'Même e-mail, autre nom : deux fiches (aucune fusion)', $fiche('RDV-SYN-6') !== $fiche('RDV-SYN-7'), $fiche('RDV-SYN-6') . ' / ' . $fiche('RDV-SYN-7'));
        $aRapprocher = $db->query('SELECT p.id FROM people p WHERE p.to_reconcile = 1 ORDER BY p.id')->fetchAll(PDO::FETCH_COLUMN);
        $attendues = [$fiche('RDV-SYN-5'), $fiche('RDV-SYN-6'), $fiche('RDV-SYN-7')];
        sort($attendues);
        $verifier($etape, 'Fiches « à rapprocher » : SYN-5 (e-mail d’un compte), SYN-6 et SYN-7 (e-mail partagé)', array_map('intval', $aRapprocher) === $attendues, json_encode($aRapprocher));
        $total = (int) $db->query('SELECT COUNT(*) FROM people')->fetchColumn();
        $verifier($etape, 'Nombre de fiches : comptes clients + groupes anonymes', $total === $fichesAttendues, $total . ' sur ' . $fichesAttendues . ' attendues');
        $verifier($etape, 'updated_at des demandes, rendez-vous et comptes conservés', $dates() === $datesAvant);
        $regles = $db->query("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$base}'
            AND CONSTRAINT_NAME IN ('people_agency_fk', 'people_created_by_fk', 'users_person_fk', 'service_requests_person_fk', 'service_requests_user_fk', 'appointments_person_fk')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $verifier($etape, 'Six clés étrangères en RESTRICT', count($regles) === 6 && array_unique(array_values($regles)) === ['RESTRICT'], json_encode($regles));
        $defaut = $db->query("SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$base}' AND TABLE_NAME = 'request_events' AND COLUMN_NAME = 'visible'")->fetchColumn();
        $verifier($etape, 'C5 : événements internes par défaut (visible = 0)', trim((string) $defaut, "'") === '0', (string) $defaut);
        $visibles = (int) $db->query('SELECT COUNT(*) FROM request_events WHERE visible = 1')->fetchColumn();
        $verifier($etape, 'Événements existants : visibilité inchangée', $visibles === 5, $visibles . ' visibles sur 5');
        $verifier($etape, 'Migration 011 enregistrée', (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '011_personnes.sql'")->fetchColumn() === 1);
    };

    /* ---------- Migration ---------- */
    echo "\nMigration 011 :\n";
    [$retour, $sortie] = $migrer(['--jusqua=011']);
    $verifier('migration', 'migrate.php se termine sans erreur', $retour === 0, trim(str_replace("\n", ' | ', $sortie)));
    $verifierReprise('migration');

    /* ---------- Relance des instructions de reprise (idempotence) ---------- */
    $liste = $instructions($migration);
    $debutReprise = null;
    foreach ($liste as $i => $instruction) {
        if (str_starts_with($instruction, 'INSERT INTO people')) {
            $debutReprise ??= $i;
        }
    }
    $avant = (int) $db->query('SELECT COUNT(*) FROM people')->fetchColumn();
    foreach (array_slice($liste, (int) $debutReprise) as $instruction) {
        $executer($db, $instruction);
    }
    $apres = (int) $db->query('SELECT COUNT(*) FROM people')->fetchColumn();
    $verifier('relance', 'Instructions de reprise rejouées : aucune fiche en plus', $apres === $avant, "{$avant} puis {$apres}");

    /* ---------- Garde-fou du retour arrière ---------- */
    echo "\nRetour arrière :\n";
    $db->exec("INSERT INTO service_requests (reference, user_id, person_id, service, first_name, last_name, email, phone) VALUES ('TAY-SYN-SANS', NULL, (SELECT MIN(id) FROM people), 'visa', 'Sans', 'Compte', 's@synth.test', '77')");
    $arret = null;
    try {
        foreach ($instructions($retourArriere) as $instruction) {
            $executer($db, $instruction);
        }
    } catch (PDOException $exception) {
        $arret = $exception->errorInfo[1] ?? 0;
    }
    $intact = $db->query("SELECT user_id FROM service_requests WHERE reference = 'TAY-SYN-SANS'")->fetchColumn();
    $cleFk = (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$base}' AND CONSTRAINT_NAME = 'service_requests_user_fk'")->fetchColumn();
    $verifier('garde-fou', 'Dossier sans compte : arrêt (1242) avant tout changement', $arret === 1242 && $intact === null && $cleFk === 1 && (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '011_personnes.sql'")->fetchColumn() === 1, 'erreur ' . var_export($arret, true) . ', user_id ' . var_export($intact, true));
    $db->exec("DELETE FROM service_requests WHERE reference = 'TAY-SYN-SANS'");

    $db->exec("INSERT INTO people (first_name, last_name, origin) VALUES ('Visite', 'Synthétique', 'visite')");
    $visite = (int) $db->lastInsertId();
    $schemaAvecVisite = $schema();
    $arret = null;
    try {
        foreach ($instructions($retourArriere) as $instruction) {
            $executer($db, $instruction);
        }
    } catch (PDOException $exception) {
        $arret = $exception->errorInfo[1] ?? 0;
    }
    $verifier('garde-fou', 'Une fiche visite bloque le retour avant toute modification',
        $arret === 1242 && $schema() === $schemaAvecVisite && (int) $db->query("SELECT COUNT(*) FROM people WHERE id = {$visite}")->fetchColumn() === 1, (string) $arret);
    $db->prepare('DELETE FROM people WHERE id = ?')->execute([$visite]);

    foreach ($instructions($retourArriere) as $instruction) {
        $executer($db, $instruction);
    }
    $verifier('retour', 'Schéma identique au point de départ (colonnes, clés, index)', $schema() === $schemaAvant);
    $verifier('retour', 'Comptes, demandes, événements et rendez-vous identiques', $donnees() === $donneesAvant);
    $verifier('retour', 'Table people supprimée, migration désenregistrée', (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$base}' AND TABLE_NAME = 'people'")->fetchColumn() === 0
        && (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '011_personnes.sql'")->fetchColumn() === 0);

    /* ---------- Interruption simulée et reprise ---------- */
    echo "\nReprise après interruption :\n";
    foreach (array_slice($liste, 0, 3) as $instruction) { // « coupure » après l'instruction 3 (users.person_id créé)
        $executer($db, $instruction);
    }
    [$retour, $sortie] = $migrer(['--jusqua=011']);
    $verifier('reprise', 'Relance simple : échec clair, migration non enregistrée',
        $retour !== 0 && str_contains($sortie, '--reprendre=011_personnes.sql:')
        && (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '011_personnes.sql'")->fetchColumn() === 0, $retour !== 0 ? 'échec signalé' : 'pas d’échec !');
    [$retour, $sortie] = $migrer(['--reprendre=011_personnes.sql:4', '--jusqua=011']);
    $verifier('reprise', 'Reprise avec --reprendre=011_personnes.sql:4', $retour === 0 && str_contains($sortie, 'Appliquée : 011_personnes.sql'), trim(str_replace("\n", ' | ', $sortie)));
    $verifierReprise('reprise');

    $code = in_array(false, $resultats, true) ? 1 : 0;
} catch (Throwable $exception) {
    fwrite(STDERR, "\nArrêt : " . $exception->getMessage() . "\n");
} finally {
    if (!isset($options['garder'])) {
        $server->exec("DROP DATABASE IF EXISTS `{$base}`");
    }
}

echo "\n" . count($resultats) . ' contrôles, ' . count(array_filter($resultats)) . ' réussis, ' . count(array_filter($resultats, static fn (bool $ok): bool => !$ok)) . " en échec.\n";
echo isset($options['garder']) ? "Copie {$base} conservée pour inspection.\n" : "Copie {$base} supprimée.\n";
exit($code);
