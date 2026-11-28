<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Person;
use App\Models\Prospect;
use App\Services\Access;

/**
 * Recherche globale côté serveur (/gestion/recherche/?q=), étape 2A, scénario P17 :
 * personnes et dossiers du périmètre du compte, requêtes préparées, % et _ échappés, 20 résultats au plus.
 * Remplace l'index embarqué dans la page en 1B, qui ne tiendrait pas le volume.
 * Réponse JSON pour la palette (Ctrl+K) avec ?format=json, page HTML sinon.
 */
final class RechercheController extends EspaceController
{
    public function index(): void
    {
        $me = Auth::user();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $resultats = mb_strlen($q) >= 2 ? self::chercher($me, $q) : [];

        if (($_GET['format'] ?? '') === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['q' => $q, 'resultats' => $resultats], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $this->page('recherche', 'gestion', 'Recherche', ['q' => $q, 'resultats' => $resultats]);
    }

    /** @return list<array{type: string, titre: string, detail: string, url: string}> */
    public static function chercher(array $user, string $q): array
    {
        $resultats = [];
        if (Access::peut($user, 'personnes')) {
            [$personnes] = Person::searchScoped($user, $q, 1, Person::RECHERCHE_MAX);
            foreach ($personnes as $p) {
                $resultats[] = [
                    'type' => 'Personne',
                    'titre' => $p['first_name'] . ' ' . $p['last_name'],
                    'detail' => trim(($p['phone'] ?? '') . ($p['email'] ? ' · ' . $p['email'] : ''), ' ·'),
                    'url' => url('/gestion/personnes/' . $p['id'] . '/'),
                ];
            }
        }
        if (Access::peut($user, 'dossiers') && count($resultats) < Person::RECHERCHE_MAX) {
            [$sql, $params] = Access::perimetre($user, 'r');
            [$filtre, $filtreParams] = Person::filtre($q, 'r');
            $motif = '%' . addcslashes(mb_strtolower($q), '\\%_') . '%';
            $statement = Database::connection()->prepare(
                "SELECT r.reference, r.first_name, r.last_name, r.service, r.destination FROM service_requests r
                 WHERE {$sql} AND ((1 = 1{$filtre}) OR LOWER(r.reference) LIKE ?)
                 ORDER BY r.updated_at DESC LIMIT " . (Person::RECHERCHE_MAX - count($resultats))
            );
            $statement->execute(array_merge($params, $filtreParams, [$motif]));
            $services = array_column(config('services'), 'title', 'key');
            foreach ($statement->fetchAll() as $r) {
                $resultats[] = [
                    'type' => 'Dossier',
                    'titre' => $r['first_name'] . ' ' . $r['last_name'] . ' · ' . ($services[$r['service']] ?? $r['service']),
                    'detail' => $r['reference'] . ($r['destination'] ? ' · ' . $r['destination'] : ''),
                    'url' => url('/gestion/dossiers/' . $r['reference'] . '/'),
                ];
            }
        }

        if (Access::peut($user, 'prospects') && count($resultats) < Person::RECHERCHE_MAX) {
            [$prospects] = Prospect::search($user, $q, '', 0, 1, Person::RECHERCHE_MAX - count($resultats));
            foreach ($prospects as $prospect) {
                $resultats[] = [
                    'type' => 'Prospect', 'titre' => $prospect['first_name'] . ' ' . $prospect['last_name'],
                    'detail' => $prospect['school'], 'url' => url('/gestion/prospects/' . $prospect['id'] . '/'),
                ];
            }
        }
        return $resultats;
    }
}
