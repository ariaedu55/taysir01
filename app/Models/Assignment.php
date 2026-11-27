<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Affectation d'un dossier par poste (table request_assignments, docs/gestion-plan.md §3.2, revue 1B point 2).
 * user_id NULL = dans la FILE du poste (transmis, pas encore pris). Le poste « conseiller » reste recopié dans
 * service_requests.assigned_to pour les écrans et e-mails existants.
 */
final class Assignment extends Model
{
    protected static string $table = 'request_assignments';

    private const LIBELLES = ['conseiller' => 'conseiller', 'orientation' => 'agent d’orientation', 'traitement' => 'agent de traitement'];

    /** @return array<string, array> Poste => affectation (user_id null si dans la file), avec le nom du responsable */
    public static function forRequest(int $requestId): array
    {
        $rows = static::query(
            'SELECT a.*, u.first_name, u.last_name, u.email, u.active FROM request_assignments a
             LEFT JOIN users u ON u.id = a.user_id WHERE a.request_id = ?',
            [$requestId]
        )->fetchAll();
        $byPoste = [];
        foreach ($rows as $row) {
            $byPoste[$row['poste']] = $row;
        }

        return $byPoste;
    }

    /**
     * PRISE pour soi, atomique : une seule prise réussit, sans jamais écraser une affectation existante.
     * Conseiller : INSERT contre la clé (dossier, poste), sinon UPDATE de la ligne en file.
     * Autres postes : UPDATE de la ligne en file uniquement (il faut avoir été transmis).
     *
     * @return bool false si quelqu'un l'a pris entre-temps (ou si le dossier n'est pas dans la file)
     */
    public static function prendre(array $request, string $poste, array $member): bool
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $pris = false;
            if ($poste === 'conseiller') {
                try {
                    static::query('INSERT INTO request_assignments (request_id, poste, user_id, assigned_by) VALUES (?, ?, ?, ?)',
                        [$request['id'], $poste, $member['id'], $member['id']]);
                    $pris = true;
                } catch (\PDOException $exception) {
                    if (($exception->errorInfo[1] ?? 0) !== 1062) {
                        throw $exception;
                    }
                }
            }
            if (!$pris) {
                $update = static::query(
                    'UPDATE request_assignments SET user_id = ?, assigned_by = ?, assigned_at = NOW() WHERE request_id = ? AND poste = ? AND user_id IS NULL',
                    [$member['id'], $member['id'], $request['id'], $poste]
                );
                $pris = $update->rowCount() === 1;
            }
            if (!$pris) {
                $db->rollBack();

                return false;
            }
            self::apres($request, $poste, (int) $member['id'], (int) $member['id'],
                $member['first_name'] . ' ' . $member['last_name'] . ' a pris le dossier (' . self::LIBELLES[$poste] . ').');
            $db->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * TRANSMISSION au poste suivant : à une personne ($member), ou dans la file du poste ($member null).
     * Les droits (qui transmet à quel poste, réaffectation réservée à l'administrateur) sont vérifiés par
     * l'appelant avec Access::transmissibles().
     */
    public static function transmettre(array $request, string $poste, ?array $member, int $authorId): void
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            static::query(
                'INSERT INTO request_assignments (request_id, poste, user_id, assigned_by, queued_at, queued_by) VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), assigned_by = VALUES(assigned_by), assigned_at = NOW(),
                     queued_at = VALUES(queued_at), queued_by = VALUES(queued_by)',
                [$request['id'], $poste, $member['id'] ?? null, $authorId, $member === null ? date('Y-m-d H:i:s') : null, $member === null ? $authorId : null]
            );
            self::apres($request, $poste, isset($member['id']) ? (int) $member['id'] : null, $authorId, $member !== null
                ? 'Dossier transmis à ' . $member['first_name'] . ' ' . $member['last_name'] . ' (' . self::LIBELLES[$poste] . ').'
                : 'Dossier mis dans la file des ' . self::LIBELLES[$poste] . 's.');
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    /**
     * Désactivation ou perte d'habilitation : les dossiers EN COURS tenus par ce membre (sur $postes, ou tous)
     * retournent dans la file de leur poste, avec une trace interne dans chaque historique.
     *
     * @param list<string>|null $postes
     * @return list<string> Références remises dans la file
     */
    public static function remettreEnFile(int $userId, int $authorId, ?array $postes = null): array
    {
        $sql = "SELECT a.request_id, a.poste, r.reference FROM request_assignments a JOIN service_requests r ON r.id = a.request_id
                WHERE a.user_id = ? AND r.status NOT IN ('terminee', 'annulee')";
        $params = [$userId];
        if ($postes !== null) {
            if ($postes === []) {
                return [];
            }
            $sql .= ' AND a.poste IN (' . implode(', ', array_fill(0, count($postes), '?')) . ')';
            array_push($params, ...$postes);
        }
        $rows = static::query($sql, $params)->fetchAll();
        $db = static::db();
        $db->beginTransaction();
        try {
            foreach ($rows as $row) {
                static::query('UPDATE request_assignments SET user_id = NULL, queued_at = NOW(), queued_by = ? WHERE request_id = ? AND poste = ?',
                    [$authorId, $row['request_id'], $row['poste']]);
                self::apres(['id' => $row['request_id']], $row['poste'], null, $authorId,
                    'Responsable indisponible (compte désactivé ou poste changé) : dossier remis dans la file des ' . self::LIBELLES[$row['poste']] . 's.');
            }
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        return array_values(array_unique(array_column($rows, 'reference')));
    }

    /** Recopie du conseiller et trace interne dans l'historique du dossier. */
    private static function apres(array $request, string $poste, ?int $userId, int $authorId, string $texte): void
    {
        if ($poste === 'conseiller') {
            static::query('UPDATE service_requests SET assigned_to = ? WHERE id = ?', [$userId, $request['id']]);
        }
        static::query("INSERT INTO request_events (request_id, user_id, type, message, visible) VALUES (?, ?, 'note', ?, 0)", [$request['id'], $authorId, $texte]);
    }
}
