<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Person;
use App\Models\ProcedureTemplate;
use App\Models\User;

/**
 * Ouverture d'un dossier par l'équipe depuis la fiche personne (docs/gestion-phase-2.md, 2C, E7, N6, N7, N10).
 *
 * En UNE transaction : le dossier (service_requests, D1), l'affectation « conseiller » (le conseiller qui ouvre, ou
 * pour l'administrateur un conseiller actif choisi ou la file), la copie des étapes de la version publiée choisie
 * (ou aucune étape « sans modèle »), et l'événement d'ouverture, interne (visible = 0).
 * Double soumission : le jeton d'ouverture est une colonne UNIQUE ; un second envoi du même formulaire retrouve le
 * dossier déjà créé au lieu d'en créer un autre.
 * Les droits (Access::peutOuvrirDossier) sont contrôlés par l'appelant ; tout le reste est revérifié ici.
 */
final class OuvertureDossier
{
    /**
     * @param array{service: string, destination: string, purpose?: string, auto?: bool, intake: string, template_id: ?int, conseiller_id: ?int, token: string} $data
     * @return array{0: array, 1: bool} Dossier, et true s'il vient d'être créé (false : renvoi du même formulaire)
     * @throws \DomainException motif du refus
     */
    public static function ouvrir(array $ouvreur, array $personne, array $data): array
    {
        $db = Database::connection();
        if (!preg_match('/^[a-f0-9]{32}$/D', $data['token'])) {
            throw new \DomainException('Formulaire expiré : rechargez la fiche.');
        }
        if (($existant = self::parJeton($data['token'])) !== null) {
            return [$existant, false];
        }
        $services = array_column(config('services'), 'title', 'key');
        if (!isset($services[$data['service']])) {
            throw new \DomainException('Choisissez un service.');
        }
        // Phase 12 : motif propre au service (sinon ignoré) ; « automatique » = la démarche publiée la plus précise
        $motif = isset(Demarches::motifs($data['service'])[$data['purpose'] ?? '']) ? $data['purpose'] : null;
        $template = null;
        if (!empty($data['auto'])) {
            $template = Demarches::choisir($data['service'], $data['destination'] !== '' ? $data['destination'] : null, $motif);
        } elseif ($data['template_id'] !== null) {
            $template = ProcedureTemplate::get($data['template_id']);
            if ($template === null || $template['status'] !== 'publie') {
                throw new \DomainException('Ce modèle n’est pas publié : choisissez un modèle publié, ou « sans modèle ».');
            }
        }
        $admin = in_array('admin', Access::postes($ouvreur), true);
        $conseiller = null;
        if (!$admin) {
            $conseiller = $ouvreur; // E7 : le dossier naît affecté au conseiller qui l'ouvre
        } elseif ($data['conseiller_id'] !== null) {
            $conseiller = User::find($data['conseiller_id']);
            if ($conseiller === null || empty($conseiller['active']) || !in_array('conseiller', Access::postes($conseiller), true)) {
                throw new \DomainException('Choisissez un conseiller actif, ou la file des conseillers.');
            }
        }
        $compte = $db->prepare("SELECT id FROM users WHERE person_id = ? AND role = 'client' LIMIT 1");
        $compte->execute([(int) $personne['id']]);
        $userId = $compte->fetchColumn();

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO service_requests (reference, user_id, person_id, template_id, service, first_name, last_name, email, phone,
                    destination, purpose, intake, opened_by, open_token, details)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                'TMP-' . bin2hex(random_bytes(6)), $userId === false ? null : (int) $userId, (int) $personne['id'], $template['id'] ?? null,
                $data['service'], $personne['first_name'], $personne['last_name'], $personne['email'] ?: null, $personne['phone'] ?: null,
                $data['destination'] !== '' ? $data['destination'] : null, $motif, $data['intake'] !== '' ? $data['intake'] : null,
                (int) $ouvreur['id'], $data['token'], json_encode(new \stdClass()),
            ]);
            $id = (int) $db->lastInsertId();
            $reference = sprintf('TAY-%s-%06d', date('Y'), $id);
            $db->prepare('UPDATE service_requests SET reference = ? WHERE id = ?')->execute([$reference, $id]);
            if ($conseiller !== null) {
                $db->prepare("INSERT INTO request_assignments (request_id, poste, user_id, assigned_by) VALUES (?, 'conseiller', ?, ?)")
                    ->execute([$id, (int) $conseiller['id'], (int) $ouvreur['id']]);
                $db->prepare('UPDATE service_requests SET assigned_to = ? WHERE id = ?')->execute([(int) $conseiller['id'], $id]);
            }
            if ($template !== null) {
                Demarches::appliquer($db, $id, $template); // étapes et pièces attendues (phase 12)
            }
            $message = 'Dossier ouvert par ' . $ouvreur['first_name'] . ' ' . $ouvreur['last_name']
                . ($template !== null ? ' avec le modèle « ' . $template['title'] . ' » (version ' . $template['version'] . ')' : ' sans modèle')
                . ($conseiller === null ? ', dans la file des conseillers.' : '.');
            $db->prepare("INSERT INTO request_events (request_id, user_id, type, status, message, visible) VALUES (?, ?, 'creation', 'nouvelle', ?, 0)")
                ->execute([$id, (int) $ouvreur['id'], $message]);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            // Second envoi simultané du même formulaire : l'index unique du jeton a refusé ; on rend le dossier du premier
            if ($exception instanceof \PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062 && ($existant = self::parJeton($data['token'])) !== null) {
                return [$existant, false];
            }
            throw $exception;
        }
        Audit::log('dossier_ouvert', 'dossier', $reference, ['personne' => (int) $personne['id'], 'modele' => $template['id'] ?? null, 'conseiller' => $conseiller['id'] ?? 'file']);

        return [self::parJeton($data['token']), true];
    }

    private static function parJeton(string $token): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM service_requests WHERE open_token = ?');
        $statement->execute([$token]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
