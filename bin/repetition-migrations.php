<?php

/*
 * Répétition des migrations 007 à 010 sur une COPIE protégée (docs/gestion-phase-1.md, 1B bis ; revue 1B point 4).
 *
 * 1. Crée une base au nom unique `taysir_m<aléa>_recette` (refus si elle existe ; jamais d'autre base touchée).
 * 2. Point de départ :
 *    - par défaut, l'état de production d'avant la 1B : migrations 001 à 006, puis des données FICTIVES avec des
 *      attributions existantes (assigned_to) ;
 *    - ou --depuis=sauvegarde.sql : une sauvegarde mysqldump de la production (copie, jamais la base elle-même),
 *      chargée avec le client mysql (--mysql=chemin, sinon détecté).
 * 3. Applique 007 à 010 et vérifie : agences, rattachement à Dakar, reprise des attributions en affectations,
 *    RESTRICT, files ; données des dossiers inchangées.
 * 4. Retour arrière (database/rollback/, de 010 à 007) : schéma et données identiques au point de départ.
 * 5. Réapplication complète, puis simulation d'une migration INTERROMPUE (010 coupée après l'instruction 1) et
 *    reprise avec --reprendre.
 * 6. Supprime la copie (sauf --garder).
 *
 * Usage : php -n -d extension_dir=… -d extension=pdo_mysql -d extension=mbstring bin/repetition-migrations.php
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
$base = 'taysir_m' . bin2hex(random_bytes(4)) . '_recette';
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

// Mêmes réglages et extensions que ce processus, sur n'importe quelle machine (WAMP, VPS)
$php = require __DIR__ . '/php-enfant.php';
$env = array_merge(getenv(), ['TAYSIR_DB' => $base]);
if ($sqlUser !== $config['user']) {
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

$code = 1;
try {
    $db = new PDO($dsn . ';dbname=' . $base, $sqlUser, $sqlPassword, $pdoOptions);
    $executerFichier = static function (string $fichier) use ($db): void {
        // Un fichier absent ou vide arrête tout : un retour arrière qui ne fait rien ne doit jamais passer pour réussi
        $contenu = is_file($fichier) ? (string) file_get_contents($fichier) : '';
        $sql = preg_replace('/^\s*--.*$/m', '', $contenu);
        $instructions = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)), static fn (string $i): bool => $i !== '');
        if ($instructions === []) {
            throw new RuntimeException("Fichier SQL absent ou vide : {$fichier}");
        }
        foreach ($instructions as $instruction) {
            $db->exec($instruction);
        }
    };

    /* ---------- Point de départ ---------- */
    if (is_string($options['depuis'] ?? null)) {
        $sauvegarde = $options['depuis'];
        if (!is_file($sauvegarde)) {
            throw new RuntimeException("Sauvegarde introuvable : {$sauvegarde}");
        }
        $mysql = is_string($options['mysql'] ?? null) ? $options['mysql'] : (glob('C:/wamp64/bin/mysql/mysql*/bin/mysql.exe')[0] ?? 'mysql');
        // Fichier d'options temporaire et exclusif (--defaults-file) : le client mysql n'y lit QUE ce compte,
        // jamais celui d'un ~/.my.cnf (qui l'emporterait sur un mot de passe passé autrement)
        $options = tempnam(sys_get_temp_dir(), 'rpt');
        chmod($options, 0600);
        file_put_contents($options, sprintf("[client]\nuser=%s\npassword=\"%s\"\nhost=%s\nport=%d\n",
            // « localhost » : TCP sur 127.0.0.1, car ce fichier exclusif ne donne pas le chemin du socket du serveur
            $sqlUser, addcslashes((string) $sqlPassword, '"\\'), $config['host'] === 'localhost' ? '127.0.0.1' : $config['host'], $config['port']));
        $chargement = proc_open([$mysql, '--defaults-file=' . $options, $base],
            [0 => ['file', $sauvegarde, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        $erreur = stream_get_contents($pipes[2]);
        $statut = proc_close($chargement);
        @unlink($options);
        if ($statut !== 0) {
            throw new RuntimeException("Chargement de la sauvegarde impossible : {$erreur}");
        }
        echo "Copie {$base} chargée depuis {$sauvegarde}.\n";
    } else {
        [$retour, $sortie] = $migrer(['--jusqua=006']);
        if ($retour !== 0) {
            throw new RuntimeException("Migrations 001-006 en échec :\n{$sortie}");
        }
        $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $u = $db->prepare("INSERT INTO users (first_name, last_name, email, profile, role, password_hash, consent_at) VALUES (?, ?, ?, 'particulier', ?, ?, NOW())");
        $ids = [];
        foreach ([['admin', 'admin'], ['conseil1', 'conseiller'], ['conseil2', 'conseiller'], ['client1', 'client'], ['client2', 'client']] as [$cle, $role]) {
            $u->execute(['Copie', ucfirst($cle), $cle . '@copie.test', $role, $hash]);
            $ids[$cle] = (int) $db->lastInsertId();
        }
        $r = $db->prepare("INSERT INTO service_requests (reference, user_id, service, status, first_name, last_name, email, phone, assigned_to)
            VALUES (?, ?, 'visa', ?, 'Client', 'Copie', 'client@copie.test', '77 000 00 00', ?)");
        foreach ([['TAY-CPY-1', 'client1', 'en-cours', 'conseil1'], ['TAY-CPY-2', 'client2', 'nouvelle', 'conseil2'], ['TAY-CPY-3', 'client1', 'terminee', null], ['TAY-CPY-4', 'client2', 'nouvelle', null]] as [$ref, $client, $statut, $conseil]) {
            $r->execute([$ref, $ids[$client], $statut, $conseil !== null ? $ids[$conseil] : null]);
            $id = (int) $db->lastInsertId();
            $db->prepare("INSERT INTO request_documents (request_id, original_name, stored_name, mime, size) VALUES (?, 'piece.pdf', ?, 'application/pdf', 10)")->execute([$id, bin2hex(random_bytes(32)) . '.pdf']);
            $db->prepare("INSERT INTO request_events (request_id, user_id, type, status, message) VALUES (?, ?, 'creation', 'nouvelle', 'Demande envoyée.')")->execute([$id, $ids[$client]]);
        }
        echo "Copie {$base} : état d'avant la 1B (001-006) et données fictives avec attributions existantes.\n";
    }
    $appliquees = $db->query('SELECT name FROM migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    if (array_filter($appliquees, static fn (string $n): bool => strcmp($n, '007') >= 0) !== []) {
        throw new RuntimeException('Le point de départ contient déjà une migration 007 ou plus : répétition sans objet.');
    }

    /* ---------- Empreintes : schéma et données ---------- */
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
    $donnees = static function (array $tables) use ($db): string {
        $empreinte = [];
        foreach ($tables as $table) {
            $empreinte[$table] = $db->query("SELECT * FROM `{$table}` ORDER BY id")->fetchAll(PDO::FETCH_NUM);
        }

        return md5(json_encode($empreinte));
    };
    $tablesDossiers = ['service_requests', 'request_documents', 'request_events', 'appointments', 'contact_messages'];
    $schemaAvant = $schema();
    $dossiersAvant = $donnees($tablesDossiers);
    $usersAvant = $donnees(['users']);
    $attribues = $db->query('SELECT id, assigned_to FROM service_requests WHERE assigned_to IS NOT NULL ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $equipe = (int) $db->query("SELECT COUNT(*) FROM users WHERE role <> 'client'")->fetchColumn();
    // Une seconde d'écart : une date ON UPDATE CURRENT_TIMESTAMP modifiée par une migration se voit alors à coup sûr
    sleep(1);

    $verifierMigre = static function (string $etape) use ($db, $verifier, $attribues, $equipe, $donnees, $tablesDossiers, $dossiersAvant, $base): void {
        $agences = $db->query('SELECT code, active FROM agencies ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $verifier($etape, 'Agences : Dakar active, Ziguinchor et Paris inactives', $agences === ['dakar' => 1, 'ziguinchor' => 0, 'paris' => 0], json_encode($agences));
        $rattaches = (int) $db->query("SELECT COUNT(*) FROM users u JOIN agencies a ON a.id = u.agency_id WHERE u.role <> 'client' AND a.code = 'dakar'")->fetchColumn();
        $verifier($etape, 'Membres de l’équipe rattachés à Dakar', $rattaches === $equipe, "{$rattaches} sur {$equipe}");
        $repris = $db->query("SELECT request_id, user_id FROM request_assignments WHERE poste = 'conseiller' ORDER BY request_id")->fetchAll(PDO::FETCH_KEY_PAIR);
        $verifier($etape, 'Attributions reprises en affectations « conseiller »', array_map('intval', $repris) === array_map('intval', $attribues), count($repris) . ' sur ' . count($attribues));
        $regles = $db->query("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$base}'
            AND CONSTRAINT_NAME IN ('service_requests_user_fk', 'request_documents_request_fk', 'request_events_request_fk')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $verifier($etape, 'Trois cascades remplacées par RESTRICT', count($regles) === 3 && array_unique(array_values($regles)) === ['RESTRICT'], json_encode($regles));
        $nullable = $db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$base}' AND TABLE_NAME = 'request_assignments' AND COLUMN_NAME = 'user_id'")->fetchColumn();
        $verifier($etape, 'File d’attente : user_id facultatif, colonnes queued_*', $nullable === 'YES' && (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$base}' AND TABLE_NAME = 'request_assignments' AND COLUMN_NAME IN ('queued_at', 'queued_by')")->fetchColumn() === 2);
        $verifier($etape, 'Dossiers, pièces, historiques, rendez-vous et messages inchangés', $donnees($tablesDossiers) === $dossiersAvant);
        $verifier($etape, 'Migrations 007 à 010 enregistrées', (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name >= '007'")->fetchColumn() === 4);
    };

    /* ---------- Migration ---------- */
    echo "\nMigration 007-010 :\n";
    [$retour, $sortie] = $migrer();
    $verifier('migration', 'migrate.php se termine sans erreur', $retour === 0, trim(str_replace("\n", ' | ', $sortie)));
    $verifierMigre('migration');

    /* ---------- Retour arrière ---------- */
    echo "\nRetour arrière 010 → 007 :\n";
    foreach (['010_files_affectation', '009_restrict', '008_affectations_journal', '007_postes_agences'] as $fichier) {
        $executerFichier("{$root}/database/rollback/{$fichier}.down.sql");
    }
    $verifier('retour', 'Schéma identique au point de départ (colonnes, clés, index)', $schema() === $schemaAvant);
    $verifier('retour', 'Données des dossiers identiques', $donnees($tablesDossiers) === $dossiersAvant);
    $verifier('retour', 'Comptes identiques', $donnees(['users']) === $usersAvant);
    $verifier('retour', 'Aucune migration 007+ enregistrée', (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name >= '007'")->fetchColumn() === 0);

    /* ---------- Réapplication, puis interruption simulée et reprise ---------- */
    echo "\nRéapplication et reprise après interruption :\n";
    [$retour] = $migrer(['--jusqua=009']);
    $verifier('reprise', 'Réapplication de 007 à 009', $retour === 0);
    $db->exec('ALTER TABLE request_assignments DROP FOREIGN KEY request_assignments_user_fk'); // 1re instruction de 010, puis « coupure »
    [$retour, $sortie] = $migrer();
    $verifier('reprise', 'Relance simple : échec clair sur l’instruction 1, migration non enregistrée',
        $retour !== 0 && str_contains($sortie, 'instruction 1 sur') && str_contains($sortie, '--reprendre=010_files_affectation.sql:1')
        && (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '010_files_affectation.sql'")->fetchColumn() === 0, $retour !== 0 ? 'échec signalé' : 'pas d’échec !');
    [$retour, $sortie] = $migrer(['--reprendre=010_files_affectation.sql:2']);
    $verifier('reprise', 'Reprise avec --reprendre=…:2', $retour === 0 && str_contains($sortie, 'Appliquée : 010_files_affectation.sql'), trim(str_replace("\n", ' | ', $sortie)));
    $verifierMigre('reprise');

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
