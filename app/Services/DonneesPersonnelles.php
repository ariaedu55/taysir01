<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;
use PDO;

/**
 * Données personnelles (phase 7, lot 7C ; docs/gestion-phase-7.md).
 * - exporter() : tout ce que Taysir garde sur une personne (et ses fiches fusionnées), pour une demande d'accès.
 * - anonymiser() : remplace identité, coordonnées et textes libres, supprime les fichiers des pièces ; garde les
 *   règlements et les reçus (obligation comptable) et les compteurs. Refusé tant qu'un dossier est en cours ou si la
 *   fiche est liée à un compte de l'équipe. Une seule transaction ; les fichiers sont supprimés après la validation.
 * - conservation() : fiches qui dépassent les durées proposées (à valider par l'agence), sans rien supprimer.
 */
final class DonneesPersonnelles
{
    public const TEXTE_EFFACE = '[Supprimé lors de l’anonymisation]';

    /** Durées proposées, en années (à faire valider par l'agence). */
    public const DUREES = ['prospect' => 2, 'dossier' => 5, 'pieces' => 1, 'comptabilite' => 10];

    /** @return list<int> La fiche et les fiches fusionnées dans elle */
    public static function fiches(int $personId): array
    {
        $s = Database::connection()->prepare('SELECT id FROM people WHERE id = ? OR merged_into = ?');
        $s->execute([$personId, $personId]);

        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function exporter(int $personId): array
    {
        $ids = self::fiches($personId);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $tout = static function (string $sql, array $params) : array {
            $s = Database::connection()->prepare($sql);
            $s->execute($params);

            return $s->fetchAll(PDO::FETCH_ASSOC);
        };
        $comptes = $tout("SELECT id, first_name, last_name, email, phone, profile, role, active, consent_at, email_verified_at, last_login_at, created_at FROM users WHERE person_id IN ($in)", $ids);
        $userIds = array_map('intval', array_column($comptes, 'id')) ?: [0];
        $inU = implode(',', array_fill(0, count($userIds), '?'));
        $dossiers = $tout("SELECT id, reference, service, status, civility, first_name, last_name, email, phone, birth_date, destination, intake, details, message, created_at, updated_at
            FROM service_requests WHERE person_id IN ($in) OR user_id IN ($inU)", [...$ids, ...$userIds]);
        $dossierIds = array_map('intval', array_column($dossiers, 'id')) ?: [0];
        $inD = implode(',', array_fill(0, count($dossierIds), '?'));

        return [
            'export' => ['genere_le' => gmdate('Y-m-d\TH:i:s\Z'), 'responsable' => 'Taysir Orientation Voyage', 'fiche' => $personId],
            'fiches' => $tout("SELECT id, first_name, last_name, email, phone, profile, birth_date, timezone, origin, created_at, updated_at, merged_into, erased_at FROM people WHERE id IN ($in)", $ids),
            'comptes' => $comptes,
            'dossiers' => $dossiers,
            'suivi_des_dossiers' => $tout("SELECT r.reference, e.type, e.status, e.message, e.created_at FROM request_events e JOIN service_requests r ON r.id = e.request_id WHERE e.request_id IN ($inD) AND e.visible = 1", $dossierIds),
            'pieces' => $tout("SELECT r.reference, d.original_name, d.mime, d.size, d.created_at FROM request_documents d JOIN service_requests r ON r.id = d.request_id WHERE d.request_id IN ($inD)", $dossierIds),
            'resultats_admission' => $tout("SELECT r.reference, a.institution, a.status, a.updated_at FROM request_admission_results a JOIN service_requests r ON r.id = a.request_id WHERE a.request_id IN ($inD)", $dossierIds),
            'reglements' => $tout("SELECT p.case_reference, p.receipt, p.amount, p.method, p.received_on, c.cancelled_at FROM finance_payments p LEFT JOIN finance_cancellations c ON c.payment_id = p.id WHERE p.request_id IN ($inD)", $dossierIds),
            'rendez_vous' => $tout("SELECT reference, motive, mode, first_name, last_name, email, phone, message, status, starts_at_utc, created_at FROM appointments WHERE person_id IN ($in) OR user_id IN ($inU)", [...$ids, ...$userIds]),
            'visites_agence' => $tout("SELECT first_name, last_name, phone, email, purpose, state, created_at FROM reception_visits WHERE person_id IN ($in)", $ids),
            'prospection' => $tout("SELECT p.level, p.state, s.name AS etablissement, c.name AS campagne, p.created_at FROM prospects p LEFT JOIN prospect_schools s ON s.id = p.school_id LEFT JOIN prospect_campaigns c ON c.id = p.campaign_id WHERE p.person_id IN ($in)", $ids),
            'messages_contact' => $tout("SELECT name, email, phone, message, status, created_at FROM contact_messages WHERE user_id IN ($inU)", $userIds),
            'messages_equipe' => $tout("SELECT channel, created_at FROM message_logs WHERE person_id IN ($in)", $ids),
            // Notes de l'équipe : elles concernent la personne et entrent dans son droit d'accès ; à relire avant l'envoi
            'notes_equipe' => $tout("SELECT body, created_at FROM notes WHERE person_id IN ($in)", $ids),
        ];
    }

    /**
     * @return int Nombre de fichiers supprimés
     * @throws DomainException Dossier en cours, compte de l'équipe, fiche fusionnée ou déjà anonymisée
     */
    public static function anonymiser(int $personId): int
    {
        $db = Database::connection();
        $personne = $db->prepare('SELECT * FROM people WHERE id = ?');
        $personne->execute([$personId]);
        $p = $personne->fetch(PDO::FETCH_ASSOC);
        if (!$p || $p['merged_into'] !== null) {
            throw new DomainException('Ouvrez la fiche principale : celle-ci a été fusionnée.');
        }
        if ($p['erased_at'] !== null) {
            throw new DomainException('Cette fiche est déjà anonymisée.');
        }
        $ids = self::fiches($personId);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $exec = static function (string $sql, array $params) use ($db): int {
            $s = $db->prepare($sql);
            $s->execute($params);

            return $s->rowCount();
        };
        $colonne = static function (string $sql, array $params) use ($db): array {
            $s = $db->prepare($sql);
            $s->execute($params);

            return $s->fetchAll(PDO::FETCH_COLUMN);
        };

        $db->beginTransaction();
        try {
            // Verrou sur les fiches : deux anonymisations ou une ouverture de dossier simultanées s'excluent
            $colonne("SELECT id FROM people WHERE id IN ($in) FOR UPDATE", $ids);
            $comptes = $db->prepare("SELECT id, role, email FROM users WHERE person_id IN ($in) FOR UPDATE");
            $comptes->execute($ids);
            $comptes = $comptes->fetchAll(PDO::FETCH_ASSOC);
            if (array_filter($comptes, static fn (array $u): bool => $u['role'] !== 'client') !== []) {
                throw new DomainException('Cette fiche est liée à un compte de l’équipe : désactivez le compte depuis « Équipe ».');
            }
            $userIds = array_map('intval', array_column($comptes, 'id')) ?: [0];
            $inU = implode(',', array_fill(0, count($userIds), '?'));
            $dossierIds = array_map('intval', $colonne("SELECT id FROM service_requests WHERE person_id IN ($in) OR user_id IN ($inU) FOR UPDATE", [...$ids, ...$userIds])) ?: [0];
            $inD = implode(',', array_fill(0, count($dossierIds), '?'));
            $enCours = $colonne("SELECT reference FROM service_requests WHERE id IN ($inD) AND status NOT IN ('terminee', 'annulee')", $dossierIds);
            if ($enCours !== []) {
                throw new DomainException('Dossier en cours (' . implode(', ', $enCours) . ') : terminez-le ou annulez-le d’abord.');
            }
            $emails = array_values(array_unique(array_filter(array_map('strtolower', [$p['email'] ?? '', ...array_column($comptes, 'email')]))));
            $nom = 'anonymisée n° ' . $personId;

            $exec("UPDATE people SET first_name = 'Personne', last_name = ?, email = NULL, phone = NULL, birth_date = NULL, import_ref = NULL, to_reconcile = 0, erased_at = UTC_TIMESTAMP() WHERE id IN ($in)", [$nom, ...$ids]);
            foreach ($userIds as $uid) {
                if ($uid > 0) {
                    // Adresse unique et non routable ; mot de passe impossible : le compte ne peut plus servir
                    $exec("UPDATE users SET first_name = 'Personne', last_name = ?, email = ?, phone = NULL, active = 0, password_hash = ? WHERE id = ?",
                        [$nom, 'anonyme-' . $uid . '@anonyme.invalid', '!' . bin2hex(random_bytes(16)), $uid]);
                }
            }
            $exec("DELETE FROM password_resets WHERE user_id IN ($inU)", $userIds);
            $exec("UPDATE service_requests SET civility = NULL, first_name = 'Personne', last_name = ?, email = NULL, phone = NULL, birth_date = NULL, details = NULL, message = NULL WHERE id IN ($inD)", [$nom, ...$dossierIds]);
            $exec("UPDATE request_events SET message = ? WHERE request_id IN ($inD) AND message IS NOT NULL", [self::TEXTE_EFFACE, ...$dossierIds]);
            $exec("UPDATE appointments SET first_name = 'Personne', last_name = ?, email = '', phone = '', message = NULL WHERE person_id IN ($in) OR user_id IN ($inU)", [$nom, ...$ids, ...$userIds]);
            $exec("UPDATE appointment_events e JOIN appointments a ON a.id = e.appointment_id SET e.message = ? WHERE (a.person_id IN ($in) OR a.user_id IN ($inU)) AND e.message IS NOT NULL", [self::TEXTE_EFFACE, ...$ids, ...$userIds]);
            $exec("UPDATE reception_visits SET first_name = 'Personne', last_name = ?, phone = NULL, email = NULL, purpose = ? WHERE person_id IN ($in)", [$nom, self::TEXTE_EFFACE, ...$ids]);
            $exec("UPDATE prospect_events e JOIN prospects p ON p.id = e.prospect_id SET e.detail = ? WHERE p.person_id IN ($in)", [self::TEXTE_EFFACE, ...$ids]);
            $exec("UPDATE notes SET body = ? WHERE person_id IN ($in)", [self::TEXTE_EFFACE, ...$ids]);
            $exec("UPDATE tasks SET title = 'Tâche anonymisée' WHERE person_id IN ($in) OR request_id IN ($inD)", [...$ids, ...$dossierIds]);
            $exec("UPDATE contact_messages SET name = ?, email = '', phone = '', message = ? WHERE user_id IN ($inU)", ['Personne ' . $nom, self::TEXTE_EFFACE, ...$userIds]);
            if ($emails !== []) {
                $inE = implode(',', array_fill(0, count($emails), '?'));
                $exec("UPDATE contact_messages SET name = ?, email = '', phone = '', message = ? WHERE LOWER(email) IN ($inE)", ['Personne ' . $nom, self::TEXTE_EFFACE, ...$emails]);
                $exec("UPDATE mail_outbox SET recipient = '', recipient_name = NULL, subject = ?, html = '', text = '' WHERE LOWER(recipient) IN ($inE) AND status <> 'en-attente'", [self::TEXTE_EFFACE, ...$emails]);
                $exec("DELETE FROM mail_outbox WHERE LOWER(recipient) IN ($inE) AND status = 'en-attente'", $emails);
            }
            $fichiers = $colonne("SELECT stored_name FROM request_documents WHERE request_id IN ($inD)", $dossierIds);
            // La ligne reste (elle est liée aux pièces demandées) ; le fichier est supprimé après la validation
            $exec("UPDATE request_documents SET original_name = 'Pièce supprimée (anonymisation)' WHERE request_id IN ($inD)", $dossierIds);
            // Règlements et reçus gardés (comptabilité, 10 ans) : finance_payments.customer_name n'est pas touché
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $supprimes = 0;
        foreach ($fichiers as $f) {
            $chemin = DocumentStorage::directory() . '/' . basename((string) $f);
            if (is_file($chemin) && @unlink($chemin)) {
                $supprimes++;
            }
        }

        return $supprimes;
    }

    /**
     * Fiches au-delà des durées proposées ($today : date de l'agence). Rien n'est supprimé ici.
     *
     * @return array{prospects: list<array>, dossiers: list<array>, pieces: list<array>}
     */
    public static function conservation(string $today): array
    {
        $avant = static fn (int $ans): string => (new \DateTimeImmutable($today))->modify("-{$ans} years")->format('Y-m-d 00:00:00');
        $tout = static function (string $sql, array $params): array {
            $s = Database::connection()->prepare($sql);
            $s->execute($params);

            return $s->fetchAll(PDO::FETCH_ASSOC);
        };

        return [
            // Prospect sans dossier ni suivi depuis 2 ans
            'prospects' => $tout("SELECT pe.id, pe.first_name, pe.last_name, MAX(COALESCE(ev.created_at, pr.created_at)) AS derniere
                FROM prospects pr JOIN people pe ON pe.id = pr.person_id AND pe.erased_at IS NULL AND pe.merged_into IS NULL
                LEFT JOIN prospect_events ev ON ev.prospect_id = pr.id
                WHERE NOT EXISTS (SELECT 1 FROM service_requests r WHERE r.person_id = pe.id)
                GROUP BY pe.id, pe.first_name, pe.last_name HAVING derniere < ? ORDER BY derniere LIMIT 200", [$avant(self::DUREES['prospect'])]),
            // Personne dont tous les dossiers sont clos depuis 5 ans
            'dossiers' => $tout("SELECT pe.id, pe.first_name, pe.last_name, MAX(r.updated_at) AS derniere
                FROM people pe JOIN service_requests r ON r.person_id = pe.id
                WHERE pe.erased_at IS NULL AND pe.merged_into IS NULL
                GROUP BY pe.id, pe.first_name, pe.last_name
                HAVING SUM(r.status NOT IN ('terminee', 'annulee')) = 0 AND derniere < ? ORDER BY derniere LIMIT 200", [$avant(self::DUREES['dossier'])]),
            // Pièces de dossiers clos depuis plus d'un an (pièces d'identité, justificatifs)
            'pieces' => $tout("SELECT r.reference, r.person_id, r.first_name, r.last_name, COUNT(d.id) AS nombre, r.updated_at AS derniere
                FROM service_requests r JOIN request_documents d ON d.request_id = r.id
                JOIN people pe ON pe.id = r.person_id AND pe.erased_at IS NULL
                WHERE r.status IN ('terminee', 'annulee') AND r.updated_at < ? AND d.original_name <> 'Pièce supprimée (anonymisation)'
                GROUP BY r.id, r.reference, r.person_id, r.first_name, r.last_name, r.updated_at ORDER BY r.updated_at LIMIT 200", [$avant(self::DUREES['pieces'])]),
        ];
    }
}
