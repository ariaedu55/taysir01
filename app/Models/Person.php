<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Access;

/**
 * Fiche personne (table people, migration 011 ; docs/gestion-phase-2.md, 2A et condition C5).
 * Une personne existe avec ou sans compte ; ses demandes (= dossiers, D1) et ses rendez-vous lui sont rattachés.
 * Toute lecture côté équipe passe par le périmètre d'Access::personnesSql().
 */
final class Person extends Model
{
    protected static string $table = 'people';

    public const ORIGINES = [
        'site' => 'Site web',
        'visite' => 'Visite à l’agence',
        'prospect' => 'Prospection',
        'import' => 'Import',
        'reprise' => 'Reprise des données',
    ];

    public const PROFILS = ['eleve' => 'Élève', 'etudiant' => 'Étudiant(e)', 'parent' => 'Parent', 'particulier' => 'Particulier'];

    /** Taille d'une page de liste et nombre maximal de résultats de recherche (P17). */
    public const PAGE = 30;
    public const RECHERCHE_MAX = 20;

    public static function create(array $data, string $origin, ?int $createdBy, bool $toReconcile = false): array
    {
        static::query(
            'INSERT INTO people (first_name, last_name, email, phone, profile, birth_date, timezone, agency_id, origin, to_reconcile, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['first_name'], $data['last_name'], self::emailOuNull($data['email'] ?? null), ($data['phone'] ?? '') !== '' ? $data['phone'] : null,
                $data['profile'] ?? null, $data['birth_date'] ?? null, $data['timezone'] ?? 'Africa/Dakar', $data['agency_id'] ?? null,
                $origin, $toReconcile ? 1 : 0, $createdBy,
            ]
        );

