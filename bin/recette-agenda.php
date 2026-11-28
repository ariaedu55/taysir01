<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Appointment;
use App\Models\User;

// Usage : php bin/recette-agenda.php (PHP CLI avec pdo_mysql, mbstring et MySQL/MariaDB local).
// TAYSIR_DB_USER / TAYSIR_DB_PASSWORD : compte SQL facultatif, limité aux bases de recette.
const CONFLIT_AGENDA = 10;

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require dirname(__DIR__) . '/app/bootstrap.php';

/** Attend une condition sans bloquer indéfiniment si un participant échoue. */
function attendreAgenda(callable $condition, string $message): void
{
    $limite = hrtime(true) + 8_000_000_000;
    do {
        if ($condition()) {
            return;
        }
        usleep(20_000);
    } while (hrtime(true) < $limite);
    throw new RuntimeException($message);
}

function baseAgenda(string $base): PDO
{
    $db = Database::connection();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $base) {
        throw new RuntimeException('La connexion applicative ne cible pas la base de recette.');
    }
    return $db;
}

function lancerAgenda(array $arguments, array $sorties, array $env)
{
    $php = require __DIR__ . '/php-enfant.php';
    $processus = proc_open([...$php, ...$arguments], $sorties, $pipes, BASE_PATH, $env);
    if ($processus === false) {
        throw new RuntimeException('Impossible de lancer le processus PHP de recette.');
    }
    return $processus;
}

/** Deux appels distincts doivent être simultanément bloqués sur la ligne du responsable. */
function courseAgenda(PDO $db, array $env, string $dossier, int $acteur, int $responsable, array $creneaux): int
{
    $processus = [];
    $codes = [];
    $connexions = [];
    $db->beginTransaction();
    try {
        $verrou = $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $verrou->execute([$responsable]);
        $verrou->closeCursor();

        foreach ($creneaux as $rdv => [$debut, $fin]) {
            $journal = "$dossier/$rdv.log";
            $processus[$rdv] = lancerAgenda(
                [__FILE__, '--participant', (string) $acteur, (string) $rdv, (string) $responsable, $debut, $fin],
                [1 => ['file', $journal, 'w'], 2 => ['redirect', 1]],
                $env
            );
        }

        attendreAgenda(static function () use ($db, $dossier, $processus, &$connexions): bool {
            foreach ($processus as $rdv => $enfant) {
                if (!proc_get_status($enfant)['running']) {
                    throw new RuntimeException("Participant $rdv arrêté avant la contention : " . trim(file_get_contents("$dossier/$rdv.log")));
                }
                if (!isset($connexions[$rdv]) && is_file("$dossier/$rdv.connexion")) {
                    $id = trim(file_get_contents("$dossier/$rdv.connexion"));
                    if (ctype_digit($id)) {
                        $connexions[$rdv] = (int) $id;
                    }
                }
            }
            // MySQL et MariaDB montrent les connexions du même compte sans privilège PROCESS.
            $verrous = [];
            foreach ($db->query('SHOW FULL PROCESSLIST') as $ligne) {
                if (preg_match('/\bFROM\s+`?users`?\b.*\bFOR\s+UPDATE\b/is', $ligne['Info'] ?? '')) {
                    $verrous[] = (int) $ligne['Id'];
                }
            }
            return count(array_unique($connexions)) === 2 && count(array_intersect($connexions, $verrous)) === 2;
        }, 'Les deux appels ne se sont pas retrouvés ensemble sur le verrou SQL du responsable.');

        $db->commit();
        attendreAgenda(static function () use ($processus, &$codes): bool {
            foreach ($processus as $rdv => $enfant) {
                if (!isset($codes[$rdv])) {
                    $etat = proc_get_status($enfant);
                    if (!$etat['running']) {
                        $codes[$rdv] = $etat['exitcode'];
                    }
                }
            }
            return count($codes) === count($processus);
        }, 'Les deux transactions ne se sont pas terminées après la libération du verrou.');

        $gagnants = array_keys($codes, 0, true);
        $refus = array_keys($codes, CONFLIT_AGENDA, true);
        if (count($gagnants) !== 1 || count($refus) !== 1) {
            $details = [];
            foreach ($codes as $rdv => $code) {
                $details[] = "RDV $rdv (code $code) " . trim(file_get_contents("$dossier/$rdv.log"));
            }
            throw new RuntimeException('Attendu : une confirmation et une DomainException. ' . implode(' | ', $details));
        }
        $gagnant = $gagnants[0];
        verifierConfirmationAgenda($gagnant, $responsable, ...$creneaux[$gagnant]);
        $perdant = Appointment::find($refus[0]);
        if ($perdant === null || $perdant['status'] !== 'demande' || $perdant['assigned_to'] !== null
            || $perdant['starts_at_utc'] !== null || $perdant['ends_at_utc'] !== null) {
            throw new RuntimeException('Le rendez-vous refusé a été modifié en base.');
        }
        return $gagnant;
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($processus as $enfant) {
            if (proc_get_status($enfant)['running']) {
                proc_terminate($enfant);
            }
            proc_close($enfant);
        }
    }
}

