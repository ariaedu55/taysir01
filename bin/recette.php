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
 *       -d extension=fileinfo -d extension=curl bin/recette.php [--garder] [--sql-user=… --sql-password=…]
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
$env = array_merge(getenv(), ['TAYSIR_DB' => BASE]);
if ($sqlUser !== $config['user']) {
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
        echo ($ok ? '  OK    ' : '  ÉCHEC ') . str_pad($id, 5) . $scenario . ' → ' . $obtenu . "\n";
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
        ['conseillerA', '/gestion/rendez-vous/', '/gestion/', 'Conseiller vers Rendez-vous, module interdit (refusé)'],
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
    preg_match('#<script type="application/json" id="g-search-data">(.*?)</script>#s', $journee['body'], $m);
    $verifier('T7', 'Dossier de B absent de la recherche de A', isset($m[1]) && !str_contains($m[1], $R2['ref']) && str_contains($m[1], $R1['ref']), isset($m[1]) ? (str_contains($m[1], $R2['ref']) ? 'présent !' : 'absent') : 'index introuvable');
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

    /* R15 : rendez-vous réservés à l'administrateur et à la secrétaire (revue 1B, point 1) */
    foreach (['conseillerA', 'conseillerB', 'orientation'] as $cle) {
        $g = $nav[$cle]->get('/gestion/rendez-vous/');
        $p1 = $nav[$cle]->post('/gestion/rendez-vous/RDV-RCT-1/', ['status' => 'annule']);
        $p2 = $nav[$cle]->post('/admin/rendez-vous/RDV-RCT-1/', ['status' => 'annule']);
        $j = $nav[$cle]->get('/gestion/');
        $verifier('R15', ucfirst($cle) . ' : liste, modification et compteur de rendez-vous', $g['code'] === 403 && $p1['code'] === 403 && $p2['code'] === 403 && !str_contains($j['body'], '/gestion/rendez-vous/'), $g['code'] . ' / ' . $p1['code'] . ' / ' . $p2['code']);
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
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
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
