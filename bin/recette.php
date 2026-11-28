<?php

/*
 * Robot de recette de l'espace équipe (docs/gestion-phase-1.md, 1B et 1B bis).
 *
 * ISOLATION (revue 1B, point 4) :
 * - base UNIQUE par exécution, `taysir_r<aléa>_recette`, créée puis supprimée par le robot lui-même ; il refuse de
 *   démarrer si ce nom existe et ne supprime jamais une base qu'il n'a pas créée ;
 * - pièces, e-mails et sessions dans storage/recette/<base>/ (config/recette.php), supprimés à la fin ;
 * - port libre choisi au lancement ; le serveur doit répondre avec l'en-tête X-Taysir-Recette = <base> ;
 * - compte SQL dédié en option : --sql-user=… --sql-password=… (droits limités aux bases taysir\_%\_recette).
 * Données FICTIVES uniquement ; aucun e-mail ne part.
 *
 * Usage, depuis la racine du projet :
 *   php -n -d extension_dir=… -d extension=pdo_mysql -d extension=mbstring -d extension=openssl
 *       -d extension=fileinfo -d extension=curl -d extension=zip bin/recette.php [--garder] [--sql-user=… --sql-password=…]
 * --garder : conserve la base et le dossier de l'exécution pour inspection (à supprimer ensuite).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
foreach (['pdo_mysql', 'curl', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Extension PHP manquante : {$extension}\n");
        exit(2);
    }
}

define('BASE', 'taysir_r' . bin2hex(random_bytes(4)) . '_recette');
const MOT_DE_PASSE = 'Recette-fictive-2026';

$root = dirname(__DIR__);
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $m)) {
        $options[$m[1]] = $m[2] ?? true;
    }
}
$keep = isset($options['garder']);

/* ---------- 1. Base dédiée, créée par le robot ---------- */
$config = is_file("{$root}/config/database.local.php") ? require "{$root}/config/database.local.php" : require "{$root}/config/database.php";
$sqlUser = is_string($options['sql-user'] ?? null) ? $options['sql-user'] : $config['user'];
$sqlPassword = is_string($options['sql-password'] ?? null) ? $options['sql-password'] : $config['password'];
$portRecette = getenv('TAYSIR_DB_PORT'); // instance de recette séparée (MariaDB 10.11 isolée, par exemple)
$config['port'] = is_string($portRecette) && ctype_digit($portRecette) ? (int) $portRecette : $config['port'];
$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
$server = new PDO($dsn, $sqlUser, $sqlPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$existe = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$existe->execute([BASE]);
if ((int) $existe->fetchColumn() > 0) {
    fwrite(STDERR, 'La base ' . BASE . " existe déjà : le robot n'y touche pas. Relancez.\n");
    exit(1);
}
$server->exec('CREATE DATABASE `' . BASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$creee = true;

$isole = $root . '/storage/recette/' . BASE;
foreach (['uploads', 'sessions', 'mails'] as $dossier) {
    mkdir($isole . '/' . $dossier, 0770, true);
}
// Mêmes réglages et extensions que ce processus, sur n'importe quelle machine (WAMP, VPS)
$php = require __DIR__ . '/php-enfant.php';
$env = array_merge(getenv(), ['TAYSIR_DB' => BASE, 'GESTION_MAQUETTE' => '0', 'AGENDA_RESERVATION_DIRECTE' => '1', 'RDV_LIMITE_IP_HEURE' => '1000', 'RDV_LIMITE_IP_JOUR' => '1000']);
if (isset($options['sql-user']) || isset($options['sql-password'])) {
    $env += ['TAYSIR_DB_USER' => $sqlUser, 'TAYSIR_DB_PASSWORD' => $sqlPassword];
}

$serveur = null;
$resultats = [];
$code = 1;
try {
    $migration = proc_open(array_merge($php, [$root . '/database/migrate.php']), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
    $sortie = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    if (proc_close($migration) !== 0) {
        throw new RuntimeException("Migrations en échec :\n{$sortie}");
    }
    echo 'Base ' . BASE . " créée, migrations appliquées.\n";

    $db = new PDO($dsn . ';dbname=' . BASE, $sqlUser, $sqlPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    /* ---------- 2. Données fictives ---------- */
    $hash = password_hash(MOT_DE_PASSE, PASSWORD_DEFAULT);
    $comptes = [
        'admin' => ['Recette', 'Admin', 'admin'],
        'secretaire' => ['Recette', 'Secretaire', 'secretaire'],
        'prospection' => ['Recette', 'Prospection', 'prospection'],
        'commerciale' => ['Recette', 'Commerciale', 'commerciale'],
        'conseillerA' => ['Conseiller', 'Alpha', 'conseiller'],
        'conseillerB' => ['Conseiller', 'Beta', 'conseiller'],
        'orientation' => ['Orientation', 'Une', 'orientation'],
        'orientation2' => ['Orientation', 'Deux', 'orientation'],
        'traitement' => ['Recette', 'Traitement', 'traitement'],
        'comptable' => ['Recette', 'Comptable', 'comptable'],
        'inactif' => ['Conseiller', 'Parti', 'conseiller'],
        'client1' => ['Client', 'Un', 'client'],
        'client2' => ['Client', 'Deux', 'client'],
    ];
    $ids = [];
    $insert = $db->prepare("INSERT INTO users (first_name, last_name, email, profile, role, agency_id, active, password_hash, consent_at, last_login_at)
        VALUES (?, ?, ?, 'particulier', ?, IF(? = 'client', NULL, 1), ?, ?, NOW(), NOW())");
    foreach ($comptes as $cle => [$prenom, $nom, $role]) {
        $insert->execute([$prenom, $nom, strtolower($cle) . '@recette.test', $role, $role, $cle === 'inactif' ? 0 : 1, $hash]);
        $ids[$cle] = (int) $db->lastInsertId();
    }

    $dossiers = [];
    $creer = static function (string $proprietaire, string $service) use ($db, $ids, $isole): array {
        $db->prepare("INSERT INTO service_requests (reference, user_id, service, first_name, last_name, email, phone, destination, details)
            VALUES (?, ?, ?, 'Client', 'Fictif', 'fictif@recette.test', '77 000 00 00', 'France', ?)")
            ->execute(['TMP-' . bin2hex(random_bytes(4)), $ids[$proprietaire], $service, json_encode(['identity_number' => 'A0000000'])]);
        $id = (int) $db->lastInsertId();
        $reference = sprintf('TAY-RCT-%06d', $id);
        $db->prepare('UPDATE service_requests SET reference = ? WHERE id = ?')->execute([$reference, $id]);
        $stored = bin2hex(random_bytes(32)) . '.pdf';
        $contenu = '%PDF-1.4 piece fictive ' . $reference;
        file_put_contents($isole . '/uploads/' . $stored, $contenu);
        $db->prepare("INSERT INTO request_documents (request_id, original_name, stored_name, mime, size) VALUES (?, 'piece-fictive.pdf', ?, 'application/pdf', ?)")
            ->execute([$id, $stored, strlen($contenu)]);
        $piece = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO request_events (request_id, user_id, type, status, message) VALUES (?, ?, 'creation', 'nouvelle', 'Demande envoyée.')")->execute([$id, $ids[$proprietaire]]);

        return ['id' => $id, 'ref' => $reference, 'piece' => $piece, 'contenu' => $contenu];
    };
    $dossiers['R1'] = $creer('client1', 'visa');       // conseiller A + traitement
    $dossiers['R2'] = $creer('client2', 'admission');  // conseiller B
    $dossiers['R3'] = $creer('client1', 'logement');   // personne (file des conseillers)
    $affecter = $db->prepare('INSERT INTO request_assignments (request_id, poste, user_id, assigned_by) VALUES (?, ?, ?, ?)');
    $affecter->execute([$dossiers['R1']['id'], 'conseiller', $ids['conseillerA'], $ids['admin']]);
    $affecter->execute([$dossiers['R1']['id'], 'traitement', $ids['traitement'], $ids['admin']]);
    $affecter->execute([$dossiers['R2']['id'], 'conseiller', $ids['conseillerB'], $ids['admin']]);
    $db->prepare('UPDATE service_requests SET assigned_to = ? WHERE id = ?')->execute([$ids['conseillerA'], $dossiers['R1']['id']]);
    $db->prepare('UPDATE service_requests SET assigned_to = ? WHERE id = ?')->execute([$ids['conseillerB'], $dossiers['R2']['id']]);
    $db->exec("INSERT INTO appointments (reference, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone)
        VALUES ('RDV-RCT-1', 'renseignement', 'telephone', CURDATE(), 'matin', 'Visiteur', 'Fictif', 'visiteur@recette.test', '77 000 00 01')");
    echo count($comptes) . ' comptes, ' . count($dossiers) . " dossiers et 1 rendez-vous fictifs créés.\n";

    /* ---------- 3. Serveur de recette sur un port libre ---------- */
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    define('PORT', $port);
    $nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $serveur = proc_open(array_merge($php, ['-d', 'session.save_path=' . $isole . '/sessions', '-S', '127.0.0.1:' . PORT, '-t', 'public']),
        [1 => ['file', $nul, 'w'], 2 => ['file', $nul, 'w']], $pipesServeur, $root, $env);
    $pret = false;
    for ($i = 0; $i < 50 && !$pret; $i++) {
        usleep(100000);
        $pret = @fsockopen('127.0.0.1', PORT) !== false;
    }
    if (!$pret) {
        throw new RuntimeException('Le serveur de recette ne démarre pas sur le port ' . PORT . '.');
    }

    /* ---------- 4. Client HTTP ---------- */
    $nav = [];
    $navigateur = static fn (string $nom): Navigateur => new Navigateur($nom, $isole);

    $verifier = static function (string $id, string $scenario, bool $ok, string $obtenu) use (&$resultats): void {
        $resultats[] = [$id, $scenario, $ok, $obtenu];
        echo ($ok ? '  OK    ' : '  ÉCHEC ') . str_pad($id, 8) . $scenario . ' → ' . $obtenu . "\n";
    };
    $chemin = static fn (string $location): string => (string) parse_url($location, PHP_URL_PATH) . (parse_url($location, PHP_URL_QUERY) ? '?' . parse_url($location, PHP_URL_QUERY) : '');
    $journal = static function (string $action, string $ref) use ($db): int {
        $s = $db->prepare('SELECT COUNT(*) FROM audit_log WHERE action = ? AND subject_ref LIKE ?');
        $s->execute([$action, $ref . '%']);

        return (int) $s->fetchColumn();
    };
    $titulaire = static function (array $d, string $poste) use ($db): ?int {
        $s = $db->prepare('SELECT user_id FROM request_assignments WHERE request_id = ? AND poste = ?');
        $s->execute([$d['id'], $poste]);
        $v = $s->fetchColumn();

        return $v === false || $v === null ? null : (int) $v;
    };
    [$R1, $R2, $R3] = [$dossiers['R1'], $dossiers['R2'], $dossiers['R3']];
    $piece = static fn (array $d): string => '/gestion/dossiers/' . $d['ref'] . '/pieces/' . $d['piece'] . '/';
    $fiche = static fn (array $d): string => '/gestion/dossiers/' . $d['ref'] . '/';
    $attribution = static fn (array $d): string => '/gestion/dossiers/' . $d['ref'] . '/attribution/';

    echo "\nScénarios :\n";

    /* R0 : le serveur qui répond est bien celui de cette recette */
    $sonde = $navigateur('sonde')->get('/connexion/');
    $verifier('R0', 'Serveur de recette identifié (X-Taysir-Recette)', $sonde['recette'] === BASE, $sonde['recette'] !== '' ? $sonde['recette'] : 'en-tête absent');
    if ($sonde['recette'] !== BASE) {
        throw new RuntimeException('Le serveur sur le port ' . PORT . ' n’est pas celui de la recette : arrêt.');
    }

    /* R1 : redirection après connexion selon le poste */
    foreach (['admin', 'secretaire', 'prospection', 'commerciale', 'conseillerA', 'conseillerB', 'orientation', 'orientation2', 'traitement', 'comptable', 'client1'] as $cle) {
        $nav[$cle] = $navigateur($cle);
        $r = $nav[$cle]->connexion(strtolower($cle) . '@recette.test');
        $attendu = $cle === 'client1' ? '/mon-espace/' : '/gestion/';
        $verifier('R1', "Connexion {$cle}", $r['code'] === 303 && $chemin($r['location']) === $attendu, $r['code'] . ' ' . $chemin($r['location']));
    }

    /* R2 : chemin de retour, gardé seulement s'il est permis — y compris le MODULE (revue 1B, point 3) */
    $retours = [
        ['client2', '/demande-de-visa/', '/demande-de-visa/', 'Client vers un formulaire client'],
        ['client2', '/gestion/dossiers/', '/mon-espace/', 'Client vers l’espace équipe (refusé)'],
        ['secretaire', '/mon-espace/', '/gestion/', 'Secrétaire vers l’espace client (refusé)'],
        ['conseillerA', '/gestion/equipe/', '/gestion/', 'Conseiller vers Équipe, module interdit (refusé)'],
        ['conseillerA', '/gestion/rendez-vous/', '/gestion/rendez-vous/', 'Conseiller vers son agenda (gardé)'],
        ['admin', '/gestion/equipe/', '/gestion/equipe/', 'Administrateur vers Équipe (gardé)'],
        ['secretaire', '/admin/rendez-vous/', '/admin/rendez-vous/', 'Secrétaire vers l’ancienne adresse des rendez-vous (gardé)'],
    ];
    foreach ($retours as $i => [$cle, $retour, $attendu, $libelle]) {
        $r = $navigateur($cle . '-retour' . $i)->connexion(strtolower($cle) . '@recette.test', $retour);
        $verifier('R2', $libelle, $chemin($r['location']) === $attendu, $chemin($r['location']));
    }

    /* R3 : un client ne passe jamais dans l'espace équipe */
    foreach (['/gestion/', '/gestion/dossiers/', $fiche($R1), $piece($R1)] as $path) {
        $r = $nav['client1']->get($path);
        $verifier('R3', "Client sur {$path}", $r['code'] === 403, (string) $r['code']);
    }

    /* T7 : le conseiller A face au dossier du conseiller B */
    $avant = $journal('acces_refuse', $R2['ref']);
    $r = $nav['conseillerA']->get($fiche($R2));
    $verifier('T7', 'Conseiller A ouvre la fiche de B', $r['code'] === 403, (string) $r['code']);
    $r = $nav['conseillerA']->get($piece($R2));
    $verifier('T7', 'Conseiller A télécharge la pièce de B', $r['code'] === 403, (string) $r['code']);
    $liste = $nav['conseillerA']->get('/gestion/dossiers/');
    $verifier('T7', 'Dossier de B absent de la liste de A', $liste['code'] === 200 && !str_contains($liste['body'], $R2['ref']) && str_contains($liste['body'], $R1['ref']), $liste['code'] . (str_contains($liste['body'], $R2['ref']) ? ' (présent !)' : ' (absent)'));
    $journee = $nav['conseillerA']->get('/gestion/');
    // Recherche côté serveur depuis la 2A (plus d'index embarqué dans la page)
    $trouve = static fn (string $q): int => count(json_decode($nav['conseillerA']->get('/gestion/recherche/?format=json&q=' . rawurlencode($q))['body'], true)['resultats'] ?? []);
    $verifier('T7', 'Dossier de B absent de la recherche de A', $trouve($R2['ref']) === 0 && $trouve($R1['ref']) === 1, $trouve($R2['ref']) . ' résultat(s) pour B, ' . $trouve($R1['ref']) . ' pour A');
    preg_match('#href="/gestion/dossiers/"[^>]*>.*?<span class="g-count"[^>]*>(\d+)</span>#s', $journee['body'], $m);
    $verifier('T7', 'Compteur de A = son dossier + la file des conseillers (2)', ($m[1] ?? '') === '2', 'compteur ' . ($m[1] ?? 'absent'));
    $apres = $journal('acces_refuse', $R2['ref']);
    $verifier('T7', 'Deux refus inscrits au journal', $apres - $avant === 2, ($apres - $avant) . ' ligne(s)');

    /* R4 : le conseiller A télécharge la pièce de SON dossier */
    $avant = $journal('piece_telechargee', $R1['ref']);
    $r = $nav['conseillerA']->get($piece($R1));
    $verifier('R4', 'Conseiller A ouvre sa pièce', $r['code'] === 200 && $r['body'] === $R1['contenu'], $r['code'] . ' (' . strlen($r['body']) . ' octets)');
    $verifier('R4', 'Téléchargement inscrit au journal', $journal('piece_telechargee', $R1['ref']) - $avant === 1, ($journal('piece_telechargee', $R1['ref']) - $avant) . ' ligne(s)');

    /* R5 : secrétaire et comptable voient l'en-tête, pas les pièces ni l'identité */
    foreach (['secretaire', 'comptable'] as $cle) {
        $l = $nav[$cle]->get('/gestion/dossiers/');
        $verifier('R5', ucfirst($cle) . ' voit les 3 dossiers', $l['code'] === 200 && str_contains($l['body'], $R1['ref']) && str_contains($l['body'], $R2['ref']) && str_contains($l['body'], $R3['ref']), (string) $l['code']);
        $f = $nav[$cle]->get($fiche($R1));
        $verifier('R5', ucfirst($cle) . ' ouvre l’en-tête sans l’identité', $f['code'] === 200 && !str_contains($f['body'], 'A0000000'), $f['code'] . (str_contains($f['body'], 'A0000000') ? ' (numéro de passeport visible !)' : ' (identité masquée)'));
        $p = $nav[$cle]->get($piece($R1));
        $verifier('R5', ucfirst($cle) . ' télécharge une pièce', $p['code'] === 403, (string) $p['code']);
    }

    /* R6 : prospection et commerciale n'ont pas de dossiers en 1B */
    foreach (['prospection', 'commerciale'] as $cle) {
        $j = $nav[$cle]->get('/gestion/');
        $d = $nav[$cle]->get('/gestion/dossiers/');
        $verifier('R6', ucfirst($cle) . ' : Ma journée 200, Dossiers 403', $j['code'] === 200 && $d['code'] === 403, $j['code'] . ' / ' . $d['code']);
    }

    /* R7 : agent de traitement — un poste vacant ne suffit plus (revue 1B, point 2) */
    $f = $nav['traitement']->get($fiche($R1));
    $p = $nav['traitement']->get($piece($R1));
    $verifier('R7', 'Traitement affecté : fiche et pièce de R1', $f['code'] === 200 && $p['code'] === 200, $f['code'] . ' / ' . $p['code']);
    $f = $nav['traitement']->get($fiche($R2));
    $verifier('R7', 'Traitement face à R2 jamais transmis : invisible', $f['code'] === 403, (string) $f['code']);
    $o = $nav['orientation']->get($fiche($R3));
    $verifier('R7', 'Orientation face à R3 jamais transmis : invisible', $o['code'] === 403, (string) $o['code']);

    /* R8 : transition /admin/ → /gestion/ */
    $r = $nav['admin']->get('/admin/demandes/' . $R1['ref'] . '/');
    $verifier('R8', 'GET /admin/demandes/{réf}/', $r['code'] === 302 && $chemin($r['location']) === $fiche($R1), $r['code'] . ' ' . $chemin($r['location']));
    $r = $nav['admin']->get('/admin/demandes/?statut=nouvelle');
    $verifier('R8', 'GET /admin/demandes/?statut=nouvelle (filtre gardé)', $r['code'] === 302 && $chemin($r['location']) === '/gestion/dossiers/?statut=nouvelle', $r['code'] . ' ' . $chemin($r['location']));
    $r = $nav['admin']->get('/admin/demandes/' . $R1['ref'] . '/documents/' . $R1['piece'] . '/');
    $verifier('R8', 'GET ancienne adresse de pièce', $r['code'] === 302 && $chemin($r['location']) === $piece($R1), $r['code'] . ' ' . $chemin($r['location']));
    $r = $nav['admin']->post('/admin/demandes/' . $R1['ref'] . '/', ['status' => 'en-cours', 'note' => 'Note de recette', 'visible' => '1']);
    $statut = $db->query('SELECT status FROM service_requests WHERE id = ' . $R1['id'])->fetchColumn();
    $verifier('R8', 'POST de compatibilité avec jeton', $r['code'] === 303 && $statut === 'en-cours', $r['code'] . ', statut ' . $statut);
    $r = $nav['admin']->post('/admin/demandes/' . $R1['ref'] . '/', ['status' => 'terminee'], false);
    $verifier('R8', 'POST de compatibilité sans jeton', $r['code'] === 419, (string) $r['code']);

    /* R9 : modules réservés à l'administrateur */
    foreach (['/gestion/e-mails/', '/gestion/equipe/', '/gestion/journal/'] as $path) {
        $a = $nav['conseillerA']->get($path);
        $b = $nav['admin']->get($path);
        $verifier('R9', "{$path} : conseiller 403, admin 200", $a['code'] === 403 && $b['code'] === 200, $a['code'] . ' / ' . $b['code']);
    }
    foreach (['/gestion/', '/gestion/dossiers/', '/gestion/rendez-vous/', '/gestion/messages/', $fiche($R3)] as $path) {
        $b = $nav['admin']->get($path);
        $verifier('R9', "Admin affiche {$path}", $b['code'] === 200 && !str_contains($b['body'], 'Fatal error') && !str_contains($b['body'], 'Warning:'), (string) $b['code']);
    }

    /* R10 : la base refuse la suppression d'un compte qui a des dossiers */
    try {
        $db->exec('DELETE FROM users WHERE id = ' . $ids['client1']);
        $verifier('R10', 'Suppression d’un client avec dossiers', false, 'supprimé !');
    } catch (PDOException $exception) {
        $verifier('R10', 'Suppression d’un client avec dossiers', ($exception->errorInfo[1] ?? 0) === 1451, 'refusée (' . ($exception->errorInfo[1] ?? '?') . ')');
    }

    /* R11 : compte désactivé */
    $r = $navigateur('inactif')->connexion('inactif@recette.test');
    $verifier('R11', 'Connexion d’un compte désactivé', in_array($r['code'], [302, 303], true) && $chemin($r['location']) === '/connexion/', $r['code'] . ' ' . $chemin($r['location']));

    /* R12 : prendre un dossier de la file, puis le périmètre se referme pour les autres */
    $r = $nav['conseillerA']->post($attribution($R3), ['poste' => 'conseiller', 'operation' => 'prendre']);
    $p = $nav['conseillerA']->get($piece($R3));
    $verifier('R12', 'Conseiller A prend R3 et ouvre sa pièce', $r['code'] === 303 && $p['code'] === 200, $r['code'] . ' / ' . $p['code']);
    $f = $nav['conseillerB']->get($fiche($R3));
    $verifier('R12', 'Conseiller B ne voit plus R3', $f['code'] === 403, (string) $f['code']);
    $verifier('R12', 'Prise inscrite au journal', $journal('affectation', $R3['ref']) === 1, $journal('affectation', $R3['ref']) . ' ligne(s)');

    /* R13 : la matrice de transmission */
    $r = $nav['conseillerA']->post($attribution($R1), ['poste' => 'orientation', 'operation' => 'transmettre', 'assigned_to' => (string) $ids['orientation']]);
    $verifier('R13', 'Conseiller → une personne de l’orientation (permis)', $r['code'] === 303 && $titulaire($R1, 'orientation') === $ids['orientation'], $r['code'] . ', titulaire ' . $titulaire($R1, 'orientation'));
    $r = $nav['secretaire']->post($attribution($R1), ['poste' => 'traitement', 'operation' => 'transmettre', 'assigned_to' => (string) $ids['traitement']]);
    $verifier('R13', 'Secrétaire → traitement (refusé)', $r['code'] === 403, (string) $r['code']);
    $r = $nav['conseillerB']->post($fiche($R1), ['status' => 'terminee', 'note' => 'x']);
    $verifier('R13', 'Conseiller B modifie le dossier de A (refusé)', $r['code'] === 403, (string) $r['code']);
    $r = $nav['conseillerA']->post($attribution($R1), ['poste' => 'orientation', 'operation' => 'transmettre', 'assigned_to' => (string) $ids['orientation2']]);
    $verifier('R13', 'Conseiller réaffecte une orientation déjà tenue (refusé)', $r['code'] === 403 && $titulaire($R1, 'orientation') === $ids['orientation'], $r['code'] . ', titulaire inchangé');
    $r = $nav['admin']->post($attribution($R1), ['poste' => 'orientation', 'operation' => 'transmettre', 'assigned_to' => (string) $ids['orientation2']]);
    $verifier('R13', 'Administrateur réaffecte l’orientation (permis)', $r['code'] === 303 && $titulaire($R1, 'orientation') === $ids['orientation2'], $r['code'] . ', titulaire ' . $titulaire($R1, 'orientation'));

    /* R14 : file du poste suivant et prise simultanée (revue 1B, point 2) */
    $r = $nav['conseillerA']->post($attribution($R3), ['poste' => 'orientation', 'operation' => 'transmettre', 'assigned_to' => '']);
    $verifier('R14', 'Conseiller A met R3 dans la file de l’orientation', $r['code'] === 303 && $titulaire($R3, 'orientation') === null && (int) $db->query("SELECT COUNT(*) FROM request_assignments WHERE request_id = {$R3['id']} AND poste = 'orientation'")->fetchColumn() === 1, (string) $r['code']);
    $l1 = $nav['orientation']->get('/gestion/dossiers/?vue=a-prendre');
    $l2 = $nav['orientation2']->get('/gestion/dossiers/?vue=a-prendre');
    $verifier('R14', 'R3 apparaît dans la file des deux agents d’orientation', str_contains($l1['body'], $R3['ref']) && str_contains($l2['body'], $R3['ref']), 'liste « À prendre »');
    $p = $nav['orientation']->get($piece($R3));
    $verifier('R14', 'Pièce fermée tant que le dossier n’est pas pris', $p['code'] === 403, (string) $p['code']);
    $simultane = Navigateur::simultane([
        [$nav['orientation'], $attribution($R3), ['poste' => 'orientation', 'operation' => 'prendre']],
        [$nav['orientation2'], $attribution($R3), ['poste' => 'orientation', 'operation' => 'prendre']],
    ]);
    $gagnants = array_values(array_filter([0, 1], static fn (int $i): bool => $chemin($simultane[$i]['location']) === $fiche($R3)));
    $perdants = array_values(array_filter([0, 1], static fn (int $i): bool => $chemin($simultane[$i]['location']) === '/gestion/dossiers/?vue=a-prendre'));
    $attendu = count($gagnants) === 1 ? [$ids['orientation'], $ids['orientation2']][$gagnants[0]] : -1;
    $verifier('R14', 'Prise simultanée : une seule réussit, l’autre est renvoyée à la file', count($gagnants) === 1 && count($perdants) === 1, count($gagnants) . ' réussite(s), ' . count($perdants) . ' renvoi(s)');
    $verifier('R14', 'L’affectation du gagnant n’est pas écrasée', $titulaire($R3, 'orientation') === $attendu, 'titulaire ' . $titulaire($R3, 'orientation'));
    $gagnant = $gagnants !== [] ? [$nav['orientation'], $nav['orientation2']][$gagnants[0]] : $nav['orientation'];
    $p = $gagnant->get($piece($R3));
    $verifier('R14', 'Le gagnant ouvre la pièce', $p['code'] === 200, (string) $p['code']);
    $t = $nav['traitement']->get($fiche($R3));
    $r = $gagnant->post($attribution($R3), ['poste' => 'traitement', 'operation' => 'transmettre', 'assigned_to' => '']);
    $t2 = $nav['traitement']->get($fiche($R3));
    $verifier('R14', 'Traitement : invisible avant transmission, visible après', $t['code'] === 403 && $r['code'] === 303 && $t2['code'] === 200, $t['code'] . ' puis ' . $t2['code']);

    /* R15 : les agents ont leur agenda, mais les demandes non affectées restent à l’accueil. */
    foreach (['conseillerA', 'conseillerB', 'orientation'] as $cle) {
        $g = $nav[$cle]->get('/gestion/rendez-vous/');
        $p1 = $nav[$cle]->post('/gestion/rendez-vous/RDV-RCT-1/', ['status' => 'annule']);
        $p2 = $nav[$cle]->post('/admin/rendez-vous/RDV-RCT-1/', ['status' => 'annule']);
        $j = $nav[$cle]->get('/gestion/');
        $verifier('R15', ucfirst($cle) . ' : une demande non attribuée reste hors de son agenda', $g['code'] === 200 && !str_contains($g['body'], 'RDV-RCT-1') && $p1['code'] === 403 && $p2['code'] === 403 && !str_contains($j['body'], 'RDV-RCT-1'), $g['code'] . ' / ' . $p1['code'] . ' / ' . $p2['code']);
    }
    $statutRdv = $db->query("SELECT status FROM appointments WHERE reference = 'RDV-RCT-1'")->fetchColumn();
    $verifier('R15', 'Le rendez-vous n’a pas été modifié', $statutRdv === 'demande', $statutRdv);
    $s = $nav['secretaire']->get('/gestion/rendez-vous/');
    $verifier('R15', 'La secrétaire voit les rendez-vous', $s['code'] === 200 && str_contains($s['body'], 'RDV-RCT-1'), (string) $s['code']);

    /* R16 : désactivation — les dossiers en cours retournent dans la file */
    $r = $nav['admin']->post('/gestion/equipe/' . $ids['conseillerB'] . '/', ['action' => 'deactivate']);
    $verifier('R16', 'Désactivation du conseiller B : R2 retourne dans la file', $r['code'] === 303 && $titulaire($R2, 'conseiller') === null, $r['code'] . ', titulaire ' . var_export($titulaire($R2, 'conseiller'), true));
    $f = $nav['conseillerA']->get($fiche($R2));
    $verifier('R16', 'Le conseiller A voit R2 dans sa file', $f['code'] === 200, (string) $f['code']);
    $detail = (string) $db->query("SELECT detail FROM audit_log WHERE action = 'compte_desactive' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $verifier('R16', 'Journal : dossiers remis comptés', str_contains($detail, '"dossiers_remis": 1') || str_contains($detail, '"dossiers_remis":1'), $detail);

    /* R17 : mission nominative attribuée, puis retirée */
    $r = $nav['admin']->post('/gestion/equipe/' . $ids['conseillerA'] . '/', ['action' => 'mission', 'mission' => 'traitement']);
    $r2 = $nav['admin']->post($attribution($R2), ['poste' => 'traitement', 'operation' => 'transmettre', 'assigned_to' => (string) $ids['conseillerA']]);
    $verifier('R17', 'Mission « traitement » : le conseiller A reçoit un dossier en traitement', $r['code'] === 303 && $r2['code'] === 303 && $titulaire($R2, 'traitement') === $ids['conseillerA'], $r['code'] . ' / ' . $r2['code']);
    $r = $nav['admin']->post('/gestion/equipe/' . $ids['conseillerA'] . '/', ['action' => 'mission', 'mission' => 'traitement']);
    $verifier('R17', 'Mission retirée : le dossier retourne dans la file du traitement', $r['code'] === 303 && $titulaire($R2, 'traitement') === null, $r['code'] . ', titulaire ' . var_export($titulaire($R2, 'traitement'), true));
    $verifier('R17', 'Attribution et retrait inscrits au journal', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action IN ('mission_attribuee', 'mission_retiree')")->fetchColumn() === 2, 'mission');


    /* ---------- Phase 2, étape 2A : personnes (docs/gestion-phase-2.md, K4, K5) ---------- */
    $personne = static function (string $prenom, string $nom, ?string $email, ?int $createur = null) use ($db): int {
        $db->prepare("INSERT INTO people (first_name, last_name, email, phone, origin, created_by) VALUES (?, ?, ?, '77 111 22 33', 'site', ?)")
            ->execute([$prenom, $nom, $email, $createur]);

        return (int) $db->lastInsertId();
    };
    $dossierDe = static function (int $personId, array $affectations) use ($db, $ids): string {
        $db->prepare("INSERT INTO service_requests (reference, user_id, person_id, service, first_name, last_name, email, phone) VALUES (?, NULL, ?, 'visa', 'Personne', 'Fictive', 'p@recette.test', '77 000 00 09')")
            ->execute(['TMP-' . bin2hex(random_bytes(4)), $personId]);
        $id = (int) $db->lastInsertId();
        $reference = sprintf('TAY-PRS-%06d', $id);
        $db->prepare('UPDATE service_requests SET reference = ? WHERE id = ?')->execute([$reference, $id]);
        foreach ($affectations as $poste => $cle) {
            $db->prepare('INSERT INTO request_assignments (request_id, poste, user_id, assigned_by) VALUES (?, ?, ?, ?)')
                ->execute([$id, $poste, $cle === null ? null : $ids[$cle], $ids['admin']]);
        }

        return $reference;
    };
    $refus = static fn (string $ref): int => (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'acces_refuse' AND subject_type = 'personne' AND subject_ref = " . $db->quote($ref))->fetchColumn();
    $PA = $personne('Penda', 'Alphaprs', 'penda@recette.test');
    $PB = $personne('Pathe', 'Betaprs', 'pathe@recette.test');
    $PQ = $personne('Quentin', 'Fileprs', null);
    $PC = $personne('Coura', 'Creeprs', null, $ids['orientation2']);
    $dossierDe($PA, ['conseiller' => 'conseillerA', 'orientation' => 'orientation']);
    $dossierDe($PB, ['orientation' => 'orientation2']);
    $dossierDe($PQ, []); // seulement dans la file des conseillers
    $fichePersonne = static fn (int $id): string => '/gestion/personnes/' . $id . '/';

    /* P4 bis : deux comptes du même poste ne voient que leurs personnes ; refus en 403 journalisé */
    $l1 = $nav['orientation']->get('/gestion/personnes/');
    $l2 = $nav['orientation2']->get('/gestion/personnes/');
    $verifier('P4 bis', 'Orientation 1 : liste = sa personne seulement', $l1['code'] === 200 && str_contains($l1['body'], 'Alphaprs') && !str_contains($l1['body'], 'Betaprs') && !str_contains($l1['body'], 'Creeprs'), (string) $l1['code']);
    $verifier('P4 bis', 'Orientation 2 : son dossier et la fiche qu’elle a créée', $l2['code'] === 200 && str_contains($l2['body'], 'Betaprs') && str_contains($l2['body'], 'Creeprs') && !str_contains($l2['body'], 'Alphaprs'), (string) $l2['code']);
    $avant = $refus((string) $PB);
    $r = $nav['orientation']->get($fichePersonne($PB));
    $verifier('P4 bis', 'Orientation 1 sur la fiche de l’orientation 2 : 403 journalisé', $r['code'] === 403 && $refus((string) $PB) === $avant + 1, $r['code'] . ', ' . ($refus((string) $PB) - $avant) . ' refus au journal');
    $a = $nav['conseillerA']->get($fichePersonne($PA));
    $q = $nav['conseillerA']->get($fichePersonne($PQ));
    $verifier('P4 bis', 'Conseiller : sa personne 200, dossier seulement dans la file 403', $a['code'] === 200 && $q['code'] === 403, $a['code'] . ' / ' . $q['code']);
    $t = $nav['traitement']->get($fichePersonne($PA));
    $verifier('P4 bis', 'Traitement non affecté : 403', $t['code'] === 403, (string) $t['code']);
    foreach (['prospection', 'commerciale', 'client1'] as $cle) {
        $r = $nav[$cle]->get('/gestion/personnes/');
        $f = $nav[$cle]->get($fichePersonne($PA));
        $verifier('P4 bis', ucfirst($cle) . ' : liste et fiche 403', $r['code'] === 403 && $f['code'] === 403, $r['code'] . ' / ' . $f['code']);
    }
    foreach (['secretaire', 'comptable', 'admin'] as $cle) {
        $r = $nav[$cle]->get('/gestion/personnes/');
        $verifier('P4 bis', ucfirst($cle) . ' : vue transversale complète', $r['code'] === 200 && str_contains($r['body'], 'Alphaprs') && str_contains($r['body'], 'Betaprs') && str_contains($r['body'], 'Fileprs'), (string) $r['code']);
    }
    $n = $nav['orientation']->get('/gestion/');
    $p = $nav['prospection']->get('/gestion/');
    $verifier('P4 bis', 'Menu « Personnes » : orientation oui, prospection non', str_contains($n['body'], '/gestion/personnes/') && !str_contains($p['body'], '/gestion/personnes/'), 'navigation');

    /* Création : accueil (visite), conseiller ; refus au comptable et au traitement */
    $creation = '/gestion/personnes/nouvelle/';
    $jetonCreation = static function (Navigateur $navigateur) use ($creation): string {
        preg_match('/name="creation_token" value="([^"]+)"/', $navigateur->get($creation)['body'], $matches);
        return $matches[1] ?? '';
    };
    $visite = ['first_name' => 'Visite', 'last_name' => 'Accueilprs', 'phone' => '77 222 33 44', 'email' => '', 'origin' => 'visite', 'agency_id' => '1', 'timezone' => 'Africa/Dakar', 'creation_token' => $jetonCreation($nav['secretaire'])];
    $r = $nav['secretaire']->post($creation, $visite);
    $creee = $db->query("SELECT id, origin, created_by FROM people WHERE last_name = 'Accueilprs'")->fetch();
    $verifier('P4 bis', 'Secrétaire crée une fiche « visite »', $r['code'] === 303 && $creee !== false && $creee['origin'] === 'visite' && (int) $creee['created_by'] === $ids['secretaire'] && $chemin($r['location']) === $fichePersonne((int) $creee['id']), $r['code'] . ' ' . $chemin($r['location']));
    $rejeu = $nav['secretaire']->post($creation, $visite);
    $verifier('P4 bis', 'Double envoi : une seule fiche et même destination', $rejeu['code'] === 303 && $rejeu['location'] === $r['location'] && (int) $db->query("SELECT COUNT(*) FROM people WHERE last_name = 'Accueilprs'")->fetchColumn() === 1, (string) $rejeu['code']);
    $visite['creation_token'] = $jetonCreation($nav['secretaire']);
    [$premier, $second] = Navigateur::simultane([[$nav['secretaire'], $creation, $visite], [$nav['secretaire'], $creation, $visite]]);
    $verifier('P4 bis', 'Deux envois simultanés d’un nouveau formulaire créent une seule fiche supplémentaire',
        $premier['code'] === 303 && $second['code'] === 303 && $premier['location'] === $second['location'] && $premier['location'] !== $r['location']
        && (int) $db->query("SELECT COUNT(*) FROM people WHERE last_name = 'Accueilprs'")->fetchColumn() === 2, $premier['code'] . ' / ' . $second['code']);
    $r = $nav['conseillerA']->post($creation, ['first_name' => 'Cree', 'last_name' => 'Parconseillerprs', 'phone' => '', 'email' => 'penda@recette.test', 'origin' => 'site', 'creation_token' => $jetonCreation($nav['conseillerA'])]);
    $cc = $db->query("SELECT id, to_reconcile FROM people WHERE last_name = 'Parconseillerprs'")->fetch();
    $f = $cc ? $nav['conseillerA']->get($fichePersonne((int) $cc['id'])) : ['code' => 0];
    $verifier('P4 bis', 'Conseiller crée une fiche, la voit ; e-mail connu → « à rapprocher »', $r['code'] === 303 && $cc !== false && (int) $cc['to_reconcile'] === 1 && $f['code'] === 200, $r['code'] . ' / ' . $f['code']);
    $c = $nav['comptable']->get('/gestion/personnes/nouvelle/');
    $t = $nav['traitement']->post('/gestion/personnes/nouvelle/', ['first_name' => 'X', 'last_name' => 'Interditprs', 'phone' => '77 333 44 55']);
    $verifier('P4 bis', 'Comptable et traitement ne créent pas de fiche', $c['code'] === 403 && $t['code'] === 403 && (int) $db->query("SELECT COUNT(*) FROM people WHERE last_name = 'Interditprs'")->fetchColumn() === 0, $c['code'] . ' / ' . $t['code']);
    $r = $nav['secretaire']->post('/gestion/personnes/nouvelle/', ['first_name' => 'Sans', 'last_name' => 'Jetonprs', 'phone' => '77 444 55 66'], false);
    $verifier('P12', 'Création sans jeton CSRF', $r['code'] === 419, (string) $r['code']);

    /* P17 : recherche côté serveur (périmètre, caractères spéciaux, limite) ; plus d'index embarqué */
    $json = static fn (array $r): array => json_decode($r['body'], true)['resultats'] ?? [];
    $o1 = $json($nav['orientation']->get('/gestion/recherche/?format=json&q=Betaprs'));
    $o2 = $json($nav['orientation2']->get('/gestion/recherche/?format=json&q=Betaprs'));
    $verifier('P17', 'Recherche dans le périmètre seulement', $o1 === [] && count($o2) === 1 && $o2[0]['type'] === 'Personne', count($o1) . ' / ' . count($o2) . ' résultat(s)');
    $pourcent = $json($nav['admin']->get('/gestion/recherche/?format=json&q=' . rawurlencode('%%')));
    $souligne = $json($nav['admin']->get('/gestion/recherche/?format=json&q=' . rawurlencode('__')));
    $verifier('P17', '« % » et « _ » échappés (aucun joker)', $pourcent === [] && $souligne === [], count($pourcent) . ' / ' . count($souligne));
    $ajout = $db->prepare("INSERT INTO people (first_name, last_name, origin) VALUES (?, 'Nombreuxprs', 'site')");
    for ($i = 1; $i <= 25; $i++) {
        $ajout->execute(['N' . $i]);
    }
    $beaucoup = $json($nav['admin']->get('/gestion/recherche/?format=json&q=Nombreuxprs'));
    $verifier('P17', 'Résultats limités à 20', count($beaucoup) === 20, count($beaucoup) . ' résultat(s)');
    $page = $nav['secretaire']->get('/gestion/');
    $verifier('P17', 'Aucun index de recherche embarqué dans la page', !str_contains($page['body'], 'g-search-data') && str_contains($page['body'], 'data-g-search-url'), 'page « Ma journée »');
    $dossierTrouve = $json($nav['conseillerA']->get('/gestion/recherche/?format=json&q=' . rawurlencode($dossiers['R1']['ref'])));
    $verifier('P17', 'Un dossier se retrouve par sa référence', count($dossierTrouve) === 1 && $dossierTrouve[0]['type'] === 'Dossier', count($dossierTrouve) . ' résultat(s)');

    /* P18 et C5 : l'espace client lit par la personne du compte ; demandes existantes inchangées */
    $P1 = $personne('Client', 'Un', 'client1@recette.test');
    $db->prepare('UPDATE users SET person_id = ? WHERE id = ?')->execute([$P1, $ids['client1']]);
    $db->prepare('UPDATE service_requests SET person_id = ? WHERE user_id = ?')->execute([$P1, $ids['client1']]);
    $m = $nav['client1']->get('/mon-espace/demandes/');
    $verifier('P18', 'Client : ses demandes, pas celles des autres', $m['code'] === 200 && str_contains($m['body'], $dossiers['R1']['ref']) && str_contains($m['body'], $dossiers['R3']['ref']) && !str_contains($m['body'], $dossiers['R2']['ref']), (string) $m['code']);
    $d = $nav['client1']->get('/mon-espace/demandes/' . $dossiers['R1']['ref'] . '/');
    $x = $nav['client1']->get('/mon-espace/demandes/' . $dossiers['R2']['ref'] . '/');
    $verifier('P18', 'Client : sa demande 200, celle d’un autre refusée', $d['code'] === 200 && $x['code'] !== 200, $d['code'] . ' / ' . $x['code']);

    /* P13 : réservation anonyme avec l'e-mail d'un compte → jamais dans l'espace de ce compte */
    $anonyme = $navigateur('anonyme-p13');
    $anonyme->get('/rendez_vous_orientation/');
    $demain = (new DateTimeImmutable('tomorrow'))->format('N') === '7' ? new DateTimeImmutable('+2 days') : new DateTimeImmutable('tomorrow');
    $r = $anonyme->post('/rendez_vous_orientation/', [
        'motive' => 'renseignement', 'mode' => 'telephone', 'preferred_date' => $demain->format('Y-m-d'), 'preferred_slot' => 'matin',
        'first_name' => 'Usurpateur', 'last_name' => 'Anonyme', 'email' => 'client1@recette.test', 'phone' => '77 555 66 77', 'message' => '', 'site_web' => '',
    ]);
    $rdv = $db->query("SELECT reference, user_id, person_id FROM appointments WHERE last_name = 'Anonyme'")->fetch();
    $verifier('P13', 'Réservation anonyme enregistrée sur une autre fiche', $r['code'] === 303 && $rdv !== false && $rdv['user_id'] === null && (int) $rdv['person_id'] !== $P1, $r['code'] . ', fiche ' . ($rdv['person_id'] ?? '?') . ' (compte : ' . $P1 . ')');
    $reconcilier = $rdv ? (int) $db->query('SELECT to_reconcile FROM people WHERE id = ' . (int) $rdv['person_id'])->fetchColumn() : 0;
    $verifier('P13', 'La nouvelle fiche est « à rapprocher »', $reconcilier === 1, (string) $reconcilier);
    $m = $nav['client1']->get('/mon-espace/demandes/');
    $verifier('P13', 'Absente de l’espace du compte', $rdv !== false && !str_contains($m['body'], $rdv['reference']) && !str_contains($m['body'], 'Usurpateur'), 'espace client');

    $dateAnnulation = (new DateTimeImmutable('next monday'))->modify('+1 week');
    $jourAnnulation = $dateAnnulation->format('Y-m-d');
    $db->prepare("UPDATE appointments SET user_id = ?, person_id = ?, preferred_date = ?, status = 'confirme',
        assigned_to = ?, starts_at_utc = ?, ends_at_utc = ? WHERE reference = 'RDV-RCT-1'")
        ->execute([$ids['client1'], $P1, $jourAnnulation, $ids['conseillerA'], "$jourAnnulation 10:00:00", "$jourAnnulation 10:30:00"]);
    $db->prepare("INSERT INTO availability_rules (user_id, agency_id, weekday, starts_at, ends_at, mode) VALUES (?, 1, 1, '10:00', '10:30', 'agence')")
        ->execute([$ids['conseillerA']]);
    $urlCreneaux = '/rendez_vous_orientation/creneaux/?date=' . $jourAnnulation . '&motif=renseignement&mode=agence-dakar';
    $libre = static fn (): bool => in_array('10:00', json_decode($anonyme->get($urlCreneaux)['body'], true) ?? [], true);
    $occupeAvant = !$libre();
    preg_match('/name="_csrf" value="([^"]+)"/', $nav['client1']->get('/mon-espace/demandes/')['body'], $jetonClient);
    $propre = $nav['client1']->post('/mon-espace/rendez-vous/RDV-RCT-1/annuler/', ['_csrf' => $jetonClient[1] ?? '']);
    $avant = $journal('acces_refuse', $rdv['reference']);
    $autre = $nav['client1']->post('/mon-espace/rendez-vous/' . $rdv['reference'] . '/annuler/', ['_csrf' => $jetonClient[1] ?? '']);
    $verifier('P14', 'Annulation personnelle puis refus journalisé hors périmètre',
        $propre['code'] === 303 && $autre['code'] === 403 && $journal('acces_refuse', $rdv['reference']) === $avant + 1
        && $occupeAvant && $libre()
        && $db->query("SELECT status FROM appointments WHERE reference = 'RDV-RCT-1'")->fetchColumn() === 'annule',
        $propre['code'] . ' / ' . $autre['code']);

    $db->exec("INSERT INTO appointments (reference, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone)
        VALUES ('RDV-GUEST-A', 'renseignement', 'telephone', DATE_ADD(UTC_DATE(), INTERVAL 5 DAY), 'matin', 'Recette', 'A', 'a@recette.test', '770000000'),
               ('RDV-GUEST-B', 'renseignement', 'telephone', DATE_ADD(UTC_DATE(), INTERVAL 5 DAY), 'matin', 'Recette', 'B', 'b@recette.test', '770000000')");
    $ouvrirAnnulation = static function (string $reference) use ($db, $anonyme, $chemin): array {
        $token = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO appointment_cancellation_tokens SELECT id, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) FROM appointments WHERE reference = ?')
            ->execute([hash('sha256', $token), $reference]);
        $lien = $anonyme->get('/rendez_vous_orientation/annuler/?jeton=' . $token);
        $formulaire = $anonyme->get($chemin($lien['location']));
        preg_match('/name="formulaire" value="([^"]+)"/', $formulaire['body'], $form);
        preg_match('/name="_csrf" value="([^"]+)"/', $formulaire['body'], $csrf);
        return ['formulaire' => $form[1] ?? '', '_csrf' => $csrf[1] ?? ''];
    };
    $formA = $ouvrirAnnulation('RDV-GUEST-A');
    $ouvrirAnnulation('RDV-GUEST-B');
    $avant = $db->query("SELECT COUNT(*) FROM appointments WHERE reference IN ('RDV-GUEST-A', 'RDV-GUEST-B') AND status = 'demande'")->fetchColumn();
    $annule = $anonyme->post('/rendez_vous_orientation/annuler/', $formA, false);
    $verifier('P14 invité', 'Deux onglets : le GET ne change rien et le formulaire A annule uniquement A',
        (int) $avant === 2 && $annule['code'] === 303
        && $db->query("SELECT status FROM appointments WHERE reference = 'RDV-GUEST-A'")->fetchColumn() === 'annule'
        && $db->query("SELECT status FROM appointments WHERE reference = 'RDV-GUEST-B'")->fetchColumn() === 'demande', (string) $annule['code']);


    /* ---------- Décision 2B, condition B3 : scénarios d'agenda par HTTP ---------- */
    // Le créneau du lundi 10:00 (conseiller A, Dakar) est libre depuis l'annulation P14.
    $public = $navigateur('public-b3');
    $jetonReservation = static function () use ($public): string {
        preg_match('/name="booking_token" value="([a-f0-9]+)"/', $public->get('/rendez_vous_orientation/')['body'], $m);

        return $m[1] ?? '';
    };
    $reservation = static fn (string $email, string $nom, array $plus = []): array => $plus + [
        'motive' => 'renseignement', 'mode' => 'agence-dakar', 'preferred_date' => $jourAnnulation, 'preferred_slot' => 'matin',
        'first_name' => 'Recette', 'last_name' => $nom, 'email' => $email, 'phone' => '77 600 00 01', 'message' => '', 'site_web' => '',
    ];
    $nombre = static fn (string $email): int => (int) $db->query('SELECT COUNT(*) FROM appointments WHERE email = ' . $db->quote($email))->fetchColumn();

    /* P12 : créneau falsifié (heure hors grille, puis heure sans plage) */
    $jeton = $jetonReservation();
    $f1 = $public->post('/rendez_vous_orientation/', $reservation('falsifie@recette.test', 'Falsifie', ['booking_token' => $jeton, 'direct_time' => '10:07']));
    $f2 = $public->post('/rendez_vous_orientation/', $reservation('falsifie@recette.test', 'Falsifie', ['booking_token' => $jeton, 'direct_time' => '15:00']));
    $verifier('P12', 'Créneau falsifié ou hors plage : refusé, rien d’enregistré', $chemin($f1['location']) !== '/rendez_vous_orientation/confirmation/'
        && $chemin($f2['location']) !== '/rendez_vous_orientation/confirmation/' && $nombre('falsifie@recette.test') === 0, $f1['code'] . ' / ' . $f2['code'] . ', ' . $nombre('falsifie@recette.test') . ' rendez-vous');

    $passe = $public->post('/rendez_vous_orientation/', $reservation('passe@recette.test', 'Passe', ['preferred_date' => (new DateTimeImmutable('yesterday'))->format('Y-m-d'), 'booking_token' => $jetonReservation(), 'direct_time' => '10:00']));
    $verifier('P12', 'Créneau dans le passé : refusé, rien d’enregistré', $chemin($passe['location']) !== '/rendez_vous_orientation/confirmation/' && $nombre('passe@recette.test') === 0, $passe['code'] . ', ' . $nombre('passe@recette.test') . ' rendez-vous');

    /* B1 : six demandes simultanées, six navigateurs sans cookie commun, même e-mail → trois au plus (verrou) */
    $rafale = [];
    for ($i = 1; $i <= 6; $i++) {
        $nav_i = $navigateur('rafale-' . $i);
        $nav_i->get('/rendez_vous_orientation/');
        $rafale[] = [$nav_i, '/rendez_vous_orientation/', $reservation('rafale+' . $i . '@recette.test', 'Rafale', ['mode' => 'telephone', 'preferred_date' => $dateAnnulation->modify('+2 days')->format('Y-m-d')])];
    }
    $reponses = Navigateur::simultane($rafale);
    $acceptees = count(array_filter($reponses, static fn (array $r): bool => $chemin($r['location']) === '/rendez_vous_orientation/confirmation/'));
    $enregistres = (int) $db->query("SELECT COUNT(*) FROM appointments WHERE email LIKE 'rafale+%@recette.test'")->fetchColumn();
    $verifier('B1', 'Rafale simultanée, même e-mail (alias) : 3 acceptées au plus, autant en base', $acceptees === 3 && $enregistres === 3, $acceptees . ' acceptée(s), ' . $enregistres . ' en base');

    /* P1 bis : double soumission publique, réellement simultanée, même formulaire */
    $jeton = $jetonReservation();
    $donnees = $reservation('double@recette.test', 'Double', ['booking_token' => $jeton, 'direct_time' => '10:00']);
    $paire = Navigateur::simultane([[$public, '/rendez_vous_orientation/', $donnees], [$public, '/rendez_vous_orientation/', $donnees]]);
    $confirme = $db->query("SELECT status, starts_at_utc FROM appointments WHERE email = 'double@recette.test'")->fetchAll();
    $verifier('P1 bis', 'Double soumission simultanée : un seul rendez-vous, confirmé à 10:00', count($confirme) === 1 && $confirme[0]['status'] === 'confirme'
        && $confirme[0]['starts_at_utc'] === "$jourAnnulation 10:00:00"
        && $chemin($paire[0]['location']) === '/rendez_vous_orientation/confirmation/' && $chemin($paire[1]['location']) === '/rendez_vous_orientation/confirmation/',
        count($confirme) . ' rendez-vous, ' . $paire[0]['code'] . ' / ' . $paire[1]['code']);

    /* P3 : demande sans créneau, confirmée par la secrétaire ; heure saisie à Dakar, stockée en UTC */
    $mardi = $dateAnnulation->modify('+1 day')->format('Y-m-d');
    $r = $public->post('/rendez_vous_orientation/', $reservation('p3@recette.test', 'Sanscreneau', ['preferred_date' => $mardi, 'preferred_slot' => 'apres-midi', 'mode' => 'telephone']));
    $p3 = $db->query("SELECT id, reference, person_id FROM appointments WHERE email = 'p3@recette.test'")->fetch();
    $r = $p3 ? $nav['secretaire']->post('/gestion/rendez-vous/' . $p3['reference'] . '/', ['status' => 'confirme', 'assigned_to' => (string) $ids['orientation'], 'scheduled_at' => $mardi . 'T15:00', 'ends_at' => '']) : ['code' => 0];
    $attenduP3 = (new DateTimeImmutable("$mardi 15:00:00", new DateTimeZone('Africa/Dakar')))->setTimezone(new DateTimeZone('UTC'));
    $etatP3 = $p3 ? $db->query('SELECT status, starts_at_utc, ends_at_utc, assigned_to FROM appointments WHERE id = ' . (int) $p3['id'])->fetch() : [];
    $agendaP3 = $nav['secretaire']->get('/gestion/rendez-vous/?jour=' . $mardi);
    $verifier('P3', 'Demande confirmée : statut, UTC stocké, 30 min, affichée à 15:00 heure de Dakar',
        $r['code'] === 303 && ($etatP3['status'] ?? '') === 'confirme' && ($etatP3['starts_at_utc'] ?? '') === $attenduP3->format('Y-m-d H:i:s')
        && ($etatP3['ends_at_utc'] ?? '') === $attenduP3->modify('+30 minutes')->format('Y-m-d H:i:s')
        && str_contains($agendaP3['body'], '<time>15:00</time>') && str_contains($agendaP3['body'], 'heure de Dakar'),
        $r['code'] . ', ' . ($etatP3['starts_at_utc'] ?? '?'));

    /* Deux agents du même poste face aux rendez-vous attribués (décision 2A, reporté en 2B) */
    $PY = $personne('Yacine', 'Rdvprs', 'yacine@recette.test');
    $db->prepare("INSERT INTO appointments (reference, person_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, status, assigned_to, agency_id, scheduled_at, starts_at_utc, ends_at_utc)
        VALUES ('RDV-ORI-2', ?, 'renseignement', 'telephone', ?, 'apres-midi', 'Yacine', 'Rdvprs', 'yacine@recette.test', '770000000', 'confirme', ?, 1, ?, ?, ?)")
        ->execute([$PY, $mardi, $ids['orientation2'], "$mardi 16:00:00", "$mardi 16:00:00", "$mardi 16:30:00"]);
    $o1 = $nav['orientation']->get('/gestion/rendez-vous/?jour=' . $mardi);
    $o2 = $nav['orientation2']->get('/gestion/rendez-vous/?jour=' . $mardi);
    $verifier('Agenda', 'Deux agents d’orientation : chacun ses rendez-vous attribués', $o1['code'] === 200 && $o2['code'] === 200
        && str_contains($o1['body'], $p3['reference'] ?? '#') && !str_contains($o1['body'], 'RDV-ORI-2')
        && str_contains($o2['body'], 'RDV-ORI-2') && !str_contains($o2['body'], $p3['reference'] ?? '#'), $o1['code'] . ' / ' . $o2['code']);
    $avant = $journal('acces_refuse', 'RDV-ORI-2');
    $r = $nav['orientation']->post('/gestion/rendez-vous/RDV-ORI-2/', ['status' => 'annule']);
    $verifier('Agenda', 'Modifier le rendez-vous d’un collègue du même poste : 403 journalisé, inchangé', $r['code'] === 403
        && $journal('acces_refuse', 'RDV-ORI-2') === $avant + 1 && $db->query("SELECT status FROM appointments WHERE reference = 'RDV-ORI-2'")->fetchColumn() === 'confirme', (string) $r['code']);
    $fp1 = $p3 ? $nav['orientation']->get($fichePersonne((int) $p3['person_id'])) : ['code' => 0];
    $fp2 = $nav['orientation']->get($fichePersonne($PY));
    $verifier('Agenda', 'Fiche personne : celle de son rendez-vous 200, celle du collègue 403', $fp1['code'] === 200 && $fp2['code'] === 403, $fp1['code'] . ' / ' . $fp2['code']);

    /* P15 : exception ou membre désactivé sur un rendez-vous confirmé → signalé à la secrétaire, jamais supprimé */
    $db->prepare("INSERT INTO availability_exceptions (user_id, starts_at_utc, ends_at_utc, reason) VALUES (?, ?, ?, 'Recette')")
        ->execute([$ids['orientation'], "$mardi 14:00:00", "$mardi 18:00:00"]);
    $db->prepare('UPDATE users SET active = 0 WHERE id = ?')->execute([$ids['orientation2']]);
    $s = $nav['secretaire']->get('/gestion/rendez-vous/');
    $restants = (int) $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'confirme' AND (reference = 'RDV-ORI-2' OR id = " . (int) ($p3['id'] ?? 0) . ')')->fetchColumn();
    $verifier('P15', 'Absence et compte désactivé : deux rendez-vous « à replanifier », aucun supprimé', $s['code'] === 200
        && substr_count($s['body'], 'Responsable indisponible') >= 2 && $restants === 2, substr_count($s['body'], 'Responsable indisponible') . ' signalement(s), ' . $restants . ' conservé(s)');
    $db->prepare('UPDATE users SET active = 1 WHERE id = ?')->execute([$ids['orientation2']]);

    $tomorrowUtc = gmdate('Y-m-d', time() + 86400);
    $db->prepare("INSERT INTO appointments (reference, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone,
        status, starts_at_utc, ends_at_utc, created_at)
        VALUES ('RDV-2D-RAPPEL', 'renseignement', 'telephone', ?, 'matin', 'Recette', 'Rappel', 'rappel@recette.test', '770000000',
                'confirme', ?, ?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY))")
        ->execute([$tomorrowUtc, "$tomorrowUtc 10:00:00", "$tomorrowUtc 10:30:00"]);
    $rappelEnv = array_merge($env, ['AGENDA_RAPPEL_HEURE' => '00:00']);
    $executerRappels = static function () use ($php, $root, $rappelEnv): int {
        $process = proc_open([...$php, $root . '/bin/rappels.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $rappelEnv);
        if (!is_resource($process)) return 1;
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process);
    };
    $firstRun = $executerRappels();
    $secondRun = $executerRappels();
    $rappels = $db->query("SELECT status FROM mail_outbox WHERE kind = 'appointment-reminder' AND related = 'RDV-2D-RAPPEL'")->fetchAll(PDO::FETCH_COLUMN);
    $verifier('P9', 'Deux passages du cron : un rappel envoyé via le journal de recette', $firstRun === 0 && $secondRun === 0 && $rappels === ['envoye'], implode(', ', $rappels));
    $db->prepare("INSERT INTO mail_outbox (kind, related, recipient, subject, html, text, status, appointment_start_utc, expires_at_utc)
        VALUES ('appointment-reminder', 'RDV-2D-RAPPEL', 'rappel@recette.test', 'Ancien rappel', '', '', 'echec', ?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE))")
        ->execute(["$tomorrowUtc 10:00:00"]);
    $oldReminder = (int) $db->lastInsertId();
    $nav['admin']->post("/gestion/e-mails/{$oldReminder}/", []);
    $oldStatus = $db->query("SELECT status FROM mail_outbox WHERE id = {$oldReminder}")->fetchColumn();
    $verifier('P9', 'Le renvoi manuel refuse un ancien rappel expiré', $oldStatus === 'echec', (string) $oldStatus);

    /* Affichage des nouveaux écrans, sans erreur PHP (chaque poste qui y a accès) */
    $sain = static fn (array $r): bool => $r['code'] === 200 && !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:') && !str_contains($r['body'], 'Notice:');
    foreach ([['secretaire', '/gestion/personnes/nouvelle/'], ['conseillerA', '/gestion/personnes/nouvelle/'], ['admin', $fichePersonne($PA)], ['admin', '/gestion/personnes/?q=prs&page=2'], ['comptable', '/gestion/recherche/?q=Alphaprs'], ['orientation', '/gestion/recherche/']] as [$cle, $path]) {
        $r = $nav[$cle]->get($path);
        $verifier('Écrans', ucfirst($cle) . ' affiche ' . $path, $sain($r), (string) $r['code']);
    }

    $referencePieces = $dossiers['R1']['ref'];
    $staffPieces = '/gestion/dossiers/' . $referencePieces . '/pieces-attendues/';
    $clientPieces = '/mon-espace/demandes/' . $referencePieces . '/';
    $labelPiece = 'Justificatif de recette phase trois';
    $validite = gmdate('Y-m-d', time() + 30 * 86400);
    $ajout = $nav['admin']->post($staffPieces, ['title' => $labelPiece, 'minimum_valid_until' => $validite]);
    $piece = $db->query("SELECT id, status FROM request_requirements WHERE title = 'Justificatif de recette phase trois'")->fetch();
    $ficheClient = $nav['client1']->get($clientPieces);
    $verifier('3A', 'Pièce demandée par l’équipe visible par le client', $ajout['code'] === 303
        && ($piece['status'] ?? '') === 'manquante' && str_contains($ficheClient['body'], $labelPiece), (string) $ajout['code']);
    preg_match('/name="_csrf" value="([^"]+)"/', $ficheClient['body'], $csrfPieces);
    $actionClient = $clientPieces . 'pieces-attendues/' . $piece['id'] . '/';
    $actionStaff = $staffPieces . $piece['id'] . '/';
    $pdf = $isole . '/recette-piece.pdf';
    file_put_contents($pdf, "%PDF-1.4\n% Pièce fictive\n%%EOF\n");
    $fichier = new CURLFile($pdf, 'application/pdf', 'piece-fictive.pdf');
    $depot = $nav['client1']->post($actionClient, ['_csrf' => $csrfPieces[1] ?? '', 'documents[0]' => $fichier, 'previous_document_id' => '0'], false);
    $lirePiece = $db->prepare('SELECT status, document_id, refusal_reason FROM request_requirements WHERE id = ?');
    $etatPiece = static function () use ($lirePiece, $piece): array {
        $lirePiece->execute([$piece['id']]);
        return $lirePiece->fetch();
    };
    $premiere = $etatPiece();
    $refus = $nav['admin']->post($actionStaff, ['decision' => 'refusee', 'document_id' => $premiere['document_id'], 'reason' => 'Le document de recette doit être lisible.']);
    $ficheRefus = $nav['client1']->get($clientPieces);
    $verifier('3A', 'Dépôt privé, refus motivé et retour visible côté client', $depot['code'] === 303
        && $premiere['status'] === 'deposee' && $refus['code'] === 303 && $etatPiece()['status'] === 'refusee'
        && str_contains($ficheRefus['body'], 'Le document de recette doit être lisible.'), $depot['code'] . ' / ' . $refus['code']);
    $nav['client1']->post($actionClient, ['_csrf' => $csrfPieces[1] ?? '', 'documents[0]' => $fichier, 'previous_document_id' => '0'], false);
    $verifier('3A', 'Un formulaire ouvert avant refus ne redépose pas la pièce', $etatPiece()['status'] === 'refusee', $etatPiece()['status']);
    $nav['client1']->post($actionClient, ['_csrf' => $csrfPieces[1] ?? '', 'documents[0]' => $fichier, 'previous_document_id' => $premiere['document_id']], false);
    $nouvelle = $etatPiece();
    $nav['admin']->post($actionStaff, ['decision' => 'validee', 'document_id' => $premiere['document_id']]);
    $verifier('3A', 'Une décision périmée laisse la nouvelle version à vérifier', $nouvelle['document_id'] !== $premiere['document_id']
        && $etatPiece()['status'] === 'deposee', $etatPiece()['status']);
    $refuse = $nav['secretaire']->post($actionStaff, ['decision' => 'validee', 'document_id' => $nouvelle['document_id']]);
    $nav['admin']->post($actionStaff, ['decision' => 'validee', 'document_id' => $nouvelle['document_id'], 'expires_on' => gmdate('Y-m-d')]);
    $verifier('3A', 'Validité insuffisante : la pièce reste à vérifier', $etatPiece()['status'] === 'deposee', $etatPiece()['status']);
    $valide = $nav['admin']->post($actionStaff, ['decision' => 'validee', 'document_id' => $nouvelle['document_id'], 'expires_on' => $validite]);
    $verifier('3A', 'Validation réservée au personnel autorisé, version courante validée', $refuse['code'] === 403
        && $valide['code'] === 303 && $etatPiece()['status'] === 'validee', $refuse['code'] . ' / ' . $valide['code']);
    $client2 = $navigateur('client2-phase3');
    $client2->connexion('client2@recette.test');
    $urlDocument = $clientPieces . 'documents/' . $nouvelle['document_id'] . '/';
    $prive = $client2->get($urlDocument);
    $proprietaire = $nav['client1']->get($urlDocument);
    $verifier('3A', 'Le dépôt reste inacessible à un autre client', $prive['code'] === 404 && $proprietaire['code'] === 200, $prive['code'] . ' / ' . $proprietaire['code']);
    $db->prepare('UPDATE request_requirements SET expires_on = ? WHERE id = ?')->execute([gmdate('Y-m-d', time() - 86400), $piece['id']]);
    $expire = $nav['client1']->get($clientPieces);
    $nav['client1']->post($actionClient, ['_csrf' => $csrfPieces[1] ?? '', 'documents[0]' => $fichier, 'previous_document_id' => $nouvelle['document_id']], false);
    $verifier('3A', 'Le client renouvelle la pièce expirée et garde accès à l’ancienne version', str_contains($expire['body'], 'À renouveler')
        && $etatPiece()['status'] === 'deposee' && $etatPiece()['document_id'] !== $nouvelle['document_id']
        && $nav['client1']->get($urlDocument)['code'] === 200, $etatPiece()['status']);
    unlink($pdf);

    $dossierSuivi = $dossiers['R2']['ref'];
    $actionSuivi = '/gestion/dossiers/' . $dossierSuivi . '/suivi/';
    $nav['admin']->post($actionSuivi, ['action' => 'result', 'institution' => 'Établissement Alpha', 'result' => 'admis']);
    $nav['admin']->post($actionSuivi, ['action' => 'result', 'institution' => 'Établissement Bêta', 'result' => 'liste-attente']);
    $nav['admin']->post($actionSuivi, ['action' => 'deadline', 'title' => 'Confirmer le choix de formation', 'due_date' => $tomorrowUtc]);
    $clientSuivi = $client2->get('/mon-espace/demandes/' . $dossierSuivi . '/');
    $verifier('3B', 'Échéance et résultats distincts visibles par le client du dossier', $clientSuivi['code'] === 200
        && str_contains($clientSuivi['body'], 'Établissement Alpha') && str_contains($clientSuivi['body'], 'Établissement Bêta')
        && str_contains($clientSuivi['body'], 'Admission obtenue') && str_contains($clientSuivi['body'], 'Liste d’attente')
        && str_contains($clientSuivi['body'], 'Confirmer le choix de formation'), (string) $clientSuivi['code']);
    $echeanceId = (int) $db->query("SELECT id FROM request_deadlines WHERE title = 'Confirmer le choix de formation'")->fetchColumn();
    $db->prepare("INSERT INTO request_assignments (request_id, poste, user_id, assigned_by) VALUES (?, 'traitement', ?, ?)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)")->execute([$dossiers['R2']['id'], $ids['traitement'], $ids['admin']]);
    $firstDeadlineRun = $executerRappels();
    $secondDeadlineRun = $executerRappels();
    $mailsEcheance = $db->query("SELECT recipient, status FROM mail_outbox WHERE kind = 'request-deadline-reminder' AND related LIKE '{$echeanceId}:%' ORDER BY recipient")->fetchAll(PDO::FETCH_KEY_PAIR);
    $verifier('3B', 'Un rappel par destinataire autorisé après deux passages du cron', $firstDeadlineRun === 0 && $secondDeadlineRun === 0
        && ($mailsEcheance['client2@recette.test'] ?? null) === 'envoye' && ($mailsEcheance['traitement@recette.test'] ?? null) === 'envoye'
        && count($mailsEcheance) === 2, count($mailsEcheance) . ' destinataires');
    $nav['admin']->post($actionSuivi, ['action' => 'complete', 'deadline_id' => $echeanceId]);
    $verifier('3B', 'Clôture d’une échéance depuis la gestion', (bool) $db->query("SELECT completed_at FROM request_deadlines WHERE id = {$echeanceId}")->fetchColumn(), (string) $echeanceId);

    $db->prepare("INSERT INTO users (first_name, last_name, email, role, profile, password_hash, consent_at)
        VALUES ('Parent', 'Recette', 'tuteur@recette.test', 'client', 'parent', ?, NOW())")->execute([password_hash(MOT_DE_PASSE, PASSWORD_DEFAULT)]);
    $parentId = (int) $db->lastInsertId();
    $parent = $navigateur('parent-phase3');
    $parent->connexion('tuteur@recette.test');
    $tutorRoute = '/gestion/dossiers/' . $referencePieces . '/tuteurs/';
    $beforeGrant = $parent->get($clientPieces);
    $nav['admin']->post($tutorRoute, ['parent_user_id' => $parentId]);
    $withoutVerification = $parent->get($clientPieces);
    $nav['admin']->post($tutorRoute, ['parent_user_id' => $parentId, 'verified' => '1', 'verification_reference' => 'VERIF-TEST-01']);
    $readOnly = $parent->get($clientPieces);
    $accessId = (int) $db->query("SELECT id FROM request_tutor_access WHERE parent_user_id = {$parentId} AND revoked_at IS NULL")->fetchColumn();
    $verifier('3B', 'Le tuteur doit être vérifié et reçoit seulement la lecture par défaut',
        $beforeGrant['code'] === 404 && $withoutVerification['code'] === 404 && $readOnly['code'] === 200
        && str_contains($readOnly['body'], 'Lecture seule') && !str_contains($readOnly['body'], 'name="previous_document_id"'),
        $beforeGrant['code'] . ' / ' . $withoutVerification['code'] . ' / ' . $readOnly['code']);
    $nav['client1']->post($clientPieces . 'tuteurs/' . $accessId . '/revoquer/', ['_csrf' => $csrfPieces[1] ?? ''], false);
    $verifier('3B', 'Le titulaire retire immédiatement l’accès tuteur au dossier et au fichier',
        $parent->get($clientPieces)['code'] === 404 && $parent->get($urlDocument)['code'] === 404, (string) $accessId);

    $accueil = '/gestion/accueil/';
    $visite = $nav['secretaire']->post($accueil, ['first_name' => 'Recette', 'last_name' => 'VisitePhaseQuatre', 'phone' => '77 600 44 21', 'purpose' => 'Orientation']);
    $visitId = (int) $db->query("SELECT id FROM reception_visits WHERE last_name = 'VisitePhaseQuatre'")->fetchColumn();
    $nav['secretaire']->post($accueil . $visitId . '/', ['action' => 'recu']);
    $nav['secretaire']->post($accueil . $visitId . '/', ['action' => 'convertir', 'person_id' => '']);
    $nav['secretaire']->post($accueil . $visitId . '/', ['action' => 'convertir', 'person_id' => '']);
    $linked = (int) $db->query("SELECT person_id FROM reception_visits WHERE id = {$visitId} AND state = 'recu'")->fetchColumn();
    $verifier('4A', 'Accueil : visite reçue et une seule fiche créée', $visite['code'] === 303 && $linked > 0
        && (int) $db->query("SELECT COUNT(*) FROM people WHERE last_name = 'VisitePhaseQuatre'")->fetchColumn() === 1
        && $nav['secretaire']->get($accueil)['code'] === 200 && $nav['comptable']->get($accueil)['code'] === 403, (string) $linked);
    $db->prepare('UPDATE people SET email = ? WHERE id = ?')->execute(['doublon4@recette.test', $linked]);
    $double = $personne('Recette', 'DoublonPhaseQuatre', 'doublon4@recette.test');
    $compare = $nav['admin']->get('/gestion/doublons/?a=' . $double . '&b=' . $linked);
    $merge = $nav['admin']->post('/gestion/doublons/', ['source' => $linked, 'target' => $double, 'confirmed' => '1']);
    $ancien = $nav['admin']->get('/gestion/personnes/' . $linked . '/');
    $verifier('4A', 'Fusion explicite : visite conservée et ancienne fiche redirigée', $compare['code'] === 200
        && str_contains($compare['body'], 'Comparer les fiches') && $merge['code'] === 303 && $ancien['code'] === 302
        && $chemin($ancien['location']) === '/gestion/personnes/' . $double . '/'
        && (int) $db->query("SELECT person_id FROM reception_visits WHERE id = {$visitId}")->fetchColumn() === $double, $merge['code'] . ' / ' . $ancien['code']);

    $financePath = $fiche($R1) . 'finance/';
    $accountant = $nav['comptable'];
    $financeToken = static function () use ($accountant, $financePath): string {
        preg_match('/name="form_token" value="([a-f0-9]{64})"/', $accountant->get($financePath)['body'], $match);
        return $match[1] ?? '';
    };
    $startQuote = $accountant->post($financePath, ['action' => 'quote', 'title' => 'Accompagnement visa', 'amount' => 100000]);
    $accountant->post($financePath, ['action' => 'installment', 'date' => gmdate('Y-m-d'), 'amount' => 30000]);
    $accountant->post($financePath, ['action' => 'installment', 'date' => gmdate('Y-m-d', time() + 86400), 'amount' => 80000]);
    $token = $financeToken();
    $verifier('5A', 'Devis et échéance enregistrés, dépassement du devis refusé', $startQuote['code'] === 303 && $token !== ''
        && (int) $db->query('SELECT SUM(amount) FROM finance_installments')->fetchColumn() === 30000, (string) $startQuote['code']);
    $verifier('5A', 'Finance inaccessible au conseiller même sur son dossier', $nav['conseillerA']->get($financePath)['code'] === 403
        && $nav['conseillerA']->post($financePath, ['action' => 'quote', 'amount' => 1])['code'] === 403, 'GET et POST');
    $payment = ['action' => 'payment', 'amount' => 30000, 'date' => gmdate('Y-m-d'), 'method' => 'wave', 'form_token' => $token];
    $accountant->post($financePath, array_replace($payment, ['form_token' => str_repeat('0', 64)]));
    $accountant->post($financePath, $payment);
    $accountant->post($financePath, $payment);
    $countPayments = static fn (): int => (int) $db->query('SELECT COUNT(*) FROM finance_payments')->fetchColumn();
    $verifier('5A', 'Un jeton invalide et une double soumission ne créent aucun règlement supplémentaire', $countPayments() === 1, (string) $countPayments());
    $accountant->post($financePath, array_replace($payment, ['amount' => 80000, 'form_token' => $financeToken()]));
    $verifier('5A', 'Surpaiement refusé', $countPayments() === 1, (string) $countPayments());
    $accountant->post($financePath, array_replace($payment, ['amount' => 20000, 'form_token' => $financeToken()]));
    $receipts = $db->query('SELECT receipt FROM finance_payments ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $firstReceipt = $receipts[0] ?? 'manquant';
    $verifier('5A', 'Deux reçus consécutifs dans la séquence de Dakar', count($receipts) === 2
        && preg_match('/^DKR-\d{4}-000001$/', $firstReceipt) && $receipts[1] === substr($firstReceipt, 0, -1) . '2', implode(', ', $receipts));
    $receiptPath = $clientPieces . 'recus/' . $firstReceipt . '/';
    $pdf = $nav['client1']->get($receiptPath);
    $nav['admin']->post($tutorRoute, ['parent_user_id' => $parentId, 'verified' => '1', 'verification_reference' => 'VERIF-TEST-02']);
    $verifier('5A', 'Reçu PDF accessible au titulaire et au comptable, refusé aux autres clients et au tuteur', $pdf['code'] === 200
        && str_starts_with($pdf['body'], '%PDF-1.4') && str_contains($pdf['body'], $firstReceipt) && str_contains($pdf['body'], 'startxref')
        && $accountant->get($financePath . 'recus/' . $firstReceipt . '/')['code'] === 200 && $client2->get($receiptPath)['code'] === 404
        && $parent->get($clientPieces)['code'] === 200 && $parent->get($receiptPath)['code'] === 404, (string) $pdf['code']);
    // Revue du 28/11, écarts 5 et 6 : reçu à l'en-tête de l'agence (logo JPEG embarqué) et page de synthèse finance
    $verifier('5A', 'Reçu PDF avec logo de l’agence et titre', str_contains($pdf['body'], '/DCTDecode') && str_contains($pdf['body'], "RE\xC7U DE R\xC8GLEMENT"), (string) strlen($pdf['body']) . ' octets');
    $synthese = $accountant->get('/gestion/finance/');
    $verifier('5A', 'Soldes et échéances : comptable 200 avec le dossier ; secrétaire, conseiller, commerciale 403',
        $synthese['code'] === 200 && str_contains($synthese['body'], $R1['ref'])
        && $nav['secretaire']->get('/gestion/finance/')['code'] === 403 && $nav['conseillerA']->get('/gestion/finance/')['code'] === 403
        && $nav['commerciale']->get('/gestion/finance/')['code'] === 403, (string) $synthese['code']);
    // Phase 7, lot 7B : pilotage (administrateur) et exports CSV journalisés
    $exportFinance = $accountant->get('/gestion/exports/finance/');
    $verifier('7B', 'Pilotage administrateur ; export finance (BOM, « ; ») pour le comptable, dossiers et prospects refusés',
        $nav['admin']->get('/gestion/pilotage/')['code'] === 200 && $accountant->get('/gestion/pilotage/')['code'] === 403
        && $exportFinance['code'] === 200 && str_starts_with($exportFinance['body'], "\xEF\xBB\xBFDossier;") && str_contains($exportFinance['body'], $R1['ref'])
        && $accountant->get('/gestion/exports/dossiers/')['code'] === 403 && $accountant->get('/gestion/exports/prospects/')['code'] === 403
        && $nav['secretaire']->get('/gestion/exports/finance/')['code'] === 403
        && $nav['admin']->get('/gestion/exports/prospects/')['code'] === 200, (string) $exportFinance['code']);
    $cancellation = ['action' => 'cancel', 'receipt' => $firstReceipt, 'reason' => 'Erreur de saisie'];
    $denied = $accountant->post($financePath, $cancellation);
    $nav['admin']->post($financePath, array_replace($cancellation, ['reason' => '']));
    $beforeCancel = (int) $db->query('SELECT COUNT(*) FROM finance_cancellations')->fetchColumn();
    $nav['admin']->post($financePath, $cancellation);
    $nav['admin']->post($financePath, $cancellation);
    $clientFinance = $nav['client1']->get($clientPieces);
    $verifier('5A', 'Annulation motivée réservée à l’administration, traçable et sans doublon', $denied['code'] === 403 && $beforeCancel === 0
        && (int) $db->query('SELECT COUNT(*) FROM finance_cancellations')->fetchColumn() === 1 && $countPayments() === 2
        && $journal('finance_dossier', $R1['ref']) === 5 && str_contains($nav['client1']->get($receiptPath)['body'], 'ANNUL'), (string) $denied['code']);
    $verifier('5A', 'Le solde client exclut le règlement annulé', $clientFinance['code'] === 200 && str_contains($clientFinance['body'], '80 000 FCFA'), (string) $clientFinance['code']);

    $prospectBase = '/gestion/prospects/';
    $prospector = $nav['prospection'];
    $commercial = $nav['commerciale'];
    $todayProspects = gmdate('Y-m-d');
    $otherCommercial = $navigateur('commerciale2');
    $db->prepare("INSERT INTO users (first_name,last_name,email,profile,role,agency_id,active,password_hash,consent_at,last_login_at)
        VALUES ('Autre','Commerciale','commerciale2@recette.test','particulier','commerciale',1,1,?,NOW(),NOW())")->execute([$hash]);
    $otherCommercialId = (int) $db->lastInsertId();
    $otherCommercial->connexion('commerciale2@recette.test');
    $schoolAdded = $prospector->post($prospectBase . 'campagnes/', ['kind' => 'school', 'name' => 'Lycée Recette', 'city' => 'Dakar']);
    $campaignAdded = $prospector->post($prospectBase . 'campagnes/', ['kind' => 'campaign', 'name' => 'Campagne Recette', 'start' => $todayProspects]);
    $schoolId = (int) $db->query('SELECT id FROM prospect_schools')->fetchColumn();
    $campaignId = (int) $db->query('SELECT id FROM prospect_campaigns')->fetchColumn();
    $context = ['school' => $schoolId, 'campaign' => $campaignId];
    $knownContactCount = (int) $db->query("SELECT COUNT(*) FROM people WHERE email = 'client1@recette.test'")->fetchColumn();
    $catalog = $commercial->get($prospectBase . 'campagnes/');
    $verifier('6A', 'Établissement et campagne créés, catalogue commercial en lecture', $schoolAdded['code'] === 303 && $campaignAdded['code'] === 303
        && $schoolId > 0 && $campaignId > 0 && $catalog['code'] === 200 && str_contains($catalog['body'], 'Lycée Recette')
        && $commercial->post($prospectBase . 'campagnes/', ['kind' => 'school'])['code'] === 403, 'catalogue');
    $importFile = $isole . '/prospects.csv';
    file_put_contents($importFile, "Prénom;Nom;Niveau;E-mail;Téléphone\nAminata;RecetteProspect;Terminale;aminata@recette.test;+221 77 123 45 67\nAminata;RecetteProspect;Terminale;aminata@recette.test;\nVisiteur;CompteExistant;Terminale;client1@recette.test;\nNom;Invalide;Terminale;incorrect;\nMoussa;AutreProspect;Première;;771234599\n");
    $upload = $prospector->post($prospectBase . 'importer/', $context + ['list' => new CURLFile($importFile, 'text/csv')]);
    $preview = $prospector->get($prospectBase . 'importer/');
    preg_match('/name="token" value="([a-f0-9]{32})"/', $preview['body'], $importToken);
    $verifier('6A', 'Aperçu CSV avec doublons et ligne invalide, sans écriture', $upload['code'] === 303 && $preview['code'] === 200 && isset($importToken[1])
        && str_contains($preview['body'], 'lignes 3, 4') && str_contains($preview['body'], 'Ligne 5')
        && (int) $db->query('SELECT COUNT(*) FROM prospects')->fetchColumn() === 0, 'aperçu');
    $commitImport = ['token' => $importToken[1] ?? ''];
    $prospector->post($prospectBase . 'importer/confirmer/', $commitImport);
    $report = $prospector->get($prospectBase . 'importer/');
    $prospector->post($prospectBase . 'importer/confirmer/', $commitImport);
    $records = $db->query('SELECT pr.id, pr.person_id, p.first_name FROM prospects pr JOIN people p ON p.id = pr.person_id ORDER BY pr.id')->fetchAll();
    $verifier('6A', 'Import confirmé et rejeu sans duplication ni rattachement au compte existant', count($records) === 2 && str_contains($report['body'], 'Rapport d’import')
        && (int) $db->query("SELECT COUNT(*) FROM people WHERE email = 'client1@recette.test'")->fetchColumn() === $knownContactCount, (string) count($records));
    if (class_exists(ZipArchive::class)) {
        $xlsxFile = $isole . '/prospects.xlsx';
        $archive = new ZipArchive();
        if ($archive->open($xlsxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Création du classeur de recette impossible.');
        }
        $archive->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet r:id="rId1"/></sheets></workbook>');
        $archive->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $archive->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Prénom</t></si><si><t>Nom</t></si><si><t>Niveau</t></si><si><t>E-mail</t></si></sst>');
        $archive->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Fatou</t></is></c><c r="B2" t="inlineStr"><is><t>Classeur</t></is></c><c r="C2" t="inlineStr"><is><t>Première</t></is></c><c r="D2" t="inlineStr"><is><t>fatou@recette.test</t></is></c></row><row r="3"><c r="A3" t="inlineStr"><is><t>Rejet</t></is></c><c r="B3" t="inlineStr"><is><t>Formule</t></is></c><c r="C3" t="inlineStr"><is><t>Terminale</t></is></c><c r="D3"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>');
        if (!$archive->close() || !is_readable($xlsxFile)) {
            throw new RuntimeException('Écriture du classeur de recette impossible.');
        }
        $xlsxUpload = $prospector->post($prospectBase . 'importer/', $context + ['list' => new CURLFile($xlsxFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'prospects.xlsx')]);
        $xlsxPreview = $prospector->get($prospectBase . 'importer/');
        preg_match('/name="token" value="([a-f0-9]{32})"/', $xlsxPreview['body'], $xlsxToken);
        $prospector->post($prospectBase . 'importer/confirmer/', ['token' => $xlsxToken[1] ?? '']);
        $xlsxSaved = (int) $db->query("SELECT COUNT(*) FROM people WHERE email = 'fatou@recette.test'")->fetchColumn();
        preg_match('/class="g-field-error"[^>]*>([^<]+)/', $xlsxPreview['body'], $xlsxError);
        $verifier('6A', 'Classeur XLSX importé, cellule de formule écartée', str_contains($xlsxPreview['body'], 'Fatou Classeur')
            && str_contains($xlsxPreview['body'], 'Ligne 3') && $xlsxSaved === 1,
            $xlsxUpload['code'] . ' / ' . $xlsxPreview['code'] . ' / ' . $xlsxSaved . ' · ' . ($xlsxError[1] ?? ''));
    }
    $firstProspect = (int) ($records[0]['id'] ?? 0);
    $secondProspect = (int) ($records[1]['id'] ?? 0);
    $detailProspect = $prospectBase . $firstProspect . '/';
    $assignment = $prospector->post($prospectBase . 'attribuer/', ['selection' => [$firstProspect, $secondProspect], 'assignee' => $ids['commerciale']]);
    $verifier('6A', 'Attribution de deux prospects en un lot, fiche inaccessible à une autre commerciale', $assignment['code'] === 303
        && (int) $db->query('SELECT COUNT(*) FROM prospects WHERE assigned_to = ' . $ids['commerciale'])->fetchColumn() === 2
        && $commercial->get($detailProspect)['code'] === 200 && $otherCommercial->get($detailProspect)['code'] === 403
        && $otherCommercial->post($detailProspect, ['action' => 'transmission'])['code'] === 403, (string) $assignment['code']);
    $searchQuery = '/gestion/recherche/?format=json&q=RecetteProspect';
    $verifier('6A', 'Recherche et liste filtrent aussi les coordonnées hors périmètre', str_contains($commercial->get($searchQuery)['body'], 'Aminata')
        && !str_contains($otherCommercial->get($searchQuery)['body'], 'Aminata') && !str_contains($otherCommercial->get($prospectBase)['body'], '771234567'), 'recherche');
    $db->prepare("UPDATE users SET agency_id = (SELECT id FROM agencies WHERE code = 'ziguinchor') WHERE id = ?")->execute([$otherCommercialId]);
    $prospector->post($prospectBase . 'attribuer/', ['selection' => [$firstProspect, $secondProspect], 'assignee' => $otherCommercialId]);
    $verifier('6A', 'Une attribution entre agences ne modifie aucune ligne', (int) $db->query('SELECT COUNT(*) FROM prospects WHERE assigned_to = ' . $ids['commerciale'])->fetchColumn() === 2, 'agence');
    $commercial->post($detailProspect, ['action' => 'appel', 'state' => 'a-rappeler', 'follow_up' => $todayProspects, 'note' => 'Relance interne fictive']);
    $verifier('6A', 'Résultat d’appel, note et relance datée dans le bon périmètre', str_contains($commercial->get($prospectBase . '?vue=relances')['body'], 'Aminata')
        && str_contains($commercial->get($detailProspect)['body'], 'Relance interne fictive') && !str_contains($otherCommercial->get($prospectBase . '?vue=relances')['body'], 'Aminata'), 'relance');
    $proposal = ['date' => gmdate('Y-m-d', time() + 86400 * 20), 'slot' => 'matin', 'mode' => 'telephone'];
    $commercial->post($detailProspect, $proposal + ['action' => 'rendez-vous']);
    $commercial->post($detailProspect, ['action' => 'transmission']);
    $commercial->post($detailProspect, ['action' => 'transmission']);
    $appointmentRows = $db->query('SELECT a.* FROM appointments a JOIN prospect_appointments pa ON pa.appointment_id = a.id')->fetchAll();
    $proposedRef = $appointmentRows[0]['reference'] ?? 'absent';
    $verifier('6A', 'Transmission sans nouvelle personne et sans rendez-vous supplémentaire au rejeu', count($appointmentRows) === 1
        && (int) $appointmentRows[0]['person_id'] === (int) ($records[0]['person_id'] ?? 0) && $appointmentRows[0]['status'] === 'demande'
        && $appointmentRows[0]['user_id'] === null && str_contains($commercial->get($detailProspect)['body'], 'Transmis à l’accueil'), $proposedRef);
    $agendaPath = '/gestion/rendez-vous/';
    $verifier('6A', 'Proposition dans la file de l’accueil et dans le seul agenda commercial concerné', str_contains($nav['secretaire']->get($agendaPath)['body'], $proposedRef)
        && str_contains($commercial->get($agendaPath)['body'], $proposedRef) && !str_contains($otherCommercial->get($agendaPath)['body'], $proposedRef)
        && $commercial->post($agendaPath . $proposedRef . '/', ['status' => 'annule'])['code'] === 403, 'agenda');
    $nav['secretaire']->post($agendaPath . $proposedRef . '/', ['status' => 'confirme', 'assigned_to' => $ids['conseillerA'], 'scheduled_at' => $proposal['date'] . 'T11:00']);
    $verifier('6A', 'L’accueil confirme le rendez-vous, la commerciale voit le nouveau statut', str_contains($commercial->get($agendaPath . '?statut=confirme')['body'], $proposedRef)
        && (bool) $db->query("SELECT 1 FROM appointments WHERE reference = '" . $proposedRef . "' AND status = 'confirme'")->fetchColumn(), $proposedRef);
    $nav['secretaire']->post($agendaPath . $proposedRef . '/', ['status' => 'reporte', 'assigned_to' => $ids['conseillerA'], 'scheduled_at' => $proposal['date'] . 'T14:00']);
    $replacementRef = $db->query("SELECT replacement.reference FROM appointments original JOIN appointments replacement ON replacement.id = original.rescheduled_to_id
        WHERE original.reference = '" . $proposedRef . "'")->fetchColumn();
    $verifier('6A', 'Le report reste visible dans l’agenda commercial du même prospect', is_string($replacementRef) && $replacementRef !== $proposedRef
        && str_contains($commercial->get($agendaPath . '?statut=confirme')['body'], $replacementRef), (string) $replacementRef);

    /* Phase 7, lot 7C : export JSON et anonymisation d'une fiche (administrateur seulement) */
    $titulaire = (int) $db->query("SELECT person_id FROM service_requests WHERE reference = '" . $R1['ref'] . "'")->fetchColumn();
    $exportJson = $nav['admin']->get('/gestion/personnes/' . $titulaire . '/donnees/export/');
    $donneesExport = json_decode($exportJson['body'], true);
    $verifier('7C', 'Export JSON des données par l’administrateur, refusé au secrétariat et au comptable', $exportJson['code'] === 200
        && in_array($R1['ref'], array_column($donneesExport['dossiers'] ?? [], 'reference'), true)
        && $nav['secretaire']->get('/gestion/personnes/' . $titulaire . '/donnees/export/')['code'] === 403
        && $accountant->get('/gestion/personnes/' . $titulaire . '/donnees/')['code'] === 403, (string) $exportJson['code']);
    $nomTitulaire = $db->query('SELECT CONCAT(first_name, " ", last_name) FROM people WHERE id = ' . $titulaire)->fetchColumn();
    $nav['admin']->post('/gestion/personnes/' . $titulaire . '/anonymiser/', ['comprend' => '1', 'confirmation' => $nomTitulaire]);
    $verifier('7C', 'Anonymisation refusée tant qu’un dossier est en cours', $db->query('SELECT erased_at FROM people WHERE id = ' . $titulaire)->fetchColumn() === null, (string) $nomTitulaire);
    $cible = (int) ($records[1]['person_id'] ?? 0);
    $nomCible = (string) $db->query('SELECT CONCAT(first_name, " ", last_name) FROM people WHERE id = ' . $cible)->fetchColumn();
    $nav['admin']->post('/gestion/personnes/' . $cible . '/anonymiser/', ['comprend' => '1', 'confirmation' => 'Autre Nom']);
    $mauvaisNom = $db->query('SELECT erased_at FROM people WHERE id = ' . $cible)->fetchColumn() === null;
    $anonymise = $nav['admin']->post('/gestion/personnes/' . $cible . '/anonymiser/', ['comprend' => '1', 'confirmation' => $nomCible]);
    $apres = $db->query('SELECT first_name, email, phone, erased_at FROM people WHERE id = ' . $cible)->fetch();
    $verifier('7C', 'Anonymisation après confirmation du nom : identité et coordonnées remplacées, journalisée', $mauvaisNom && $anonymise['code'] === 303
        && $apres['first_name'] === 'Personne' && $apres['email'] === null && $apres['phone'] === null && $apres['erased_at'] !== null
        && (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'anonymisation' AND subject_ref = '" . $cible . "'")->fetchColumn() === 1
        && $nav['admin']->get('/gestion/conservation/')['code'] === 200, $nomCible);

    /* ---------- Phase 9, lot 9B : scénarios reportés de la 2C, de la 2D et des phases 4 et 7 ---------- */
    $compter = static function (string $sql, array $params = []) use ($db): int {
        $s = $db->prepare($sql);
        $s->execute($params);

        return (int) $s->fetchColumn();
    };
    // Conseiller C, actif, qui ne tient rien de P1 (le conseiller B a été désactivé en R16) ; client 2 connecté
    $insert->execute(['Conseiller', 'Gamma', 'conseillerc@recette.test', 'conseiller', 'conseiller', 1, $hash]);
    $ids['conseillerC'] = (int) $db->lastInsertId();
    foreach (['conseillerC', 'client2'] as $cle) {
        $nav[$cle] = $navigateur($cle);
        $nav[$cle]->connexion(strtolower($cle) . '@recette.test');
    }
    $notesP1 ='/gestion/personnes/' . $P1 . '/notes/';
    $secretInterne = 'Secret interne neufB ' . bin2hex(random_bytes(3));
    $textePartage = 'Partage client neufB ' . bin2hex(random_bytes(3));
    $nbNotes = static fn (): int => $compter('SELECT COUNT(*) FROM notes WHERE person_id = ?', [$P1]);

    // E1 : aucune visibilité par défaut, visibilité inconnue refusée
    $avantNotes = $nbNotes();
    $nav['conseillerA']->post($notesP1, ['body' => 'Sans choix']);
    $nav['conseillerA']->post($notesP1, ['body' => 'Choix inconnu', 'visibility' => 'publique']);
    $verifier('9B', 'Note sans visibilité ou visibilité inconnue : refusée, rien d’enregistré (E1)', $nbNotes() === $avantNotes, (string) ($nbNotes() - $avantNotes));

    // E4 : postes qui n'écrivent pas de note
    $refusAvant = $compter("SELECT COUNT(*) FROM audit_log WHERE action = 'acces_refuse'");
    $codesEcriture = [];
    foreach (['secretaire', 'comptable', 'prospection', 'commerciale', 'conseillerC'] as $cle) {
        $codesEcriture[$cle] = $nav[$cle]->post($notesP1, ['body' => 'Intrusion', 'visibility' => 'interne'])['code'];
    }
    $refusApres = $compter("SELECT COUNT(*) FROM audit_log WHERE action = 'acces_refuse'");
    $verifier('9B', 'Note écrite par secrétaire, comptable, prospection, commerciale ou conseiller hors périmètre : 403 journalisé (E4)',
        array_unique(array_values($codesEcriture)) === [403] && $refusApres - $refusAvant === 5 && $nbNotes() === $avantNotes, json_encode($codesEcriture));

    // P5, P5 bis, P6 : note interne et note partagée
    $mailsClient = static fn (): int => $compter("SELECT COUNT(*) FROM mail_outbox WHERE recipient = 'client1@recette.test' AND kind = 'note-shared'");
    $mailsAvant = $mailsClient();
    $nav['conseillerA']->post($notesP1, ['body' => $secretInterne, 'visibility' => 'interne']);
    $nav['conseillerA']->post($notesP1, ['body' => $textePartage, 'visibility' => 'partagee']);
    $espaceClient = $nav['client1']->get('/mon-espace/')['body'];
    $vueSecretaire = $nav['secretaire']->get($fichePersonne($P1))['body'];
    $vueComptable = $nav['comptable']->get($fichePersonne($P1))['body'];
    $recherche = $nav['conseillerA']->get('/gestion/recherche/?format=json&q=' . rawurlencode($secretInterne))['body'];
    $p5 = [
        'deux notes' => $nbNotes() === $avantNotes + 2,
        'conseiller' => str_contains($nav['conseillerA']->get($fichePersonne($P1))['body'], $secretInterne),
        'admin' => str_contains($nav['admin']->get($fichePersonne($P1))['body'], $secretInterne),
        'traitement' => str_contains($nav['traitement']->get($fichePersonne($P1))['body'], $secretInterne),
        'pas client' => !str_contains($espaceClient, $secretInterne),
        'pas secrétaire' => !str_contains($vueSecretaire, $secretInterne),
        'pas comptable' => !str_contains($vueComptable, $secretInterne),
        // La réponse renvoie la requête tapée (q) : seuls les résultats comptent
        'pas recherche' => (json_decode($recherche, true)['resultats'] ?? null) === [],
        'pas e-mail' => $compter('SELECT COUNT(*) FROM mail_outbox WHERE html LIKE ? OR text LIKE ?', ['%' . $secretInterne . '%', '%' . $secretInterne . '%']) === 0,
    ];
    $verifier('9B', 'Note interne : visible du conseiller, de l’administrateur et du traitement affecté ; absente du client, de la secrétaire, du comptable, de la recherche et des e-mails (P5, P5 bis)',
        !in_array(false, $p5, true), implode(', ', array_keys(array_filter($p5, static fn (bool $ok): bool => !$ok))) ?: 'conforme');
    $verifier('9B', 'Note partagée : visible du client, de la secrétaire et du comptable ; un e-mail au compte, sans le texte ; absente chez un autre client (P6, N12)',
        str_contains($espaceClient, $textePartage) && str_contains($vueSecretaire, $textePartage) && str_contains($vueComptable, $textePartage)
        && !str_contains($nav['client2']->get('/mon-espace/')['body'], $textePartage) && $mailsClient() === $mailsAvant + 1
        && $compter('SELECT COUNT(*) FROM mail_outbox WHERE html LIKE ? OR text LIKE ?', ['%' . $textePartage . '%', '%' . $textePartage . '%']) === 0,
        ($mailsClient() - $mailsAvant) . ' e-mail(s)');

    // N4 : aucune modification ni suppression de note
    $idNote = $compter('SELECT id FROM notes WHERE body = ?', [$secretInterne]);
    $modif = $nav['admin']->post($notesP1 . $idNote . '/', ['body' => 'Modifiée', 'visibility' => 'partagee']);
    $verifier('9B', 'Modifier ou supprimer une note : aucune route, note intacte (N4)', in_array($modif['code'], [404, 405], true)
        && $compter("SELECT COUNT(*) FROM notes WHERE id = ? AND body = ? AND visibility = 'interne'", [$idNote, $secretInterne]) === 1, (string) $modif['code']);

    // E8, N5, E9 : modèles de procédure versionnés, une seule version publiée, fraîcheur
    $cle9b = 'recette-neufb-' . bin2hex(random_bytes(2));
    $saisieModele = ['template_key' => $cle9b, 'title' => 'Modèle recette 9B', 'service' => 'visa', 'destination' => 'France',
        'steps' => "Constituer le dossier | Passeport;Photo | 7\nDéposer la demande | | 14", 'sources' => 'https://www.campusfrance.org/fr'];
    $creation = $nav['admin']->post('/gestion/modeles/', $saisieModele);
    $v1 = (int) basename($chemin($creation['location']));
    $nav['admin']->post('/gestion/modeles/' . $v1 . '/', ['action' => 'publier']);
    $refusSansVerif = $compter('SELECT COUNT(*) FROM procedure_templates WHERE id = ? AND status = ?', [$v1, 'brouillon']) === 1;
    $nav['admin']->post('/gestion/modeles/' . $v1 . '/', ['action' => 'verifier']);
    $nav['admin']->post('/gestion/modeles/' . $v1 . '/', ['action' => 'publier']);
    $nouvelle = $nav['admin']->post('/gestion/modeles/' . $v1 . '/', ['action' => 'nouvelle-version']);
    $v2 = (int) basename($chemin($nouvelle['location']));
    $nav['admin']->post('/gestion/modeles/' . $v2 . '/', ['action' => 'verifier']);
    $nav['admin']->post('/gestion/modeles/' . $v2 . '/', ['action' => 'publier']);
    $nav['admin']->post('/gestion/modeles/' . $v1 . '/', ['action' => 'publier']);
    $verifier('9B', 'Modèle : publication refusée sans vérification ; la version 2 publiée retire la 1 ; jamais deux versions publiées (E8, N5)',
        $creation['code'] === 303 && $v1 > 0 && $v2 > $v1 && $refusSansVerif
        && $compter("SELECT COUNT(*) FROM procedure_templates WHERE template_key = ? AND status = 'publie'", [$cle9b]) === 1
        && $compter("SELECT COUNT(*) FROM procedure_templates WHERE id = ? AND status = 'publie'", [$v2]) === 1
        && $compter("SELECT COUNT(*) FROM procedure_templates WHERE id = ? AND status = 'retire'", [$v1]) === 1, "v1 #$v1, v2 #$v2");
    $fraicheur = static function (string $intervalle) use ($db, $v2, $nav): bool {
        $db->prepare('UPDATE procedure_templates SET verified_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', (int) strtotime($intervalle)), $v2]);
        $page = $nav['admin']->get('/gestion/modeles/')['body'];
        $position = strpos($page, 'Modèle recette 9B');
        $ligne = $position === false ? '' : substr($page, $position, 600);

        return str_contains($ligne, 'À revérifier');
    };
    $a7 = $fraicheur('-7 months');
    $a5 = $fraicheur('-5 months');
    $verifier('9B', 'Fraîcheur : alerte « À revérifier » à 7 mois, aucune à 5 mois (E9)', $a7 && !$a5, ($a7 ? 'alerte' : 'pas d’alerte') . ' à 7 mois, ' . ($a5 ? 'alerte' : 'rien') . ' à 5 mois');

    // P7, N6, N7, N10, P20, P8 : ouverture d'un dossier depuis la fiche
    $ouvrir = static fn (string $cle, int $personId, array $data): array => $nav[$cle]->post('/gestion/personnes/' . $personId . '/dossier/',
        $data + ['service' => 'visa', 'destination' => 'France', 'intake' => 'Septembre 2027', 'conseiller_id' => '', 'open_token' => bin2hex(random_bytes(16))]);
    $jeton = bin2hex(random_bytes(16));
    $ouverture = $ouvrir('conseillerA', $P1, ['template_id' => (string) $v2, 'open_token' => $jeton]);
    $ouvert = $db->query("SELECT id, reference, template_id, assigned_to FROM service_requests WHERE open_token = '" . $jeton . "'")->fetch();
    $etapes = $ouvert ? $compter('SELECT COUNT(*) FROM request_steps WHERE request_id = ?', [$ouvert['id']]) : 0;
    $verifier('9B', 'Ouverture par le conseiller : dossier affecté à lui, 2 étapes copiées, version enregistrée (P7, N6)', $ouverture['code'] === 303 && $ouvert !== false
        && (int) $ouvert['assigned_to'] === $ids['conseillerA'] && (int) $ouvert['template_id'] === $v2 && $etapes === 2, ($ouvert['reference'] ?? 'aucun') . ', ' . $etapes . ' étape(s)');
    $v3 = (int) basename($chemin($nav['admin']->post('/gestion/modeles/' . $v2 . '/', ['action' => 'nouvelle-version'])['location']));
    $nav['admin']->post('/gestion/modeles/' . $v3 . '/', ['action' => 'verifier']);
    $nav['admin']->post('/gestion/modeles/' . $v3 . '/', ['action' => 'publier']);
    $pageOuvert = $ouvert ? $nav['conseillerA']->get('/gestion/dossiers/' . $ouvert['reference'] . '/')['body'] : '';
    $verifier('9B', 'Une version 3 publiée ne change pas le dossier et lui est proposée (E8)', $ouvert !== false
        && $compter('SELECT template_id FROM service_requests WHERE id = ?', [$ouvert['id']]) === $v2 && str_contains($pageOuvert, 'Une version 3 de ce modèle est publiée'), 'v3 #' . $v3);
    $codesOuverture = [];
    foreach (['secretaire', 'orientation', 'traitement', 'comptable', 'prospection', 'commerciale', 'conseillerC'] as $cle) {
        $codesOuverture[$cle] = $ouvrir($cle, $P1, ['template_id' => ''])['code'];
    }
    $verifier('9B', 'Ouverture refusée à 6 postes et au conseiller hors périmètre : 403 (N11)', array_unique(array_values($codesOuverture)) === [403], json_encode($codesOuverture));
    $brouillon = $nav['admin']->post('/gestion/modeles/', ['template_key' => $cle9b . '-b', 'title' => 'Brouillon 9B'] + $saisieModele);
    $idBrouillon = (int) basename($chemin($brouillon['location']));
    $jetonBrouillon = bin2hex(random_bytes(16));
    $ouvrir('conseillerA', $P1, ['template_id' => (string) $idBrouillon, 'open_token' => $jetonBrouillon]);
    $jetonSans = bin2hex(random_bytes(16));
    $ouvrir('conseillerA', $P1, ['template_id' => '', 'open_token' => $jetonSans]);
    $sansModele = $db->query("SELECT id, template_id FROM service_requests WHERE open_token = '" . $jetonSans . "'")->fetch();
    $verifier('9B', 'Modèle en brouillon forcé : refusé ; ouverture sans modèle : dossier sans étapes (P20, N10)',
        $compter('SELECT COUNT(*) FROM service_requests WHERE open_token = ?', [$jetonBrouillon]) === 0 && $sansModele !== false && $sansModele['template_id'] === null
        && $compter('SELECT COUNT(*) FROM request_steps WHERE request_id = ?', [$sansModele['id'] ?? 0]) === 0, 'brouillon #' . $idBrouillon);
    $jetonDouble = bin2hex(random_bytes(16));
    $donneesDouble = ['service' => 'visa', 'destination' => 'France', 'intake' => '2027', 'template_id' => '', 'conseiller_id' => '', 'open_token' => $jetonDouble];
    Navigateur::simultane([[$nav['conseillerA'], '/gestion/personnes/' . $P1 . '/dossier/', $donneesDouble], [$nav['conseillerA'], '/gestion/personnes/' . $P1 . '/dossier/', $donneesDouble]]);
    $verifier('9B', 'Double ouverture simultanée avec le même jeton : un seul dossier (P8, N7)', $compter('SELECT COUNT(*) FROM service_requests WHERE open_token = ?', [$jetonDouble]) === 1,
        $compter('SELECT COUNT(*) FROM service_requests WHERE open_token = ?', [$jetonDouble]) . ' dossier(s)');
    $verifier('9B', 'Dossier ouvert visible par le client de la personne, refusé à un autre client', $ouvert !== false
        && $nav['client1']->get('/mon-espace/demandes/' . $ouvert['reference'] . '/')['code'] === 200
        && $nav['client2']->get('/mon-espace/demandes/' . $ouvert['reference'] . '/')['code'] !== 200, $ouvert['reference'] ?? '');
    $sansCourriel = $personne('Recette', 'SansCourriel', null, $ids['conseillerA']);
    $mailsTotal = $compter('SELECT COUNT(*) FROM mail_outbox');
    $ouvertureSansMail = $ouvrir('conseillerA', $sansCourriel, ['template_id' => '']);
    $verifier('9B', 'Dossier d’une personne sans e-mail : ouvert, aucune ligne d’e-mail, aucune erreur (N9)', $ouvertureSansMail['code'] === 303
        && $compter('SELECT COUNT(*) FROM service_requests WHERE person_id = ?', [$sansCourriel]) === 1 && $compter('SELECT COUNT(*) FROM mail_outbox') === $mailsTotal, (string) $ouvertureSansMail['code']);
    $etape = $ouvert ? $compter('SELECT MIN(id) FROM request_steps WHERE request_id = ?', [$ouvert['id']]) : 0;
    $cheminEtape = '/gestion/dossiers/' . ($ouvert['reference'] ?? '') . '/etapes/' . $etape . '/';
    $etapeConseiller = $nav['conseillerA']->post($cheminEtape, ['fait' => '1'])['code'];
    $etapeComptable = $nav['comptable']->post($cheminEtape, ['fait' => '1'])['code'];
    $etapeAdmin = $nav['admin']->post($cheminEtape, ['fait' => '1'])['code'];
    $verifier('9B', 'Étape cochée par l’administrateur ; conseiller et comptable lisent seulement (N10)', $etapeConseiller === 403 && $etapeComptable === 403 && $etapeAdmin === 303
        && $compter('SELECT COUNT(*) FROM request_steps WHERE id = ? AND done_at IS NOT NULL', [$etape]) === 1, "conseiller $etapeConseiller, comptable $etapeComptable, admin $etapeAdmin");

    // E5, N3, N12 : compte rendu d'entretien
    $db->prepare("INSERT INTO appointments (reference, person_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, status, assigned_to, agency_id, scheduled_at, starts_at_utc, ends_at_utc)
        VALUES ('RDV-NEUFB-1', ?, 'renseignement', 'telephone', ?, 'matin', 'Client', 'Un', 'autre-adresse-9b@recette.test', '77 000 00 02', 'confirme', ?, 1, ?, ?, ?)")
        ->execute([$P1, gmdate('Y-m-d', time() + 172800), $ids['conseillerA'], gmdate('Y-m-d H:i:s', time() + 172800), gmdate('Y-m-d H:i:s', time() + 172800), gmdate('Y-m-d H:i:s', time() + 174600)]);
    $idRdv = (int) $db->lastInsertId();
    $crChemin = '/gestion/rendez-vous/RDV-NEUFB-1/compte-rendu/';
    $compteRendu = ['body' => 'Compte rendu neufB', 'visibility' => 'partagee'];
    $nav['conseillerA']->post($crChemin, $compteRendu);
    $avantHeure = $compter('SELECT COUNT(*) FROM notes WHERE appointment_id = ?', [$idRdv]) === 0;
    $db->prepare('UPDATE appointments SET scheduled_at = ?, starts_at_utc = ?, ends_at_utc = ? WHERE id = ?')
        ->execute([gmdate('Y-m-d H:i:s', time() - 3600), gmdate('Y-m-d H:i:s', time() - 3600), gmdate('Y-m-d H:i:s', time() - 1800), $idRdv]);
    $crAutre = $nav['conseillerC']->post($crChemin, $compteRendu)['code'];
    $mailsAvant = $mailsClient();
    $crOk = $nav['conseillerA']->post($crChemin, $compteRendu);
    $nav['conseillerA']->post($crChemin, ['body' => 'Second compte rendu', 'visibility' => 'interne']);
    $verifier('9B', 'Compte rendu : refusé avant l’heure et à un autre agent ; le premier passe le rendez-vous à « honoré », un seul par rendez-vous (E5, N3)',
        $avantHeure && $crAutre === 403 && $crOk['code'] === 303 && $compter('SELECT COUNT(*) FROM notes WHERE appointment_id = ?', [$idRdv]) === 1
        && $compter("SELECT COUNT(*) FROM appointments WHERE id = ? AND status = 'honore'", [$idRdv]) === 1, "autre agent $crAutre, rédaction " . $crOk['code']);
    $verifier('9B', 'E-mail « compte rendu disponible » vers le compte, jamais vers l’adresse du rendez-vous (N12)', $mailsClient() === $mailsAvant + 1
        && $compter("SELECT COUNT(*) FROM mail_outbox WHERE recipient = 'autre-adresse-9b@recette.test'") === 0, ($mailsClient() - $mailsAvant) . ' e-mail(s)');

    // 419 sur les nouveaux formulaires (note, ouverture, modèle, tâche, compte rendu)
    $sansJeton = [
        'note' => $nav['conseillerA']->post($notesP1, ['body' => 'Sans jeton', 'visibility' => 'interne'], false)['code'],
        'ouverture' => $nav['conseillerA']->post('/gestion/personnes/' . $P1 . '/dossier/', ['service' => 'visa', 'open_token' => bin2hex(random_bytes(16))], false)['code'],
        'modele' => $nav['admin']->post('/gestion/modeles/', $saisieModele, false)['code'],
        'tache' => $nav['conseillerA']->post('/gestion/taches/', ['title' => 'Sans jeton'], false)['code'],
        'compte-rendu' => $nav['conseillerA']->post($crChemin, $compteRendu, false)['code'],
    ];
    $verifier('9B', 'Nouveaux formulaires sans jeton CSRF : 419 partout', array_unique(array_values($sansJeton)) === [419], json_encode($sansJeton));

    // P16 : tâches (2D)
    preg_match('/name="form_token" value="([a-f0-9]{64})"/', $nav['conseillerA']->get($fichePersonne($P1))['body'], $jetonTache);
    $titreTache = 'Tâche neufB ' . bin2hex(random_bytes(2));
    $tache = ['title' => $titreTache, 'person_id' => (string) $P1, 'form_token' => $jetonTache[1] ?? ''];
    $nav['conseillerA']->post('/gestion/taches/', $tache);
    $nav['conseillerA']->post('/gestion/taches/', $tache);
    $idTache = $compter('SELECT id FROM tasks WHERE title = ?', [$titreTache]);
    $refusTache = $compter("SELECT COUNT(*) FROM audit_log WHERE action = 'acces_refuse' AND subject_type = 'tache'");
    $cocheAutre = $nav['conseillerC']->post('/gestion/taches/' . $idTache . '/etat/', ['done' => '1'])['code'];
    $verifier('9B', 'Tâche : créée une fois malgré le rejeu, visible de son responsable seulement, refus journalisé pour un autre (P16)',
        $idTache > 0 && $compter('SELECT COUNT(*) FROM tasks WHERE title = ?', [$titreTache]) === 1 && str_contains($nav['conseillerA']->get('/gestion/')['body'], $titreTache)
        && !str_contains($nav['conseillerC']->get('/gestion/')['body'], $titreTache) && $cocheAutre === 403
        && $compter("SELECT COUNT(*) FROM audit_log WHERE action = 'acces_refuse' AND subject_type = 'tache'") === $refusTache + 1, "autre responsable $cocheAutre");
    $nav['conseillerA']->post('/gestion/taches/' . $idTache . '/etat/', ['done' => '1']);
    $verifier('9B', 'Tâche cochée par son responsable', $compter('SELECT COUNT(*) FROM tasks WHERE id = ? AND done_at IS NOT NULL AND done_by = ?', [$idTache, $ids['conseillerA']]) === 1, '#' . $idTache);

    // 2D : e-mail de report
    $db->prepare("UPDATE appointments SET status = 'confirme', scheduled_at = ?, starts_at_utc = ?, ends_at_utc = ? WHERE id = ?")
        ->execute([gmdate('Y-m-d H:i:s', time() + 259200), gmdate('Y-m-d H:i:s', time() + 259200), gmdate('Y-m-d H:i:s', time() + 261000), $idRdv]);
    $deplaces = $compter("SELECT COUNT(*) FROM mail_outbox WHERE kind = 'appointment-moved'");
    $nav['secretaire']->post('/gestion/rendez-vous/RDV-NEUFB-1/', ['status' => 'reporte', 'assigned_to' => (string) $ids['conseillerA'], 'scheduled_at' => gmdate('Y-m-d', time() + 345600) . 'T10:00']);
    $verifier('9B', 'Report d’un rendez-vous confirmé : e-mail « rendez-vous déplacé » (2D, F1)', $compter("SELECT COUNT(*) FROM mail_outbox WHERE kind = 'appointment-moved'") === $deplaces + 1,
        ($compter("SELECT COUNT(*) FROM mail_outbox WHERE kind = 'appointment-moved'") - $deplaces) . ' e-mail(s)');

    // P9 bis : un rendez-vous reporté reçoit un seul rappel, à la nouvelle date ; l'ancien n'en reçoit aucun
    $remplacant = $db->query("SELECT r.id, r.reference FROM appointments o JOIN appointments r ON r.id = o.rescheduled_to_id WHERE o.reference = 'RDV-NEUFB-1'")->fetch();
    $rappelsDe = static fn (string $ref): int => $compter("SELECT COUNT(*) FROM mail_outbox WHERE kind = 'appointment-reminder' AND related = ?", [$ref]);
    $executerRappels();
    $avantVeille = $remplacant ? $rappelsDe($remplacant['reference']) : -1;
    if ($remplacant) {
        // Le temps passe : la nouvelle date devient demain, la confirmation date de deux jours
        $demain = gmdate('Y-m-d', time() + 86400);
        $db->prepare("UPDATE appointments SET status = 'confirme', scheduled_at = ?, starts_at_utc = ?, ends_at_utc = ?, created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) WHERE id = ?")
            ->execute(["$demain 10:00:00", "$demain 10:00:00", "$demain 10:30:00", $remplacant['id']]);
        $db->prepare('UPDATE appointment_events SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) WHERE appointment_id = ?')->execute([$remplacant['id']]);
    }
    $executerRappels();
    $executerRappels();
    $verifier('9B', 'Rendez-vous reporté : aucun rappel avant la veille, puis un seul, pour la nouvelle date ; aucun pour l’ancien (P9 bis)', $remplacant !== false
        && $avantVeille === 0 && $rappelsDe($remplacant['reference']) === 1 && $rappelsDe('RDV-NEUFB-1') === 0, ($remplacant['reference'] ?? 'aucun remplaçant') . ', ' . ($remplacant ? $rappelsDe($remplacant['reference']) : 0) . ' rappel(s)');
    // P9 bis : confirmation tardive (moins de 24 h avant le début) : aucun rappel
    $minutes = (int) gmdate('H') * 60 + (int) gmdate('i');
    if ($minutes >= 60) {
        $debutTardif = gmdate('Y-m-d', time() + 86400) . ' ' . gmdate('H:i:s', time() - 1800);
        $db->prepare("INSERT INTO appointments (reference, person_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, status, assigned_to, agency_id, scheduled_at, starts_at_utc, ends_at_utc)
            VALUES ('RDV-NEUFB-TARD', ?, 'renseignement', 'telephone', ?, 'matin', 'Client', 'Tardif', 'tardif@recette.test', '77 000 00 03', 'confirme', ?, 1, ?, ?, DATE_ADD(?, INTERVAL 30 MINUTE))")
            ->execute([$P1, substr($debutTardif, 0, 10), $ids['conseillerA'], $debutTardif, $debutTardif, $debutTardif]);
        $executerRappels();
        $verifier('9B', 'Confirmation à moins de 24 h du début : aucun rappel (P9 bis)', $rappelsDe('RDV-NEUFB-TARD') === 0, $rappelsDe('RDV-NEUFB-TARD') . ' rappel(s)');
    }

    // N1 : secrétaire avec la mission « traitement » : notes internes des seules personnes tenues à ce titre
    $nav['admin']->post('/gestion/equipe/' . $ids['secretaire'] . '/', ['action' => 'mission', 'mission' => 'traitement']);
    $personneTraitee = $personne('Recette', 'TraiteeParSecretaire', null);
    $dossierDe($personneTraitee, ['traitement' => 'secretaire']);
    $noteTraitee = 'Interne traitement neufB ' . bin2hex(random_bytes(2));
    $nav['admin']->post('/gestion/personnes/' . $personneTraitee . '/notes/', ['body' => $noteTraitee, 'visibility' => 'interne']);
    $vueTenue = $nav['secretaire']->get($fichePersonne($personneTraitee))['body'];
    $vueNonTenue = $nav['secretaire']->get($fichePersonne($P1))['body'];
    $nav['admin']->post('/gestion/equipe/' . $ids['secretaire'] . '/', ['action' => 'mission', 'mission' => 'traitement']);
    $verifier('9B', 'Compte à deux postes (secrétaire + traitement) : note interne lue sur la personne tenue, jamais par la vue transversale (N1)',
        str_contains($vueTenue, $noteTraitee) && !str_contains($vueNonTenue, $secretInterne)
        && $compter("SELECT COUNT(*) FROM user_missions WHERE user_id = ? AND revoked_at IS NULL", [$ids['secretaire']]) === 0, 'mission retirée ensuite');

    // Phase 4 : fusion d'une fiche liée à un compte, reportée à un parcours dédié
    $P2 = $personne('Client', 'Deux', 'client2@recette.test');
    $db->prepare('UPDATE users SET person_id = ? WHERE id = ?')->execute([$P2, $ids['client2']]);
    $cibleFusion = $personne('Client', 'DeuxDoublon', null);
    $nav['admin']->post('/gestion/doublons/', ['source' => $P2, 'target' => $cibleFusion, 'confirmed' => '1']);
    $verifier('9B', 'Fusion d’une fiche liée à un compte client : refusée, rien de déplacé (phase 4)', $compter('SELECT COUNT(*) FROM people WHERE id = ? AND merged_into IS NULL', [$P2]) === 1
        && $compter('SELECT person_id FROM users WHERE id = ?', [$ids['client2']]) === $P2, 'fiche #' . $P2);

    // Phase 7 : numéro WhatsApp invalide, journal des messages
    $numeroFaux = $personne('Recette', 'NumeroFaux', null);
    $db->prepare("UPDATE people SET phone = '123' WHERE id = ?")->execute([$numeroFaux]);
    $formMessage = $nav['admin']->get('/gestion/personnes/' . $numeroFaux . '/message/?modele=1');
    $nav['admin']->post('/gestion/personnes/' . $numeroFaux . '/message/', ['modele' => '1', 'texte' => 'Bonjour', 'canal' => 'whatsapp']);
    $messagesP1 = $compter('SELECT COUNT(*) FROM message_logs WHERE person_id = ?', [$P1]);
    $envoi = $nav['conseillerA']->post('/gestion/personnes/' . $P1 . '/message/', ['modele' => '1', 'texte' => 'Bonjour {prenom}', 'canal' => 'email']);
    $verifier('9B', 'Message : aucun bouton ni journal pour un numéro invalide ; ouverture d’un e-mail journalisée sans le texte (7A)', $formMessage['code'] === 200
        && !str_contains($formMessage['body'], 'Ouvrir WhatsApp') && $compter('SELECT COUNT(*) FROM message_logs WHERE person_id = ?', [$numeroFaux]) === 0
        && $envoi['code'] === 200 && str_contains($envoi['body'], 'mailto:') && $compter('SELECT COUNT(*) FROM message_logs WHERE person_id = ?', [$P1]) === $messagesP1 + 1,
        (string) $envoi['code']);

    // Phase 7 : anonymisation d'un dossier clos avec pièce et règlement
    $archivee = $personne('Recette', 'Archivee', 'archivee@recette.test');
    $db->prepare("INSERT INTO service_requests (reference, user_id, person_id, service, status, first_name, last_name, email, phone) VALUES (?, NULL, ?, 'visa', 'terminee', 'Recette', 'Archivee', 'archivee@recette.test', '77 000 00 07')")
        ->execute(['TAY-ARC-' . bin2hex(random_bytes(3)), $archivee]);
    $dossierArchive = (int) $db->lastInsertId();
    $fichierArchive = bin2hex(random_bytes(32)) . '.pdf';
    file_put_contents($isole . '/uploads/' . $fichierArchive, '%PDF-1.4 archive');
    $db->prepare("INSERT INTO request_documents (request_id, original_name, stored_name, mime, size) VALUES (?, 'passeport.pdf', ?, 'application/pdf', 16)")->execute([$dossierArchive, $fichierArchive]);
    $recuArchive = 'REC-ARC-' . bin2hex(random_bytes(3));
    $db->prepare("INSERT INTO finance_quotes (request_id, title, amount) VALUES (?, 'Accompagnement visa', 50000)")->execute([$dossierArchive]);
    $db->prepare("INSERT INTO finance_payments (request_id, receipt, form_token, amount, method, received_on, recorded_by, customer_name, case_reference) VALUES (?, ?, ?, 50000, 'especes', ?, ?, 'Recette Archivee', 'TAY-ARC')")
        ->execute([$dossierArchive, $recuArchive, bin2hex(random_bytes(32)), gmdate('Y-m-d'), $ids['comptable']]);
    $nav['admin']->post('/gestion/personnes/' . $archivee . '/anonymiser/', ['comprend' => '1', 'confirmation' => 'Recette Archivee']);
    $verifier('9B', 'Anonymisation : fichier de la pièce supprimé, dossier anonymisé, règlement et reçu intacts (7C)', !is_file($isole . '/uploads/' . $fichierArchive)
        && $compter("SELECT COUNT(*) FROM service_requests WHERE id = ? AND first_name = 'Personne' AND email IS NULL", [$dossierArchive]) === 1
        && $compter("SELECT COUNT(*) FROM finance_payments WHERE receipt = ? AND customer_name = 'Recette Archivee' AND amount = 50000", [$recuArchive]) === 1, $recuArchive);

    /* Phase 9, lot 9A : matrice des droits. Attendu écrit à la main d'après le §3.5 du plan, jamais lu dans Access */
    $tous = ['admin', 'secretaire', 'prospection', 'commerciale', 'conseiller', 'orientation', 'traitement', 'comptable'];
    $matrice = [
        '/gestion/' => $tous,
        '/gestion/recherche/' => $tous,
        '/gestion/dossiers/' => ['admin', 'secretaire', 'conseiller', 'orientation', 'traitement', 'comptable'],
        '/gestion/personnes/' => ['admin', 'secretaire', 'conseiller', 'orientation', 'traitement', 'comptable'],
        '/gestion/personnes/nouvelle/' => ['admin', 'secretaire', 'conseiller', 'orientation'],
        '/gestion/rendez-vous/' => ['admin', 'secretaire', 'conseiller', 'orientation', 'commerciale'],
        '/gestion/prospects/' => ['admin', 'prospection', 'commerciale'],
        '/gestion/prospects/campagnes/' => ['admin', 'prospection', 'commerciale'],
        '/gestion/prospects/importer/' => ['admin', 'prospection'],
        '/gestion/accueil/' => ['admin', 'secretaire'],
        '/gestion/messages/' => ['admin', 'secretaire'],
        '/gestion/finance/' => ['admin', 'comptable'],
        '/gestion/exports/finance/' => ['admin', 'comptable'],
        '/gestion/exports/dossiers/' => ['admin'],
        '/gestion/exports/prospects/' => ['admin'],
        '/gestion/doublons/' => ['admin'],
        '/gestion/equipe/' => ['admin'],
        '/gestion/journal/' => ['admin'],
        '/gestion/e-mails/' => ['admin'],
        '/gestion/modeles/' => ['admin'],
        '/gestion/modeles/nouveau/' => ['admin'],
        '/gestion/messages-modeles/' => ['admin'],
        '/gestion/pilotage/' => ['admin'],
        '/gestion/conservation/' => ['admin'],
    ];
    // Toute page /gestion/ sans paramètre déclarée dans les routes doit figurer dans la matrice (hors maquette)
    preg_match_all("~router->get\\('(/gestion[^'{]*)'~", (string) file_get_contents($root . '/routes/web.php'), $declarees);
    $absentes = array_values(array_filter($declarees[1], static fn (string $p): bool => !str_starts_with($p, '/gestion/maquette')
        && !isset($matrice[rtrim($p, '/') . '/'])));
    $verifier('9A', 'Chaque page de la gestion sans paramètre figure dans la matrice des droits', $absentes === [], $absentes === [] ? count($matrice) . ' pages' : implode(', ', $absentes));
    $profils = ['admin' => 'admin', 'secretaire' => 'secretaire', 'prospection' => 'prospection', 'commerciale' => 'commerciale',
        'conseillerA' => 'conseiller', 'orientation' => 'orientation', 'traitement' => 'traitement', 'comptable' => 'comptable'];
    $visiteur = $navigateur('visiteur');
    foreach ($matrice as $page => $permis) {
        $ecarts = [];
        foreach ($profils as $cle => $poste) {
            $code = $nav[$cle]->get($page)['code'];
            if ($code !== (in_array($poste, $permis, true) ? 200 : 403)) {
                $ecarts[] = $poste . ' ' . $code;
            }
        }
        $client = $nav['client1']->get($page)['code'];
        if ($client !== 403) {
            $ecarts[] = 'client ' . $client;
        }
        $anonyme = $visiteur->get($page);
        if (!in_array($anonyme['code'], [302, 303], true) || !str_starts_with($chemin($anonyme['location']), '/connexion')) {
            $ecarts[] = 'visiteur ' . $anonyme['code'];
        }
        $verifier('9A', 'Droits ' . $page . ' : ' . implode(', ', $permis), $ecarts === [], $ecarts === [] ? 'conforme' : implode(' ; ', $ecarts));
    }

    $totalMail = (int) $db->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn();
    $planned = [];
    foreach (['rappels.php', 'send-mails.php'] as $file) {
        $job = proc_open([...$php, $root . '/bin/' . $file, '--planifie'], [1 => ['file', $nul, 'w'], 2 => ['file', $nul, 'w']], $jobPipes, $root, $env);
        $planned[] = is_resource($job) ? proc_close($job) : 1;
    }
    $verifier('9C', 'Tâches planifiées inactives avec le pilote log', $planned === [0, 0]
        && (int) $db->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn() === $totalMail, 'rappels et file');

    $primaryLinks = [
        'admin' => '/gestion/pilotage/', 'secretaire' => '/gestion/rendez-vous/', 'prospection' => '/gestion/prospects/?vue=libres',
        'commerciale' => '/gestion/prospects/?vue=relances', 'conseillerA' => '/gestion/dossiers/?vue=mes',
        'orientation' => '/gestion/dossiers/?vue=a-prendre', 'traitement' => '/gestion/dossiers/?vue=a-prendre', 'comptable' => '/gestion/finance/',
    ];
    $rolesOk = 0;
    foreach ($primaryLinks as $account => $priorityUrl) {
        $day = $nav[$account]->get('/gestion/');
        if ($day['code'] === 200 && preg_match('~class="g-btn g-btn-primary g-day-action" href="' . preg_quote($priorityUrl, '~') . '"~', $day['body'])) {
            $rolesOk++;
        }
    }
    $verifier('10', 'Une action métier vérifiée pour chacun des huit postes', $rolesOk === count($primaryLinks), $rolesOk . ' sur 8');

    $phaseTenRequest = $creer('client1', 'visa');
    $db->prepare("UPDATE service_requests SET status = 'attente-client' WHERE id = ?")->execute([$phaseTenRequest['id']]);
    $pieceRecette = $db->prepare("INSERT INTO request_requirements (request_id, title, status) VALUES (?, 'Pièce phase dix', 'manquante')");
    $pieceRecette->execute([$phaseTenRequest['id']]);
    $nextPieceId = (int) $db->lastInsertId();
    $dashboardPage = $nav['client1']->get('/mon-espace/');
    $detailsPhaseTen = '/mon-espace/demandes/' . $phaseTenRequest['ref'] . '/';
    $pieceLink = $detailsPhaseTen . '#piece-' . $nextPieceId;
    $verifier('10', 'La prochaine action client mène à sa pièce attendue', $dashboardPage['code'] === 200
        && str_contains($dashboardPage['body'], 'class="client-workspace"') && str_contains($dashboardPage['body'], $pieceLink)
        && str_contains($nav['client1']->get($detailsPhaseTen)['body'], 'id="piece-' . $nextPieceId . '"')
        && !str_contains($nav['client2']->get('/mon-espace/')['body'], $pieceLink), (string) $dashboardPage['code']);

    /* K4 : la maquette répond 404 sans GESTION_MAQUETTE */
    $r = $nav['admin']->get('/gestion/maquette/');
    $verifier('K4', 'Maquette sans GESTION_MAQUETTE : 404', $r['code'] === 404, (string) $r['code']);

    $code = array_filter($resultats, static fn (array $r): bool => !$r[2]) === [] ? 0 : 1;
} catch (Throwable $exception) {
    fwrite(STDERR, "\nArrêt : " . $exception->getMessage() . "\n");
    $code = 1;
} finally {
    /* ---------- 5. Nettoyage : seulement ce que le robot a créé ---------- */
    if ($serveur !== null) {
        proc_terminate($serveur);
        proc_close($serveur);
    }
    if (!$keep) {
        if ($creee ?? false) {
            $server->exec('DROP DATABASE IF EXISTS `' . BASE . '`');
        }
        supprimer_dossier($isole ?? '');
    }
}

$echecs = array_filter($resultats, static fn (array $r): bool => !$r[2]);
echo "\n" . count($resultats) . ' contrôles, ' . (count($resultats) - count($echecs)) . ' réussis, ' . count($echecs) . " en échec.\n";
echo $keep ? 'Base ' . BASE . ' et dossier storage/recette/' . BASE . "/ conservés pour inspection.\n" : 'Base ' . BASE . " et dossier de l’exécution supprimés.\n";
exit($code);

function supprimer_dossier(string $dossier): void
{
    if ($dossier === '' || !is_dir($dossier) || !str_contains(str_replace('\\', '/', $dossier), '/storage/recette/')) {
        return;
    }
    $elements = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($elements as $element) {
        $element->isDir() ? @rmdir($element->getPathname()) : @unlink($element->getPathname());
    }
    @rmdir($dossier);
}

final class Navigateur
{
    private string $jar;

    public function __construct(public string $nom, string $isole)
    {
        $this->jar = $isole . '/sessions/cookies-' . preg_replace('/[^a-z0-9-]/i', '', $nom) . '.txt';
    }

    /** @return array{code: int, location: string, body: string, recette: string} */
    public function get(string $path): array
    {
        return self::lire($this->poignee('GET', $path));
    }

    public function post(string $path, array $data, bool $csrf = true): array
    {
        return self::lire($this->poignee('POST', $path, $csrf ? $data + ['_csrf' => $this->csrf()] : $data));
    }

    public function connexion(string $email, string $retour = ''): array
    {
        $this->get('/connexion/' . ($retour !== '' ? '?retour=' . rawurlencode($retour) : ''));

        return self::lire($this->poignee('POST', '/connexion/', ['email' => $email, 'password' => MOT_DE_PASSE, '_csrf' => $this->csrf('/connexion/')]));
    }

    /**
     * Envoie plusieurs POST EN MÊME TEMPS (curl_multi), chacun avec son jeton préparé à l'avance.
     *
     * @param list<array{0: self, 1: string, 2: array}> $requetes
     * @return list<array{code: int, location: string, body: string, recette: string}>
     */
    public static function simultane(array $requetes): array
    {
        $poignees = [];
        foreach ($requetes as [$navigateur, $path, $data]) {
            $poignees[] = $navigateur->poignee('POST', $path, $data + ['_csrf' => $navigateur->csrf()]);
        }
        $multi = curl_multi_init();
        foreach ($poignees as $poignee) {
            curl_multi_add_handle($multi, $poignee);
        }
        do {
            $etat = curl_multi_exec($multi, $actifs);
            if ($actifs) {
                curl_multi_select($multi);
            }
        } while ($actifs && $etat === CURLM_OK);
        $reponses = [];
        foreach ($poignees as $poignee) {
            $reponses[] = self::lire($poignee, curl_multi_getcontent($poignee));
            curl_multi_remove_handle($multi, $poignee);
        }
        curl_multi_close($multi);

        return $reponses;
    }

    /** Jeton CSRF de la session (lu sur une page qui affiche un formulaire). */
    private function csrf(string $page = '/gestion/'): string
    {
        $reponse = $this->get($page);
        if (!preg_match('/name="_csrf" value="([^"]+)"/', $reponse['body'], $m)) {
            preg_match('/name="_csrf" value="([^"]+)"/', $this->get('/connexion/')['body'], $m);
        }

        return html_entity_decode($m[1] ?? '');
    }

    private function poignee(string $method, string $path, array $data = []): CurlHandle
    {
        $curl = curl_init('http://127.0.0.1:' . PORT . $path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20,
        ]);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            $multipart = array_filter($data, static fn (mixed $value): bool => $value instanceof CURLFile);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
        }

        return $curl;
    }

    private static function lire(CurlHandle $curl, ?string $raw = null): array
    {
        $raw ??= (string) curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);
        $headers = substr($raw, 0, $size);
        preg_match('/^Location:\s*(.+)$/mi', $headers, $location);
        preg_match('/^X-Taysir-Recette:\s*(.+)$/mi', $headers, $recette);

        return ['code' => $code, 'location' => trim($location[1] ?? ''), 'body' => substr($raw, $size), 'recette' => trim($recette[1] ?? '')];
    }
}
