<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\DocumentStorage;

// Requiert un serveur PHP de recette et sa base vide, migrée. Voir docs/validations/2026-11-28-parcours-tuteur.md.
if (PHP_SAPI !== 'cli') {
    exit(2);
}
$root = dirname(__DIR__, 2);
$recette = require $root . '/config/recette.php';
$port = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
if ($recette === null || $port === false) {
    fwrite(STDERR, "Indiquez TAYSIR_DB (base de recette isolée) et le port HTTP du serveur de recette.\n");
    exit(2);
}
require $root . '/app/bootstrap.php';
restore_exception_handler();

const TUTOR_TEST_PASSWORD = 'Mot-de-passe-fictif-recette';

function tutorCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "ÉCHEC — {$message}\n");
        exit(1);
    }
    echo "OK — {$message}\n";
}

function tutorBrowser(int $port, string $base): Closure
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_COOKIEFILE => '',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    return static function (string $path, ?array $data = null) use ($curl, $port, $base): array {
        curl_setopt($curl, CURLOPT_URL, 'http://127.0.0.1:' . $port . $path);
        if ($data === null) {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $raw = curl_exec($curl);
        if ($raw === false) {
            fwrite(STDERR, curl_error($curl) . "\n");
            exit(1);
        }
        $size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        if (!preg_match('/^X-Taysir-Recette: ' . preg_quote($base, '/') . '\r?$/mi', substr($raw, 0, $size))) {
            fwrite(STDERR, "Le serveur ne correspond pas à la base de recette.\n");
            exit(2);
        }

        return [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), substr($raw, $size)];
    };
}

function tutorCsrf(array $response): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $response[1], $matches)) {
        throw new RuntimeException('Jeton CSRF introuvable, HTTP ' . $response[0]);
    }

    return $matches[1];
}

function tutorSql(PDO $db, string $file): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    foreach (preg_split('/;\s*$/m', $sql, -1, PREG_SPLIT_NO_EMPTY) as $statement) {
        if (trim($statement) !== '') {
            $db->query($statement)->closeCursor();
        }
    }
}

$visitor = tutorBrowser($port, $recette['base']);
tutorCheck($visitor('/connexion/')[0] === 200, 'Serveur de recette identifié');
$db = Database::connection();
tutorCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0
    && (int) $db->query('SELECT COUNT(*) FROM service_requests')->fetchColumn() === 0, 'Base de recette vide');
$migration = '019_acces_tuteurs.sql';
$rollback = $root . '/database/rollback/019_acces_tuteurs.down.sql';
tutorSql($db, $rollback);
tutorCheck((int) $db->query("SELECT COUNT(*) FROM migrations WHERE name = '{$migration}'")->fetchColumn() === 0, 'Retour arrière sur une table vide');
tutorSql($db, $root . '/database/migrations/' . $migration);
$db->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$migration]);

