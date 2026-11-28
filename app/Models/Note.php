<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Access;

/**
 * Notes et comptes rendus d'entretien sur une PERSONNE (migration 014 ; docs/gestion-phase-2.md, 2C, E1 à E5, N1 à N4).
 * - Visibilité « interne » ou « partagée », choisie à chaque note, jamais modifiée ensuite (aucune modification ni
 *   suppression : une erreur se corrige par une nouvelle note).
 * - Lecture côté équipe par Access::notesVisiblesSql() uniquement ; côté client, notes partagées de la personne du compte.
 */
final class Note extends Model
{
    protected static string $table = 'notes';

    public const VISIBILITES = ['interne' => 'Interne', 'partagee' => 'Partagée'];
    public const LONGUEUR_MAX = 5000;

    /** Note libre sur une personne. Les droits sont contrôlés par l'appelant (Access::peutEcrireNote). */
    public static function create(int $personId, string $visibility, string $body, array $author, string $poste): array
    {
        static::query(
            'INSERT INTO notes (person_id, visibility, body, author_id, author_poste) VALUES (?, ?, ?, ?, ?)',
            [$personId, $visibility, $body, (int) $author['id'], $poste]
        );

        return static::find((int) static::db()->lastInsertId());
    }

    /**
     * Compte rendu d'un entretien (E5, N3), en une transaction : rendez-vous verrouillé ; responsable lui-même ou
     * administrateur ; statut `confirme` (qui passe à `honore`) ou déjà `honore` ; heure de début passée (UTC) ;
     * au plus un compte rendu (index unique).
     *
     * @throws \DomainException avec le motif du refus
     */
    public static function compteRendu(array $author, string $poste, int $appointmentId, string $visibility, string $body): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $rdv = static::query('SELECT * FROM appointments WHERE id = ? FOR UPDATE', [$appointmentId])->fetch();
            if ($rdv === false) {
                throw new \DomainException('Rendez-vous introuvable.');
            }
            $admin = in_array('admin', Access::postes($author), true);
            if ($rdv['assigned_to'] === null || (!$admin && (int) $rdv['assigned_to'] !== (int) $author['id'])) {
                throw new \DomainException('Seul le responsable du rendez-vous rédige son compte rendu.');
            }
            if (!in_array($rdv['status'], ['confirme', 'honore'], true)) {
                throw new \DomainException('Un compte rendu ne se rédige que pour un rendez-vous confirmé ou honoré.');
            }
            if ($rdv['starts_at_utc'] === null || $rdv['starts_at_utc'] > gmdate('Y-m-d H:i:s')) {
                throw new \DomainException('Le compte rendu se rédige après le début de l’entretien.');
            }
            if ($rdv['person_id'] === null) {
                throw new \DomainException('Ce rendez-vous n’est rattaché à aucune fiche.');
            }
            if (static::query('SELECT 1 FROM notes WHERE appointment_id = ?', [$appointmentId])->fetchColumn()) {
                throw new \DomainException('Ce rendez-vous a déjà son compte rendu. Ajoutez une note sur la fiche.');
            }
            static::query(
                'INSERT INTO notes (person_id, appointment_id, visibility, body, author_id, author_poste) VALUES (?, ?, ?, ?, ?, ?)',
                [(int) $rdv['person_id'], $appointmentId, $visibility, $body, (int) $author['id'], $poste]
            );
            $id = (int) $db->lastInsertId();
            if ($rdv['status'] === 'confirme') {
                static::query("UPDATE appointments SET status = 'honore' WHERE id = ?", [$appointmentId]);
                static::query("INSERT INTO appointment_events (appointment_id, user_id, type, status, message) VALUES (?, ?, 'statut', 'honore', 'Compte rendu rédigé')", [$appointmentId, (int) $author['id']]);
            }
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            if ($exception instanceof \PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new \DomainException('Ce rendez-vous a déjà son compte rendu. Ajoutez une note sur la fiche.');
            }
            throw $exception;
        }

        return static::find($id);
    }

    /** Notes d'une personne lisibles par ce membre de l'équipe, les plus récentes d'abord. */
    public static function forPerson(array $user, int $personId): array
    {
        [$sql, $params] = Access::notesVisiblesSql($user, 'n');

        return static::query(
            "SELECT n.*, u.first_name AS author_first_name, u.last_name AS author_last_name,
                    a.reference AS appointment_reference, a.starts_at_utc AS appointment_start, a.agency_id AS appointment_agency
             FROM notes n JOIN users u ON u.id = n.author_id LEFT JOIN appointments a ON a.id = n.appointment_id
             WHERE n.person_id = ? AND {$sql} ORDER BY n.created_at DESC, n.id DESC",
            [$personId, ...$params]
        )->fetchAll();
    }

    /** Comptes rendus PARTAGÉS de la personne du compte client (E3) : prénom et poste de l'auteur seulement (N4). */
    public static function partageesPourClient(int $userId): array
    {
        return static::query(
            "SELECT n.id, n.body, n.created_at, n.author_poste, u.first_name AS author_first_name, a.reference AS appointment_reference
             FROM notes n JOIN users u ON u.id = n.author_id LEFT JOIN appointments a ON a.id = n.appointment_id
             WHERE n.visibility = 'partagee' AND n.person_id = (SELECT cu.person_id FROM users cu WHERE cu.id = ? AND cu.person_id IS NOT NULL)
             ORDER BY n.created_at DESC, n.id DESC",
            [$userId]
        )->fetchAll();
    }

    /** Compte rendu déjà rédigé pour ce rendez-vous ? */
    public static function existePourRendezVous(int $appointmentId): bool
    {
        return (bool) static::query('SELECT 1 FROM notes WHERE appointment_id = ?', [$appointmentId])->fetchColumn();
    }
}
