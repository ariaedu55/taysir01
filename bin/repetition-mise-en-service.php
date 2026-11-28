<?php

/*
 * Répétition de la mise en service : migrations 011 à 026 sur une COPIE (docs/gestion-phase-9.md, lot 9C).
 *
 * 1. Crée une base au nom unique `taysir_s<aléa>_recette` (refus si elle existe ; jamais d'autre base touchée).
 * 2. Point de départ :
 *    - --depuis=sauvegarde.sql : la sauvegarde mysqldump de la production, chargée avec le client mysql. Le
 *      chargement réussi prouve au passage que la sauvegarde se restaure ;
 *    - sinon l'état de production actuel (001 à 010) avec des données FICTIVES : comptes, dossiers avec pièces,
 *      rendez-vous (dont un « termine »), messages de contact.
 * 3. Applique 011 à 026 et vérifie : 16 migrations enregistrées, aucune colonne d'origine modifiée (seule
 *    transformation attendue : rendez-vous « termine » → « honore », 012), personnes reprises pour chaque compte
 *    client, dossier et rendez-vous.
 * 4. Retour arrière de 026 à 011 (database/rollback/) : schéma et données identiques au point de départ.
 * 5. Réapplication complète : 025 enregistrée, mêmes contrôles.
 * 6. Supprime la copie (sauf --garder).
 *
 * Usage : php bin/repetition-mise-en-service.php [--depuis=sauvegarde.sql] [--mysql=chemin/mysql]
 *         [--sql-user=… --sql-password=…] [--garder]
 * Port d'une instance séparée : variable TAYSIR_DB_PORT (comme le robot).
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
$port = getenv('TAYSIR_DB_PORT');
$config['port'] = is_string($port) && ctype_digit($port) ? (int) $port : $config['port'];
$sqlUser = $options['sql-user'] ?? (getenv('TAYSIR_DB_USER') ?: $config['user']);
$sqlPassword = $options['sql-password'] ?? (getenv('TAYSIR_DB_PASSWORD') !== false ? getenv('TAYSIR_DB_PASSWORD') : $config['password']);
$restauration = is_string($options['depuis'] ?? null);
if (!in_array($config['host'], ['localhost', '127.0.0.1'], true) || !is_string($sqlUser) || !is_string($sqlPassword)) {
    fwrite(STDERR, "La répétition exige des paramètres SQL locaux valides.\n");
    exit(2);
}
$config['host'] = '127.0.0.1';
$base = 'taysir_s' . bin2hex(random_bytes(4)) . '_recette';
$migrations = ['011_personnes', '012_agenda', '013_reglages_agenda', '014_notes_modeles', '015_taches', '016_rappels_agenda',
    '017_pieces_attendues', '018_suivi_dossiers', '019_acces_tuteurs', '020_accueil', '021_finance', '022_prospection',
    '023_visites_coordonnees', '024_messages_conformite', '025_depots_multiples', '026_demarches'];

$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
$pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$server = new PDO($dsn, $sqlUser, $sqlPassword, $pdoOptions);
$version = (string) $server->query('SELECT VERSION()')->fetchColumn();
$exiger = $options['exiger'] ?? ($restauration ? '10.11' : null);
if ($exiger !== null && (!is_string($exiger) || !preg_match('/^\d+\.\d+$/D', $exiger) || !str_starts_with($version, $exiger . '.'))) {
    fwrite(STDERR, "Le moteur SQL ne correspond pas à la version requise.\n");
    exit(2);
}
if ($restauration) {
    $limite = ' ON `taysir\\_%\\_recette`.* TO ';
    $droits = $server->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
    $droitRecette = false;
    foreach ($droits as $droit) {
        // Certaines installations MariaDB Windows accordent aussi les schémas d'essai intégrés à tous les comptes locaux.
        $schemaEssai = str_contains($droit, ' ON `test`.* TO ') || str_contains($droit, ' ON `test\\_%`.* TO ');
        if (str_contains($droit, 'WITH GRANT OPTION')) {
            $droitRecette = false;
            break;
        }
        if (str_contains($droit, $limite)) {
            $droitRecette = true;
        } elseif (!$schemaEssai && !str_starts_with($droit, 'GRANT USAGE ON *.* TO ')) {
            $droitRecette = false;
            break;
        }
    }
    if (!$droitRecette || !str_starts_with($version, '10.11.') || !str_contains($version, 'MariaDB')
        || !is_string($port) || in_array((int) $port, [3306, 3307], true)) {
        fwrite(STDERR, "Restaurez la copie sur MariaDB 10.11 isolé, avec un compte dédié limité aux bases taysir_%_recette.\n");
        exit(2);
    }
}
$existe = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$existe->execute([$base]);
if ((int) $existe->fetchColumn() > 0) {
    fwrite(STDERR, "La base {$base} existe déjà : rien n'est touché. Relancez.\n");
    exit(1);
}
$server->exec("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo 'Moteur : ' . $version . "\n";

$php = require __DIR__ . '/php-enfant.php';
$env = array_merge(getenv(), ['TAYSIR_DB' => $base, 'TAYSIR_DB_USER' => $sqlUser, 'TAYSIR_DB_PASSWORD' => $sqlPassword]);
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
            // Les garde-fous sont des SELECT (erreur 1242 si une ligne bloque) : résultat lu en entier
            $resultat = $db->query($instruction);
            if ($resultat->columnCount() > 0) {
                $resultat->fetchAll();
            }
            $resultat->closeCursor();
        }
    };

    /* ---------- Point de départ ---------- */
    if (is_string($options['depuis'] ?? null)) {
        $sauvegarde = $options['depuis'];
        if (!is_file($sauvegarde)) {
            throw new RuntimeException("Sauvegarde introuvable : {$sauvegarde}");
        }
        $mysql = is_string($options['mysql'] ?? null) ? $options['mysql'] : (glob('C:/wamp64/bin/mysql/mysql*/bin/mysql.exe')[0] ?? 'mysql');
        // Fichier d'options exclusif : le client mysql n'y lit QUE ce compte, jamais celui d'un ~/.my.cnf
        $fichierOptions = tempnam(sys_get_temp_dir(), 'rms');
        if ($fichierOptions === false) {
            throw new RuntimeException('Fichier temporaire de connexion indisponible.');
        }
        try {
            chmod($fichierOptions, 0600);
            $optionsMysql = sprintf("[client]\nuser=\"%s\"\npassword=\"%s\"\nhost=%s\nport=%d\n",
                addcslashes($sqlUser, '"\\'), addcslashes($sqlPassword, '"\\'), $config['host'], $config['port']);
            if (file_put_contents($fichierOptions, $optionsMysql) === false) {
                throw new RuntimeException('Écriture du fichier de connexion impossible.');
            }
            $nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $chargement = proc_open([$mysql, '--defaults-file=' . $fichierOptions, '--binary-mode', '--local-infile=0', $base],
                [0 => ['file', $sauvegarde, 'r'], 1 => ['file', $nul, 'w'], 2 => ['file', $nul, 'w']], $pipes, $root);
            $statut = is_resource($chargement) ? proc_close($chargement) : 1;
        } finally {
            @unlink($fichierOptions);
        }
        $verifier('sauvegarde', 'La sauvegarde se restaure dans une base vide', $statut === 0);
        if ($statut !== 0) {
            throw new RuntimeException('Restauration impossible : arrêt.');
        }
    } else {
        [$retour, $sortie] = $migrer(['--jusqua=010']);
        if ($retour !== 0) {
            throw new RuntimeException("Migrations 001-010 en échec :\n{$sortie}");
        }
        $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $u = $db->prepare("INSERT INTO users (first_name, last_name, email, profile, role, agency_id, password_hash, consent_at) VALUES (?, ?, ?, 'particulier', ?, ?, ?, NOW())");
        $ids = [];
        foreach ([['admin', 'admin', 1], ['conseil', 'conseiller', 1], ['client1', 'client', null], ['client2', 'client', null]] as [$cle, $role, $agence]) {
            $u->execute(['Copie', ucfirst($cle), $cle . '@copie.test', $role, $agence, $hash]);
            $ids[$cle] = (int) $db->lastInsertId();
        }
        $r = $db->prepare("INSERT INTO service_requests (reference, user_id, service, status, first_name, last_name, email, phone) VALUES (?, ?, 'visa', ?, 'Client', 'Copie', ?, '77 000 00 00')");
        foreach ([['TAY-CPY-1', 'client1', 'en-cours'], ['TAY-CPY-2', 'client2', 'nouvelle'], ['TAY-CPY-3', 'client1', 'terminee']] as [$ref, $client, $statut]) {
            $r->execute([$ref, $ids[$client], $statut, $client . '@copie.test']);
            $id = (int) $db->lastInsertId();
            $db->prepare("INSERT INTO request_documents (request_id, original_name, stored_name, mime, size) VALUES (?, 'piece.pdf', ?, 'application/pdf', 10)")->execute([$id, bin2hex(random_bytes(32)) . '.pdf']);
            $db->prepare("INSERT INTO request_events (request_id, user_id, type, status, message) VALUES (?, ?, 'creation', 'nouvelle', 'Demande envoyée.')")->execute([$id, $ids[$client]]);
        }
        $a = $db->prepare("INSERT INTO appointments (reference, user_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, status) VALUES (?, ?, 'renseignement', 'telephone', ?, 'matin', 'Visiteur', 'Copie', ?, '77 000 00 01', ?)");
        $a->execute(['RDV-CPY-1', null, gmdate('Y-m-d', time() + 86400 * 5), 'visiteur@copie.test', 'demande']);
        $a->execute(['RDV-CPY-2', $ids['client1'], gmdate('Y-m-d', time() - 86400 * 5), 'client1@copie.test', 'termine']);
        $db->exec("INSERT INTO contact_messages (name, email, phone, message, status) VALUES ('Visiteur Copie', 'contact@copie.test', '77 000 00 02', 'Message fictif', 'nouveau')");
        echo "Copie {$base} : état de production actuel (001-010) et données fictives.\n";
    }
    $appliquees = $db->query('SELECT name FROM migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    $derniere = end($appliquees) ?: 'aucune';
    if (array_filter($appliquees, static fn (string $n): bool => strcmp($n, '011') >= 0) !== [] || !in_array('010_files_affectation.sql', $appliquees, true)) {
        throw new RuntimeException("Le point de départ doit s'arrêter exactement à 010 (dernière : {$derniere}) : répétition sans objet.");
    }
    $verifier('depart', 'Point de départ : migrations 001 à 010, rien au-delà', true, $derniere);

    /* ---------- Empreintes : schéma, puis données des colonnes d'origine ---------- */
    $schema = static function () use ($db, $base): string {
        $empreinte = [];
        foreach ([
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'migrations' ORDER BY TABLE_NAME, COLUMN_NAME",
            'SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? ORDER BY TABLE_NAME, CONSTRAINT_NAME',
            "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'migrations' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
        ] as $sql) {
            $s = $db->prepare($sql);
            $s->execute([$base]);
            $empreinte[] = $s->fetchAll(PDO::FETCH_NUM);
        }

        return md5(json_encode($empreinte));
    };
    $colonnes = [];
    $s = $db->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'migrations' ORDER BY TABLE_NAME, ORDINAL_POSITION");
    $s->execute([$base]);
    foreach ($s->fetchAll() as $c) {
        $colonnes[$c['TABLE_NAME']][] = $c['COLUMN_NAME'];
    }
    // Lignes de chaque table d'origine, colonnes d'origine seulement, triées : comparaison exacte avant et après
    $lignes = static function (string $table) use ($db, $colonnes): array {
        $liste = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $colonnes[$table]));
        $rows = array_map('json_encode', $db->query("SELECT {$liste} FROM `{$table}`")->fetchAll(PDO::FETCH_NUM));
        sort($rows);

        return $rows;
    };
    $photo = static function () use ($colonnes, $lignes): array {
        $p = [];
        foreach (array_keys($colonnes) as $table) {
            $p[$table] = $lignes($table);
        }

        return $p;
    };
    $avant = $photo();
    $schemaAvant = $schema();
    echo "\nTables d'origine (lignes) : " . implode(', ', array_map(static fn (string $t, array $r): string => "{$t} " . count($r), array_keys($avant), $avant)) . "\n";
    sleep(1); // une date ON UPDATE modifiée par erreur se verrait à coup sûr

    $ecarts = static function (array $reference, array $actuel, array $tolerees = []): array {
        $diff = [];
        foreach ($reference as $table => $rows) {
            if (in_array($table, $tolerees, true)) {
                continue;
            }
            if (($actuel[$table] ?? null) !== $rows) {
                $diff[] = $table;
            }
        }

        return $diff;
    };
    $verifierMigre = static function (string $etape) use ($db, $verifier, $photo, $avant, $ecarts, $colonnes, $migrations): void {
        $nb = (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name >= '011' AND name < '027'")->fetchColumn();
        $verifier($etape, 'Migrations 011 à 026 enregistrées', $nb === count($migrations), $nb . ' sur ' . count($migrations));
        $apres = $photo();
        $diff = $ecarts($avant, $apres, ['appointments']);
        $verifier($etape, 'Colonnes d’origine inchangées dans toutes les tables (hors rendez-vous)', $diff === [], $diff === [] ? count($avant) . ' tables' : implode(', ', $diff));
        // Rendez-vous : seule transformation permise, « termine » devient « honore » (012)
        $statut = array_search('status', $colonnes['appointments'], true);
        $normaliser = static fn (array $rows): array => array_map(static function (string $json) use ($statut): string {
            $r = json_decode($json, true);
            $r[$statut] = $r[$statut] === 'termine' ? 'honore' : $r[$statut];

            return json_encode($r);
        }, $rows);
        $a = $normaliser($avant['appointments']);
        $b = $normaliser($apres['appointments']);
        sort($a);
        sort($b);
        $verifier($etape, 'Rendez-vous inchangés, sauf « termine » devenu « honore »', $a === $b, count($b) . ' rendez-vous');
        $sansPersonne = [];
        foreach (["SELECT COUNT(*) FROM users WHERE role = 'client' AND person_id IS NULL" => 'comptes client', 'SELECT COUNT(*) FROM service_requests WHERE person_id IS NULL' => 'dossiers',
            'SELECT COUNT(*) FROM appointments WHERE person_id IS NULL' => 'rendez-vous'] as $sql => $libelle) {
            if (($n = (int) $db->query($sql)->fetchColumn()) > 0) {
                $sansPersonne[] = "{$n} {$libelle}";
            }
        }
        $verifier($etape, 'Chaque compte client, dossier et rendez-vous est rattaché à une personne', $sansPersonne === [], $sansPersonne === [] ? (string) $db->query('SELECT COUNT(*) FROM people')->fetchColumn() . ' personne(s)' : implode(', ', $sansPersonne));
        $verifier($etape, 'Rendez-vous : agence pour chacun, heure UTC pour chaque heure fixée (pas pour une simple demande)',
            (int) $db->query('SELECT COUNT(*) FROM appointments WHERE agency_id IS NULL OR (scheduled_at IS NOT NULL AND starts_at_utc IS NULL)')->fetchColumn() === 0);
        $verifier($etape, 'Quatre modèles de message de départ', (int) $db->query('SELECT COUNT(*) FROM message_templates')->fetchColumn() === 4);
    };

    /* ---------- Migration ---------- */
    echo "\nMigration 011-026 :\n";
    [$retour, $sortie] = $migrer(['--jusqua=026']);
    $verifier('migration', 'migrate.php se termine sans erreur', $retour === 0, trim((string) preg_replace('/\s+/', ' ', substr($sortie, -300))));
    $verifierMigre('migration');

    /* ---------- Retour arrière ---------- */
    echo "\nRetour arrière 026 → 011 :\n";
    foreach (array_reverse($migrations) as $fichier) {
        $executerFichier("{$root}/database/rollback/{$fichier}.down.sql");
    }
    $diff = $ecarts($avant, $photo());
    $verifier('retour', 'Schéma identique au point de départ (colonnes, clés, index)', $schema() === $schemaAvant);
    $verifier('retour', 'Données identiques au point de départ, rendez-vous compris', $diff === [], $diff === [] ? count($avant) . ' tables' : implode(', ', $diff));
    $verifier('retour', 'Aucune migration 011+ enregistrée', (int) $db->query("SELECT COUNT(*) FROM migrations WHERE name >= '011'")->fetchColumn() === 0);

    /* ---------- Réapplication ---------- */
    echo "\nRéapplication complète :\n";
    [$retour, $sortie] = $migrer(['--jusqua=026']);
    $verifier('reprise', 'migrate.php se termine sans erreur', $retour === 0, trim((string) preg_replace('/\s+/', ' ', substr($sortie, -200))));
    $verifierMigre('reprise');
    [$retour, $sortie] = $migrer(['--jusqua=026']);
    $verifier('reprise', 'Relance à vide : rien à appliquer, aucune erreur', $retour === 0 && !str_contains($sortie, 'Appliquée'), trim((string) preg_replace('/\s+/', ' ', $sortie)));

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
