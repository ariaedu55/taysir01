<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Appointment;
use App\Models\Prospect;

/**
 * Données de la coquille de l'espace équipe (gabarit layouts/espace.php) pour le compte connecté :
 * navigation permise, compteurs DU PÉRIMÈTRE, index de recherche limité au périmètre, raccourcis mobiles.
 * Voir docs/gestion-phase-1.md (1B) et docs/gestion-plan.md §3.4.
 */
final class Coquille
{
    /** @param string $actif Module de la page affichée (gestion, dossiers, rendez-vous…) */
    public static function pour(array $user, string $actif): array
    {
        $compteurs = self::compteurs($user);
        $nav = [];
        foreach (Access::navigation($user) as [$titre, $famille, $items]) {
            $nav[] = [$titre, $famille, array_map(static fn (array $item): array => [
                'label' => $item[0],
                'icone' => $item[1],
                'url' => $item[2],
                'actif' => $item[3] === $actif,
                'compteur' => $compteurs[$item[3]] ?? null,
            ], $items)];
        }

        return [
            'prenom' => $user['first_name'],
            'nom' => $user['last_name'],
            'intitule' => Access::libelle($user['role']),
            'agence' => self::agence($user),
            'nav' => $nav,
            'actif' => $actif,
            'raccourci' => Access::peut($user, 'dossiers') ? ['Dossiers', 'folder', '/gestion/dossiers/', 'dossiers']
                : (Access::peut($user, 'prospects') ? ['Prospects', 'target', '/gestion/prospects/', 'prospects'] : null),
            // Recherche globale côté serveur (/gestion/recherche/, 2A) : plus d'index embarqué dans la page
            'recherche' => Access::peut($user, 'dossiers') || Access::peut($user, 'personnes') || Access::peut($user, 'prospects'),
        ];
    }

    /** @return array<string, int> Module => nombre en attente, calculé sur le seul périmètre du compte */
    public static function compteurs(array $user): array
    {
        $db = Database::connection();
        $compteurs = [];
        if (Access::peut($user, 'dossiers')) {
            [$sql, $params] = Access::perimetre($user, 'r');
            $statement = $db->prepare("SELECT COUNT(*) FROM service_requests r WHERE r.status NOT IN ('terminee', 'annulee') AND {$sql}");
            $statement->execute($params);
            $compteurs['dossiers'] = (int) $statement->fetchColumn();
        }
        if (Access::peut($user, 'rendez-vous')) {
            $compteurs['rendez-vous'] = (int) (Appointment::countsByStatus($user)['demande'] ?? 0);
        }
        if (Access::peut($user, 'messages')) {
            $compteurs['messages'] = (int) $db->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'nouveau'")->fetchColumn();
        }
        if (Access::peut($user, 'prospects')) {
            $stats = Prospect::stats($user);
            $compteurs['prospects'] = (int) $stats[$user['role'] === 'commerciale' ? 'relances' : 'libres'];
        }
        if (Access::peut($user, 'e-mails')) {
            $compteurs['e-mails'] = (int) $db->query("SELECT COUNT(*) FROM mail_outbox WHERE status = 'echec'")->fetchColumn();
        }

        return array_filter($compteurs, static fn (int $n): bool => $n > 0);
    }


    private static function agence(array $user): string
    {
        $statement = Database::connection()->prepare('SELECT name FROM agencies WHERE id = ?');
        $statement->execute([(int) ($user['agency_id'] ?? 0)]);

        return (string) ($statement->fetchColumn() ?: 'Siège de Dakar');
    }
}