function verifierConfirmationAgenda(int $rdv, int $responsable, string $debut, string $fin): void
{
    $ligne = Appointment::find($rdv);
    if ($ligne === null || $ligne['status'] !== 'confirme' || (int) $ligne['assigned_to'] !== $responsable
        || $ligne['starts_at_utc'] !== $debut || $ligne['ends_at_utc'] !== $fin) {
        throw new RuntimeException("La confirmation du rendez-vous $rdv ne correspond pas au responsable et à l'intervalle attendus.");
    }
}

$configuration = config('database');
$code = 2;
$creee = false;
$dossier = null;
try {
    if (!extension_loaded('pdo_mysql') || !extension_loaded('mbstring') || !function_exists('proc_open')) {
        throw new RuntimeException('PHP CLI doit fournir pdo_mysql, mbstring et proc_open.');
    }
    if (!in_array($configuration['host'], ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('La recette agenda exige un serveur SQL local.');
    }
    if (!is_callable([Appointment::class, 'planifier']) || !glob(BASE_PATH . '/database/migrations/012_*.sql')) {
        throw new RuntimeException('Prérequis 2B manquant : Appointment::planifier() et la migration 012 doivent être présents.');
    }

    if (($argv[1] ?? null) === '--participant') {
        $base = (string) getenv('TAYSIR_DB');
        $dossierParticipant = (string) getenv('TAYSIR_AGENDA_SYNC');
        if (count($argv) !== 7 || !preg_match('/^taysir_agenda_[a-f0-9]{16}_recette$/D', $base)
            || !is_dir($dossierParticipant) || basename($dossierParticipant) !== $base) {
            throw new RuntimeException('Participant hors du contexte de recette.');
        }
        $db = baseAgenda($base);
        $db->exec('SET SESSION innodb_lock_wait_timeout = 12');
        $acteur = User::find((int) $argv[2]) ?? throw new RuntimeException('Acteur fictif introuvable.');
        $rdv = (int) $argv[3];
        file_put_contents("$dossierParticipant/$rdv.connexion", (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
        try {
            Appointment::planifier($acteur, $rdv, 'confirme', (int) $argv[4], $argv[5], $argv[6]);
            exit(0);
        } catch (DomainException $exception) {
            fwrite(STDERR, $exception->getMessage() . "\n");
            exit(CONFLIT_AGENDA);
        }
    }
    // R1 : participant de la course anti-abus. Création volontairement lente À L'INTÉRIEUR du verrou :
    // sans verrou, les six participants passeraient tous la vérification avant que le premier soit compté.
    if (($argv[1] ?? null) === '--limiteur') {
        $base = (string) getenv('TAYSIR_DB');
        if (count($argv) !== 4 || !preg_match('/^taysir_agenda_[a-f0-9]{16}_recette$/D', $base) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $argv[3])) {
            throw new RuntimeException('Participant anti-abus hors du contexte de recette.');
        }
        baseAgenda($base);
        // Barrière : signaler « prêt », puis attendre le départ commun (sinon les démarrages de PHP s'échelonnent)
        $sync = (string) getenv('TAYSIR_AGENDA_SYNC');
        file_put_contents($sync . '/pret-' . getmypid(), '1');
        attendreAgenda(static fn (): bool => is_file($sync . '/depart'), 'Départ de la course jamais donné.');
        try {
            App\Services\BookingLimiter::proteger('198.51.100.77', $argv[2], static function () use ($argv): array {
                $rdv = Appointment::create([
                    'motive' => 'renseignement', 'mode' => 'telephone', 'preferred_date' => $argv[3], 'preferred_slot' => 'matin',
                    'first_name' => 'Course', 'last_name' => 'Limiteur', 'email' => $argv[2], 'phone' => '770000000', 'message' => null,
                ], null);
                usleep(300_000);

                return $rdv;
            });
            exit(0);
        } catch (App\Services\LimiteAtteinte) {
            exit(CONFLIT_AGENDA);
        }
    }
    if (count($argv) !== 1) {
        throw new RuntimeException('Usage : php bin/recette-agenda.php');
    }

    $code = 1;
    $base = 'taysir_agenda_' . bin2hex(random_bytes(8)) . '_recette';
    $env = array_merge(getenv(), ['TAYSIR_DB' => $base]);
    $user = (string) getenv('TAYSIR_DB_USER');
    $portRecette = getenv('TAYSIR_DB_PORT');
    $chaine = sprintf('mysql:host=%s;port=%d;charset=%s', $configuration['host'], is_string($portRecette) && ctype_digit($portRecette) ? (int) $portRecette : $configuration['port'], $configuration['charset']);
    $serveur = new PDO(
        $chaine,
        $user !== '' ? $user : $configuration['user'],
        $user !== '' ? (string) getenv('TAYSIR_DB_PASSWORD') : $configuration['password']
    );
    // CREATE sans IF NOT EXISTS : seul le processus qui a créé cette base peut la supprimer.
    $serveur->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $creee = true;
    $chemin = BASE_PATH . '/storage/recette/' . $base;
    if (!mkdir($chemin, 0770, true)) {
        throw new RuntimeException('Impossible de créer le dossier de recette.');
    }
    $dossier = $chemin;
    $env['TAYSIR_AGENDA_SYNC'] = $dossier;
    putenv('TAYSIR_DB=' . $base);
    $migration = lancerAgenda([BASE_PATH . '/database/migrate.php'], [1 => STDOUT, 2 => STDERR], $env);
    if (proc_close($migration) !== 0) {
        throw new RuntimeException('Échec des migrations de la base isolée.');
    }

    $db = baseAgenda($base);
    $agence = (int) $db->query("SELECT id FROM agencies WHERE code = 'dakar'")->fetchColumn();
    $insert = $db->prepare("INSERT INTO users (first_name, last_name, email, role, agency_id, active, password_hash, consent_at, last_login_at)
        VALUES (?, 'Recette', ?, ?, ?, 1, ?, NOW(), NOW())");
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $comptes = [];
    foreach (['admin' => 'admin', 'alpha' => 'conseiller', 'beta' => 'conseiller'] as $nom => $role) {
        $insert->execute([$nom, "$nom@recette.test", $role, $agence, $hash]);
        $comptes[$nom] = (int) $db->lastInsertId();
    }
    $acteur = User::find($comptes['admin']);
    $jour = (new DateTimeImmutable('next monday', new DateTimeZone('UTC')))->modify('+1 week')->format('Y-m-d');
    $rdvs = [];
    for ($numero = 1; $numero <= 6; $numero++) {
        $rdvs[$numero] = (int) Appointment::create([
            'motive' => 'renseignement', 'mode' => 'agence-dakar', 'preferred_date' => $jour,
            'preferred_slot' => 'matin', 'first_name' => 'Personne', 'last_name' => "Fictive $numero",
            'email' => "rdv$numero@recette.test", 'phone' => '770000000', 'message' => null,
        ], null)['id'];
    }

    $alpha = $comptes['alpha'];
    $beta = $comptes['beta'];
    courseAgenda($db, $env, $dossier, $comptes['admin'], $alpha, [
        $rdvs[1] => ["$jour 09:00:00", "$jour 09:30:00"],
        $rdvs[2] => ["$jour 09:00:00", "$jour 09:30:00"],
    ]);
    echo "OK — même début, même responsable : une confirmation.\n";

    $gagnant = courseAgenda($db, $env, $dossier, $comptes['admin'], $alpha, [
        $rdvs[3] => ["$jour 10:00:00", "$jour 10:30:00"],
        $rdvs[4] => ["$jour 10:15:00", "$jour 10:45:00"],
    ]);
    echo "OK — débuts différents, même responsable : une confirmation.\n";

    $fin = $gagnant === $rdvs[3] ? '10:30:00' : '10:45:00';
    Appointment::planifier($acteur, $rdvs[5], 'confirme', $alpha, "$jour $fin", "$jour 11:15:00");
    verifierConfirmationAgenda($rdvs[5], $alpha, "$jour $fin", "$jour 11:15:00");
    Appointment::planifier($acteur, $rdvs[6], 'confirme', $beta, "$jour 10:00:00", "$jour 10:30:00");
    verifierConfirmationAgenda($rdvs[6], $beta, "$jour 10:00:00", "$jour 10:30:00");
    echo "OK — créneaux contigus et responsables distincts.\n";

    $deplace = Appointment::planifier($acteur, $gagnant, 'confirme', $alpha, "$jour 10:10:00", "$jour 10:25:00");
    if (!$deplace || Appointment::find($gagnant)['status'] !== 'reporte') {
        throw new RuntimeException('Changer l’heure d’une confirmation doit produire un report.');
    }
    $gagnant = (int) $deplace['id'];
    verifierConfirmationAgenda($gagnant, $alpha, "$jour 10:10:00", "$jour 10:25:00");
    echo "OK — replanification d'un rendez-vous sur son propre créneau.\n";

    try {
        Appointment::planifier($acteur, $gagnant, 'reporte', $alpha, "$jour 11:00:00", "$jour 11:30:00");
        throw new RuntimeException('Un report en conflit a été accepté.');
    } catch (DomainException) {
        verifierConfirmationAgenda($gagnant, $alpha, "$jour 10:10:00", "$jour 10:25:00");
    }
    $remplacement = Appointment::planifier($acteur, $gagnant, 'reporte', $alpha, "$jour 12:00:00", "$jour 12:30:00");
    $ancien = Appointment::find($gagnant);
    if ($ancien['status'] !== 'reporte' || (int) $ancien['rescheduled_to_id'] !== (int) $remplacement['id']
        || $ancien['starts_at_utc'] !== "$jour 10:10:00" || $ancien['person_id'] !== $remplacement['person_id']) {
        throw new RuntimeException('Le report doit préserver l’ancien rendez-vous et le relier au nouveau.');
    }
    verifierConfirmationAgenda((int) $remplacement['id'], $alpha, "$jour 12:00:00", "$jour 12:30:00");
    echo "OK — report lié et préservation de l’ancien rendez-vous en cas de conflit.\n";

    $db->prepare("INSERT INTO availability_rules (user_id, agency_id, weekday, starts_at, ends_at, mode) VALUES (?, ?, 1, '14:00', '16:00', 'agence')")
        ->execute([$alpha, $agence]);
    $db->prepare('INSERT INTO availability_exceptions (user_id, starts_at_utc, ends_at_utc) VALUES (?, ?, ?)')
        ->execute([$alpha, "$jour 15:00:00", "$jour 15:30:00"]);
    $data = [
        'motive' => 'orientation-filieres', 'mode' => 'agence-dakar', 'preferred_date' => $jour,
        'preferred_slot' => 'apres-midi', 'first_name' => 'Réservation', 'last_name' => 'Recette',
        'email' => 'direct@recette.test', 'phone' => '770000000', 'message' => null,
    ];
    $slots = Appointment::slots($jour, $data['motive'], $data['mode']);
    if (array_keys($slots) !== ['14:00', '14:15']) {
        throw new RuntimeException('Les plages et absences doivent filtrer les créneaux de 45 minutes.');
    }
    $direct = Appointment::create($data, null, '14:00');
    verifierConfirmationAgenda((int) $direct['id'], $alpha, "$jour 14:00:00", "$jour 14:45:00");
    try {
        Appointment::create($data, null, '14:15');
        throw new RuntimeException('Une seconde réservation qui chevauche la première a été acceptée.');
    } catch (DomainException) {
        echo "OK — disponibilité publique, absence, durée par motif et créneau devenu indisponible.\n";
    }
    $link = Appointment::guestCancellationLink($direct);
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
    $token = $query['jeton'] ?? '';
    if (Appointment::cancelGuest(str_repeat('0', 64)) !== null
        || !Appointment::cancelGuest($token) || Appointment::cancelGuest($token) !== null
        || !isset(Appointment::slots($jour, $data['motive'], $data['mode'])['14:00'])) {
        throw new RuntimeException('Le lien invité doit être unique et libérer le créneau.');
    }
    echo "OK — annulation sans compte, jeton invalide et rejeu refusés.\n";

    Appointment::setDuration('orientation-filieres', 60);
    $apres = Appointment::slots($jour, $data['motive'], $data['mode']);
    if (array_keys($apres) !== ['14:00'] || $apres['14:00'][$alpha][1] !== "$jour 15:00:00") {
        throw new RuntimeException('Le calcul doit respecter la nouvelle durée.');
    }
    echo "OK — durée modifiée prise en compte par le calcul.\n";

    // B1 (C6) : limites anti-abus côté serveur, seuils par défaut, compteurs isolés dans le dossier de recette
    for ($i = 1; $i <= 5; $i++) {
        App\Services\BookingLimiter::record('203.0.113.7', "ip$i@recette.test");
    }
    $ipBloquee = App\Services\BookingLimiter::check('203.0.113.7', 'nouvelle@recette.test');
    $autreIp = App\Services\BookingLimiter::check('203.0.113.8', 'nouvelle@recette.test');
    foreach (['198.51.100.1', '198.51.100.2', '198.51.100.3'] as $ip) {
        App\Services\BookingLimiter::record($ip, 'meme@recette.test');
    }
    $emailBloque = App\Services\BookingLimiter::check('198.51.100.9', ' MEME@recette.test ');
    for ($i = 1; $i <= 3; $i++) {
        Appointment::create([
            'motive' => 'renseignement', 'mode' => 'telephone', 'preferred_date' => $jour, 'preferred_slot' => 'matin',
            'first_name' => 'Plafond', 'last_name' => "Actif $i", 'email' => 'plafond@recette.test', 'phone' => '770000000', 'message' => null,
        ], null);
    }
    $actifsBloques = App\Services\BookingLimiter::check('192.0.2.1', 'plafond@recette.test');
    $fichiers = implode('', array_map('file_get_contents', glob($dossier . '/rendez-vous/*.json') ?: []) ?: ['']) . implode('', array_map('basename', glob($dossier . '/rendez-vous/*') ?: []));
    if ($ipBloquee !== App\Services\BookingLimiter::MESSAGE || $autreIp !== null || $emailBloque !== App\Services\BookingLimiter::MESSAGE
        || $actifsBloques !== App\Services\BookingLimiter::MESSAGE || str_contains($fichiers, '203.0.113.7') || str_contains($fichiers, 'meme@')) {
        throw new RuntimeException('Les limites par IP, par e-mail et de rendez-vous actifs doivent refuser avec le même message, sans rien stocker en clair.');
    }
    echo "OK — anti-abus : 5 envois par IP et par heure, 3 par e-mail, 3 rendez-vous actifs ; même message ; rien en clair.\n";

    // Alias « +… », IPv6 par /64, plafond de 15 sur 24 h (seuil horaire relevé ici seulement, en recette)
    foreach (['alias+1@recette.test', 'ALIAS+2@recette.test', ' alias@recette.test'] as $i => $adresse) {
        App\Services\BookingLimiter::record('198.18.0.' . ($i + 1), $adresse);
    }
    $alias = App\Services\BookingLimiter::check('198.18.0.9', 'alias+nouveau@recette.test');
    for ($i = 1; $i <= 5; $i++) {
        App\Services\BookingLimiter::record('2001:db8:aa:bb::' . $i, "v6-$i@recette.test");
    }
    $v6 = App\Services\BookingLimiter::check('2001:db8:aa:bb:ffff::99', 'v6-nouveau@recette.test');
    $v6Autre = App\Services\BookingLimiter::check('2001:db8:aa:cc::1', 'v6-nouveau@recette.test');
    putenv('RDV_LIMITE_IP_HEURE=100');
    for ($i = 1; $i <= 15; $i++) {
        App\Services\BookingLimiter::record('203.0.113.50', "jour$i@recette.test");
    }
    $jourBloque = App\Services\BookingLimiter::check('203.0.113.50', 'jour-nouveau@recette.test');
    putenv('RDV_LIMITE_IP_HEURE');
    $cle = $dossier . '/rendez-vous/.cle';
    if ($alias !== App\Services\BookingLimiter::MESSAGE || $v6 !== App\Services\BookingLimiter::MESSAGE || $v6Autre !== null
        || $jourBloque !== App\Services\BookingLimiter::MESSAGE || !preg_match('/^[a-f0-9]{64}$/D', (string) @file_get_contents($cle))) {
        throw new RuntimeException('Alias, IPv6 /64, plafond sur 24 h ou clé HMAC incorrects.');
    }
    echo "OK — anti-abus : alias « +… » regroupés, IPv6 par /64, 15 envois sur 24 h, empreintes HMAC à clé secrète.\n";

    // R1 : six processus PHP distincts, lancés ensemble, même e-mail (limite de 3 par heure) → 3 réussites exactement
    $nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $course = [];
    for ($i = 0; $i < 6; $i++) {
        $course[] = lancerAgenda([__FILE__, '--limiteur', 'course@recette.test', $jour], [1 => ['file', $nul, 'w'], 2 => ['file', $nul, 'w']], $env);
    }
    attendreAgenda(static fn (): bool => count(glob($dossier . '/pret-*') ?: []) === 6, 'Les six participants ne sont pas prêts.');
    file_put_contents($dossier . '/depart', '1');
    $codesCourse = array_map('proc_close', $course);
    $reussites = count(array_filter($codesCourse, static fn (int $c): bool => $c === 0));
    $refus = count(array_filter($codesCourse, static fn (int $c): bool => $c === CONFLIT_AGENDA));
    $enBase = (int) $db->query("SELECT COUNT(*) FROM appointments WHERE email = 'course@recette.test'")->fetchColumn();
    if ($reussites !== 3 || $refus !== 3 || $enBase !== 3) {
        throw new RuntimeException("Course anti-abus : $reussites réussite(s), $refus refus, $enBase en base (attendu 3, 3, 3).");
    }
    echo "OK — anti-abus en concurrence réelle : 6 processus simultanés, 3 acceptés et 3 refusés, 3 en base.\n";

    // P10 bis (B2) : une agence hors UTC+0, de part et d'autre du prochain changement d'heure de Paris.
    // Dans la base de recette seulement, l'agence de Ziguinchor passe à Europe/Paris (Paris n'est pas encore ouverte au public).
    $paris = new DateTimeZone('Europe/Paris');
    $transitions = $paris->getTransitions(time() + 3 * 86400, time() + 175 * 86400);
    $changement = $transitions[1] ?? null;
    if ($changement === null) {
        throw new RuntimeException('Aucun changement d’heure à Paris dans les six prochains mois.');
    }
    $bascule = (new DateTimeImmutable('@' . $changement['ts']))->setTimezone($paris);
    $avant = $bascule->modify('-2 days')->format('Y-m-d');
    $apres = $bascule->modify('+2 days')->format('Y-m-d');
    $db->exec("UPDATE agencies SET timezone = 'Europe/Paris', active = 1 WHERE code = 'ziguinchor'");
    $zig = (int) $db->query("SELECT id FROM agencies WHERE code = 'ziguinchor'")->fetchColumn();
    $insert->execute(['gamma', 'gamma@recette.test', 'conseiller', $zig, $hash]);
    $gamma = (int) $db->lastInsertId();
    $regle = $db->prepare("INSERT INTO availability_rules (user_id, agency_id, weekday, starts_at, ends_at, mode) VALUES (?, ?, ?, '10:00:00', '10:30:00', 'agence')");
    foreach ([$avant, $apres] as $jourTest) {
        $regle->execute([$gamma, $zig, (int) (new DateTimeImmutable($jourTest))->format('N')]);
    }
    $attendu = static fn (string $jourTest): string => (new DateTimeImmutable("$jourTest 10:00:00", $paris))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $sAvant = Appointment::slots($avant, 'renseignement', 'agence-ziguinchor', $gamma);
    $sApres = Appointment::slots($apres, 'renseignement', 'agence-ziguinchor', $gamma);
    $decalages = [substr($attendu($avant), 11, 5), substr($attendu($apres), 11, 5)];
    if (array_keys($sAvant) !== ['10:00'] || array_keys($sApres) !== ['10:00']
        || $sAvant['10:00'][$gamma][0] !== $attendu($avant) || $sApres['10:00'][$gamma][0] !== $attendu($apres)
        || $decalages[0] === $decalages[1]
        || Appointment::local($attendu($apres), Appointment::zone($zig), 'H:i') !== '10:00') {
        throw new RuntimeException('P10 bis : les créneaux doivent rester à 10:00 locales de part et d’autre du changement d’heure.');
    }
    echo "OK — P10 bis : 10:00 à Paris = {$decalages[0]} UTC le $avant et {$decalages[1]} UTC le $apres ; affichage local exact.\n";
    $code = 0;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Recette agenda : ' . $exception->getMessage() . "\n");
} finally {
    if ($creee) {
        try {
            $serveur->exec("DROP DATABASE `$base`");
        } catch (PDOException $exception) {
            fwrite(STDERR, "Impossible de supprimer la base de recette $base : " . $exception->getMessage() . "\n");
            $code = 1;
        }
    }
    if ($dossier !== null) {
        $fichiers = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($fichiers as $fichier) {
            $supprime = $fichier->isDir() && !$fichier->isLink() ? rmdir($fichier->getPathname()) : unlink($fichier->getPathname());
            if (!$supprime) {
                $code = 1;
            }
        }
        if (!rmdir($dossier)) {
            $code = 1;
        }
    }
}
exit($code);
