<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Journal d'audit (table audit_log, docs/gestion-plan.md §8) : accès et modifications sensibles.
 * Jamais de contenu de pièce, de mot de passe ni de jeton dans le détail.
 */
final class Audit
{
    public const ACTIONS = [
        'connexion' => 'Connexion',
        'acces_refuse' => 'Accès refusé',
        'piece_telechargee' => 'Pièce téléchargée',
        'affectation' => 'Affectation de dossier',
        'poste_change' => 'Changement de poste',
        'compte_desactive' => 'Compte désactivé',
        'compte_reactive' => 'Compte réactivé',
        'mission_attribuee' => 'Mission attribuée',
        'mission_retiree' => 'Mission retirée',
        'membre_invite' => 'Membre invité',
    ];

    /** @param array<string, scalar|null> $detail */
    public static function log(string $action, ?string $subjectType = null, ?string $subjectRef = null, array $detail = [], ?int $userId = null): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO audit_log (user_id, action, subject_type, subject_ref, detail, ip) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $userId ?? Auth::id(),
                $action,
                $subjectType,
                $subjectRef,
                $detail === [] ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            ]);
        } catch (\PDOException $exception) {
            // Le journal ne doit jamais bloquer le service, mais l'échec doit se voir (revue 1B) : journaux du serveur
            // et fichier d'alerte dédié, à surveiller (storage/logs/audit-echecs.log, une ligne par échec).
            error_log('Journal d’audit indisponible : ' . $exception->getMessage());
            $dossier = BASE_PATH . '/storage/logs';
            if (is_dir($dossier) || @mkdir($dossier, 0770, true)) {
                @file_put_contents($dossier . '/audit-echecs.log', sprintf("%s\t%s\t%s\t%s\t%s\n",
                    date('c'), $action, (string) $subjectType, (string) $subjectRef, str_replace(["\n", "\t"], ' ', $exception->getMessage())), FILE_APPEND | LOCK_EX);
            }
        }
    }

    /** Dernières lignes du journal, avec l'auteur. */
    public static function latest(?string $action = null, int $limit = 200): array
    {
        $sql = 'SELECT l.*, u.first_name, u.last_name, u.role FROM audit_log l LEFT JOIN users u ON u.id = l.user_id';
        $params = [];
        if ($action !== null && isset(self::ACTIONS[$action])) {
            $sql .= ' WHERE l.action = ?';
            $params[] = $action;
        }
        $statement = Database::connection()->prepare($sql . ' ORDER BY l.id DESC LIMIT ' . max(1, min(500, $limit)));
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