        return static::find((int) static::db()->lastInsertId());
    }

    /** L'e-mail est-il déjà connu d'une fiche ? (marque « à rapprocher », jamais de rattachement automatique à un compte) */
    public static function emailConnu(?string $email): bool
    {
        $email = self::emailOuNull($email);

        return $email !== null && (bool) static::query('SELECT 1 FROM people WHERE LOWER(TRIM(email)) = ? LIMIT 1', [$email])->fetchColumn();
    }

    /** Personne d'un compte client ; la crée à la volée (compte créé avant 011 ou à l'inscription). */
    public static function pourCompte(array $user): array
    {
        if (!empty($user['person_id']) && ($person = static::find((int) $user['person_id'])) !== null) {
            return $person;
        }
        $db = static::db();
        $db->beginTransaction();
        try {
            $personId = static::query('SELECT person_id FROM users WHERE id = ? FOR UPDATE', [$user['id']])->fetchColumn();
            if ($personId !== null) {
                if ($personId === false) {
                    throw new \RuntimeException('Compte introuvable.');
                }
                $person = static::find((int) $personId);
                $db->commit();
                return $person;
            }
            $person = static::create(
                ['first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'email' => $user['email'], 'phone' => $user['phone'] ?? '', 'profile' => $user['profile'] ?? null, 'agency_id' => $user['agency_id'] ?? null],
                'site',
                null,
                self::emailConnu($user['email'])
            );
            static::query('UPDATE users SET person_id = ?, updated_at = updated_at WHERE id = ?', [$person['id'], $user['id']]);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        return $person;
    }

    /**
     * Personne d'une réservation ou d'une demande SANS compte (C5) :
     * - rattachée à une personne SANS compte si l'e-mail et le nom normalisés désignent UNE SEULE fiche ;
     * - sinon nouvelle fiche, marquée « à rapprocher » si l'e-mail est déjà connu ;
     * - jamais rattachée à une personne qui a un compte (quelqu'un qui saisirait l'e-mail d'autrui).
     * La réponse publique ne dépend pas de ce choix.
     */
    public static function pourAnonyme(array $data): array
    {
        $email = self::emailOuNull($data['email'] ?? null);
        if ($email !== null) {
            $candidats = static::query(
                'SELECT p.id FROM people p
                 WHERE p.merged_into IS NULL AND LOWER(TRIM(p.email)) = ? AND LOWER(TRIM(p.first_name)) = ? AND LOWER(TRIM(p.last_name)) = ?
                   AND NOT EXISTS (SELECT 1 FROM users u WHERE u.person_id = p.id) LIMIT 2',
                [$email, self::norme($data['first_name']), self::norme($data['last_name'])]
            )->fetchAll(\PDO::FETCH_COLUMN);
            if (count($candidats) === 1) {
                return static::find((int) $candidats[0]);
            }
        }

        return static::create($data, 'site', null, self::emailConnu($email));
    }

    /** Fiche du périmètre du compte, ou null (hors périmètre ou inexistante). */
    public static function findScoped(array $user, int $id): ?array
    {
        [$sql, $params] = Access::personnesSql($user, 'p');
        $row = static::query("SELECT p.*, (SELECT u.id FROM users u WHERE u.person_id = p.id) AS account_id,
                (SELECT a.name FROM agencies a WHERE a.id = p.agency_id) AS agency_name,
                (SELECT CONCAT(c.first_name, ' ', c.last_name) FROM users c WHERE c.id = p.created_by) AS created_by_name
             FROM people p WHERE p.id = ? AND p.merged_into IS NULL AND ({$sql})", array_merge([$id], $params))->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Liste du périmètre, la plus récente d'abord ; $q filtre nom, e-mail ou téléphone (caractères % et _ échappés).
     *
     * @return array{0: list<array>, 1: int} Lignes de la page et total
     */
    public static function searchScoped(array $user, ?string $q, int $page = 1, int $limit = self::PAGE): array
    {
        [$sql, $params] = Access::personnesSql($user, 'p');
        [$filtre, $filtreParams] = self::filtre($q);
        $where = "WHERE p.merged_into IS NULL AND ({$sql}){$filtre}";
        $all = array_merge($params, $filtreParams);
        $total = (int) static::query("SELECT COUNT(*) FROM people p {$where}", $all)->fetchColumn();
        $offset = max(0, ($page - 1) * $limit);
        [$dossiersSql, $dossiersParams] = Access::perimetre($user, 'r');
        $rdvCount = 'NULL';
        $rdvParams = [];
        if (Access::peut($user, 'rendez-vous')) {
            [$rdvSql, $rdvParams] = Access::rendezVousSql($user);
            $rdvCount = "(SELECT COUNT(*) FROM appointments a WHERE a.person_id = p.id AND {$rdvSql})";
        }
        $rows = static::query(
            "SELECT p.*, (SELECT COUNT(*) FROM users u WHERE u.person_id = p.id) AS has_account,
                    (SELECT COUNT(*) FROM service_requests r WHERE r.person_id = p.id AND {$dossiersSql}) AS dossiers,
                    {$rdvCount} AS rendez_vous
             FROM people p {$where} ORDER BY p.updated_at DESC, p.id DESC LIMIT " . (int) $limit . ' OFFSET ' . $offset,
            array_merge($dossiersParams, $rdvParams, $all)
        )->fetchAll();

        return [$rows, $total];
    }

    /** Dossiers de la personne visibles par ce compte (périmètre des dossiers). */
    public static function dossiers(array $user, int $personId): array
    {
        [$sql, $params] = Access::perimetre($user, 'r');

        return static::query(
            "SELECT r.reference, r.service, r.status, r.destination, r.created_at FROM service_requests r
             WHERE r.person_id = ? AND {$sql} ORDER BY r.created_at DESC",
            array_merge([$personId], $params)
        )->fetchAll();
    }

    public static function rendezVous(array $user, int $personId): array
    {
        [$sql, $params] = Access::rendezVousSql($user);

        return static::query(
            "SELECT a.reference, a.motive, a.mode, a.preferred_date, a.preferred_slot, a.status, a.agency_id,
                    COALESCE(a.starts_at_utc, a.scheduled_at) AS scheduled_at
             FROM appointments a WHERE a.person_id = ? AND {$sql}
             ORDER BY COALESCE(a.starts_at_utc, a.scheduled_at, a.preferred_date) DESC, a.id DESC",
            array_merge([$personId], $params)
        )->fetchAll();
    }

    /** @return array{0: string, 1: list<string>} Condition LIKE échappée (P17) */
    public static function filtre(?string $q, string $alias = 'p'): array
    {
        $q = trim((string) $q);
        if ($q === '') {
            return ['', []];
        }
        $motif = '%' . addcslashes(mb_strtolower(mb_substr($q, 0, 80)), '\\%_') . '%';
        $chiffres = preg_replace('/\D/', '', $q);
        $sql = " AND (LOWER(CONCAT({$alias}.first_name, ' ', {$alias}.last_name)) LIKE ? OR LOWER(CONCAT({$alias}.last_name, ' ', {$alias}.first_name)) LIKE ? OR LOWER({$alias}.email) LIKE ?";
        $params = [$motif, $motif, $motif];
        if (strlen($chiffres) >= 4) {
            $sql .= " OR REPLACE({$alias}.phone, ' ', '') LIKE ?";
            $params[] = '%' . $chiffres . '%';
        }

        return [$sql . ')', $params];
    }

    private static function emailOuNull(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private static function norme(string $texte): string
    {
        return mb_strtolower(trim($texte));
    }
}