$db->exec("INSERT INTO people (first_name, last_name, birth_date) VALUES ('Titulaire', 'Fictif', CURDATE() - INTERVAL 16 YEAR)");
$person = (int) $db->lastInsertId();
$accounts = [];
$insert = $db->prepare('INSERT INTO users (person_id, first_name, last_name, email, role, profile, active, password_hash, consent_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
$hash = password_hash(TUTOR_TEST_PASSWORD, PASSWORD_DEFAULT);
foreach ([
    'admin' => [null, 'admin', 'particulier', 1],
    'conseiller' => [null, 'conseiller', 'particulier', 1],
    'titulaire' => [$person, 'client', 'eleve', 1],
    'parent' => [null, 'client', 'parent', 1],
    'autre' => [null, 'client', 'particulier', 1],
    'inactif' => [null, 'client', 'parent', 0],
] as $name => [$personId, $role, $profile, $active]) {
    $insert->execute([$personId, $name, 'Fictif', $name . '@recette.test', $role, $profile, $active, $hash]);
    $accounts[$name] = (int) $db->lastInsertId();
}
$insert = $db->prepare("INSERT INTO service_requests (reference, person_id, user_id, service, first_name, last_name, email, phone, details)
                        VALUES (?, ?, ?, 'visa', 'Titulaire', 'Fictif', 'parent@recette.test', '770000001', ?)");
$insert->execute(['TAY-TUTEUR-1', $person, $accounts['titulaire'], '{"identity_number":"IDENTITE-PRIVEE-RECETTE"}']);
$request = (int) $db->lastInsertId();
$insert->execute(['TAY-TUTEUR-2', $person, $accounts['titulaire'], '{}']);
$otherRequest = (int) $db->lastInsertId();
$insert->execute(['TAY-TUTEUR-PARENT', null, $accounts['parent'], '{}']);
$db->exec("INSERT INTO request_requirements (request_id, title) VALUES ({$request}, 'Pièce ciblée'), ({$otherRequest}, 'Autre pièce')");
[$requirement, $foreignRequirement] = $db->query('SELECT id FROM request_requirements ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$db->exec("INSERT INTO request_events (request_id, user_id, type, message, visible) VALUES
            ({$request}, {$accounts['admin']}, 'note', 'SUIVI-PARTAGE-RECETTE', 1),
            ({$request}, {$accounts['admin']}, 'note', 'INTERNE-DOSSIER-RECETTE', 0)");
$db->exec("INSERT INTO appointments (reference, user_id, person_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone)
           VALUES ('RDV-TUTEUR-RECETTE', {$accounts['titulaire']}, {$person}, 'visa', 'telephone', CURDATE(), 'matin', 'Titulaire', 'Fictif', 'titulaire@recette.test', '770000001')");
$appointment = (int) $db->lastInsertId();
$db->exec("INSERT INTO notes (person_id, appointment_id, visibility, body, author_id, author_poste)
           VALUES ({$person}, {$appointment}, 'partagee', 'COMPTE-RENDU-RECETTE', {$accounts['admin']}, 'admin')");

$vault = DocumentStorage::directory();
if (!is_dir($vault)) {
    mkdir($vault, 0770, true);
}
$file = $vault . '/tutor-fixture.pdf';
$pdf = "%PDF-1.4\nPièce fictive de recette\n";
file_put_contents($file, $pdf);
$db->prepare("INSERT INTO request_documents (request_id, original_name, stored_name, mime, size)
              VALUES (?, 'tutor-fixture.pdf', 'tutor-fixture.pdf', 'application/pdf', ?), (?, 'tutor-fixture.pdf', 'tutor-fixture.pdf', 'application/pdf', ?)")
    ->execute([$request, strlen($pdf), $otherRequest, strlen($pdf)]);
[$document, $foreignDocument] = $db->query('SELECT id FROM request_documents ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

$home = '/mon-espace/';
$list = $home . 'demandes/';
$view = $list . 'TAY-TUTEUR-1/';
$adminPage = '/gestion/dossiers/TAY-TUTEUR-1/tuteurs/';
$nav = [];
foreach (['admin', 'conseiller', 'titulaire', 'parent', 'autre'] as $name) {
    $browser = tutorBrowser($port, $recette['base']);
    tutorCheck($browser('/connexion/', [
        '_csrf' => tutorCsrf($browser('/connexion/')), 'email' => $name . '@recette.test', 'password' => TUTOR_TEST_PASSWORD,
    ])[0] === 303, 'Connexion de ' . $name);
    $nav[$name] = $browser;
}
$admin = $nav['admin'];
$parent = $nav['parent'];
$owner = $nav['titulaire'];
$other = $nav['autre'];
$adminToken = tutorCsrf($admin('/gestion/'));
$parentToken = tutorCsrf($parent($home));
$ownerToken = tutorCsrf($owner($home));
$staffToken = tutorCsrf($nav['conseiller']('/gestion/'));
$parentId = $accounts['parent'];
$grant = static fn (int $id, bool $verified, bool $deposit = false, ?string $path = null): array => $admin($path ?? $adminPage, [
    '_csrf' => $adminToken, 'parent_user_id' => $id, 'verified' => (int) $verified, 'can_deposit' => (int) $deposit,
    'verification_reference' => 'TUT-RECETTE-01',
]);
$active = static fn (): int => (int) $db->query('SELECT id FROM request_tutor_access WHERE revoked_at IS NULL')->fetchColumn();
$download = $view . 'documents/' . $document . '/';
$deposit = static fn (int $id, int $previous = 0): array => $parent($view . 'pieces-attendues/' . $id . '/', [
    '_csrf' => $parentToken, 'previous_document_id' => $previous, 'documents[0]' => new CURLFile($file, 'application/pdf'),
]);

tutorCheck($parent($view)[0] === 404 && $parent($download)[0] === 404, 'Un e-mail commun ne donne aucun accès');
tutorCheck($nav['conseiller']($adminPage)[0] === 403
    && $nav['conseiller']($adminPage, ['_csrf' => $staffToken, 'parent_user_id' => $parentId, 'verified' => 1])[0] === 403, 'Gestion réservée à l’administrateur');
tutorCheck($admin($adminPage, ['parent_user_id' => $parentId, 'verified' => 1])[0] === 419, 'Protection CSRF de l’octroi');
tutorCheck(str_contains($admin('/gestion/dossiers/TAY-TUTEUR-1/')[1], $adminPage), 'La fiche de gestion donne accès au parcours administrateur');
tutorCheck(str_contains($admin($adminPage . '?compte=' . $parentId)[1], 'parent Fictif'), 'Identification du compte par son identifiant');
tutorCheck($grant($parentId, false)[0] === 303 && $grant($accounts['inactif'], true)[0] === 303
    && $grant($accounts['autre'], true)[0] === 303 && $grant($parentId, true, false, '/gestion/dossiers/TAY-TUTEUR-PARENT/tuteurs/')[0] === 303
    && $active() === 0, 'Refus sans attestation, pour un compte inadmissible ou déjà titulaire');
tutorCheck($grant($parentId, true)[0] === 303 && ($firstAccess = $active()) > 0, 'Octroi de la lecture seule');
$grant($parentId, true, true);
tutorCheck((int) $db->query('SELECT COUNT(*) FROM request_tutor_access')->fetchColumn() === 1, 'Un nouvel envoi ne modifie pas un accès actif');
[$status, $body] = $parent($view);
$listing = $parent($list)[1];
tutorCheck($status === 200 && str_contains($body, 'SUIVI-PARTAGE-RECETTE') && str_contains($body, 'Lecture seule')
    && str_contains($listing, 'TAY-TUTEUR-1') && !str_contains($listing, 'TAY-TUTEUR-2'), 'Le tuteur retrouve le dossier partagé et sa permission');
tutorCheck(!str_contains($body, 'INTERNE-DOSSIER-RECETTE') && !str_contains($body, 'IDENTITE-PRIVEE-RECETTE')
    && !str_contains($listing, 'RDV-TUTEUR-RECETTE') && !str_contains($parent($home)[1], 'COMPTE-RENDU-RECETTE')
    && $parent($home . 'rendez-vous/RDV-TUTEUR-RECETTE/annuler/', ['_csrf' => $parentToken])[0] === 403, 'Notes internes, données du formulaire et rendez-vous restent cloisonnés');
tutorCheck($parent($download) === [200, $pdf] && $parent($view . 'documents/' . $foreignDocument . '/')[0] === 404
    && $other($view)[0] === 404, 'Téléchargement limité au dossier autorisé et à son compte parent');
tutorCheck($deposit((int) $requirement)[0] === 403 && $parent($view, ['_csrf' => $parentToken, 'message' => 'Refusé'])[0] === 404,
    'La lecture seule refuse le dépôt, et le tuteur ne peut pas répondre ou déposer librement');
$revoke = $view . 'tuteurs/' . $firstAccess . '/revoquer/';
tutorCheck(str_contains($owner($view)[1], $revoke) && !str_contains($body, $revoke), 'Le formulaire de retrait est réservé au titulaire');
tutorCheck($parent($revoke, ['_csrf' => $parentToken])[0] === 404 && $owner($revoke, [])[0] === 419, 'La révocation exige la propriété et un jeton CSRF');
tutorCheck($owner($revoke, ['_csrf' => $ownerToken])[0] === 303 && $active() === 0
    && $parent($download)[0] === 404 && !str_contains($parent($list)[1], 'TAY-TUTEUR-1'), 'Révocation immédiate par le titulaire malgré une date déclarative');

tutorCheck($grant($parentId, true, true)[0] === 303 && ($secondAccess = $active()) > $firstAccess, 'Nouvel octroi avec option de dépôt');
tutorCheck($owner($revoke, ['_csrf' => $ownerToken])[0] === 404 && $active() === $secondAccess, 'Une ancienne révocation ne retire pas le nouvel accès');
tutorCheck($deposit((int) $foreignRequirement)[0] === 404 && $deposit((int) $requirement)[0] === 303
    && $db->query("SELECT status FROM request_requirements WHERE id = {$requirement}")->fetchColumn() === 'deposee', 'Le dépôt vise uniquement une pièce attendue du dossier autorisé');
$db->exec("UPDATE users SET profile = 'particulier' WHERE id = {$parentId}");
tutorCheck($parent($view)[0] === 404 && $parent($download)[0] === 404, 'Un changement de profil suspend les autorisations');
$db->exec("UPDATE users SET profile = 'parent' WHERE id = {$parentId}");
$db->exec("INSERT INTO request_requirements (request_id, title) VALUES ({$request}, 'Pièce à fournir avant clôture')");
$closedRequirement = (int) $db->lastInsertId();
$db->exec("UPDATE service_requests SET status = 'terminee' WHERE id = {$request}");
tutorCheck($deposit($closedRequirement)[0] === 303 && (int) $db->query("SELECT COUNT(*) FROM request_documents WHERE requirement_id = {$closedRequirement}")->fetchColumn() === 0,
    'Un dossier fermé refuse tout nouveau dépôt');
tutorCheck($admin($adminPage . $secondAccess . '/revoquer/', ['_csrf' => $adminToken])[0] === 303
    && $active() === 0 && $parent($view)[0] === 404 && $parent($download)[0] === 404
    && $deposit((int) $requirement)[0] === 404 && $owner($view)[0] === 200, 'Révocation administrateur et maintien du droit du titulaire');
tutorCheck((int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action IN ('acces_tuteur_accorde', 'acces_tuteur_revoque')")->fetchColumn() === 4, 'Octrois et retraits journalisés');
try {
    tutorSql($db, $rollback);
    tutorCheck(false, 'Le rollback refuse de supprimer l’historique');
} catch (PDOException $exception) {
    tutorCheck((int) ($exception->errorInfo[1] ?? 0) === 1242
        && (int) $db->query('SELECT COUNT(*) FROM request_tutor_access')->fetchColumn() === 2, 'Le rollback préserve l’historique');
}
