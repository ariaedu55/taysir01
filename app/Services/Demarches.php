<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ProcedureTemplate;
use PDO;

/**
 * Catalogue des démarches (phase 12, docs/gestion-phase-12.md). Une démarche = service + pays + motif.
 *
 * Choix : la démarche PUBLIÉE la plus précise l'emporte (service + pays + motif, puis service + pays, puis
 * service + motif, puis le modèle général du service). Un pays ou un motif vide sur la démarche veut dire « tous ».
 * Application : à la création d'un dossier (site ou équipe), copie des étapes et des pièces attendues ; le dossier
 * garde l'identifiant de la version (une nouvelle version ne change jamais un dossier ouvert).
 */
final class Demarches
{
    public const FOURNISSEURS = ['etudiant' => 'Vous', 'parent' => 'Votre parent', 'garant' => 'Votre garant'];
    public const AUTRE_PAYS = 'Autre pays';

    /** @return list<string> Noms des pays couverts actifs, dans l'ordre choisi par l'administration */
    public static function pays(): array
    {
        try {
            return Database::connection()->query('SELECT name FROM covered_countries WHERE active = 1 ORDER BY position, name')->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException) {
            return []; // base pas encore migrée en 026
        }
    }

    /** @return array<int, array> Tous les pays couverts (gestion) */
    public static function tousLesPays(): array
    {
        return Database::connection()->query('SELECT * FROM covered_countries ORDER BY active DESC, position, name')->fetchAll();
    }

    /** @return array<string, string> Motifs d'un service (clé => libellé), vide si le service n'en a pas */
    public static function motifs(string $service): array
    {
        foreach (config('services') as $s) {
            if ($s['key'] === $service) {
                return $s['purposes'] ?? [];
            }
        }

        return [];
    }

    /** Démarche publiée la plus précise pour ce service, ce pays et ce motif, ou null. */
    public static function choisir(string $service, ?string $pays, ?string $motif): ?array
    {
        $pays = $pays !== null && trim($pays) !== '' ? trim($pays) : null;
        $motif = $motif !== null && $motif !== '' ? $motif : null;
        $s = Database::connection()->prepare(
            "SELECT id FROM procedure_templates
             WHERE status = 'publie' AND service = ?
               AND (destination IS NULL OR destination = ?) AND (purpose IS NULL OR purpose = ?)
             ORDER BY (destination IS NOT NULL) DESC, (purpose IS NOT NULL) DESC, id DESC LIMIT 1"
        );
        $s->execute([$service, $pays ?? '', $motif ?? '']);
        $id = $s->fetchColumn();

        return $id === false ? null : ProcedureTemplate::get((int) $id);
    }

    /**
     * Pièces d'une démarche : les pièces structurées, sinon (anciens modèles 2C) celles notées dans les étapes.
     *
     * @return list<array{titre: string, aide: ?string, obligatoire: bool, fournisseur: string}>
     */
    public static function pieces(array $template): array
    {
        $pieces = [];
        foreach ($template['documents'] ?? [] as $d) {
            if (!is_array($d) || trim((string) ($d['titre'] ?? '')) === '') {
                continue;
            }
            $pieces[] = [
                'titre' => mb_substr(trim((string) $d['titre']), 0, 190),
                'aide' => isset($d['aide']) && trim((string) $d['aide']) !== '' ? mb_substr(trim((string) $d['aide']), 0, 255) : null,
                'obligatoire' => (bool) ($d['obligatoire'] ?? true),
                'fournisseur' => isset(self::FOURNISSEURS[$d['fournisseur'] ?? '']) ? $d['fournisseur'] : 'etudiant',
            ];
        }
        if ($pieces === []) {
            foreach ($template['steps'] ?? [] as $etape) {
                foreach ($etape['pieces'] ?? [] as $titre) {
                    if (is_string($titre) && trim($titre) !== '') {
                        $pieces[] = ['titre' => mb_substr(trim($titre), 0, 190), 'aide' => null, 'obligatoire' => true, 'fournisseur' => 'etudiant'];
                    }
                }
            }
        }
        // Une même pièce une seule fois (index unique du dossier sur le titre)
        $vus = [];

        return array_values(array_filter($pieces, static function (array $p) use (&$vus): bool {
            $cle = mb_strtolower($p['titre']);
            if (isset($vus[$cle])) {
                return false;
            }
            $vus[$cle] = true;

            return true;
        }));
    }

    /** « Visa · France · Études » ; les parties absentes sont omises. */
    public static function libelle(string $service, ?string $pays, ?string $motif): string
    {
        $titre = null;
        foreach (config('services') as $s) {
            if ($s['key'] === $service) {
                $titre = $s['short'];
            }
        }
        $motifs = self::motifs($service);

        return implode(' · ', array_filter([$titre ?? $service, $pays ?: null, $motif !== null && $motif !== '' ? ($motifs[$motif] ?? $motif) : null]));
    }

    /** Libellé d'une démarche (gestion) : « Visa · Tous les pays · Études ». */
    public static function libelleModele(array $template): string
    {
        $motifs = self::motifs($template['service']);
        $service = self::libelle($template['service'], null, null);

        return $service . ' · ' . ($template['destination'] ?: 'Tous les pays') . ' · '
            . ($template['purpose'] ? ($motifs[$template['purpose']] ?? $template['purpose']) : (count($motifs) > 0 ? 'Tous les motifs' : 'Sans motif'));
    }

    /**
     * Applique une démarche à un dossier qui vient d'être créé, dans la transaction de l'appelant :
     * version retenue, étapes, pièces attendues (aide, facultative, fournisseur).
     */
    public static function appliquer(PDO $db, int $requestId, array $template): void
    {
        $db->prepare('UPDATE service_requests SET template_id = ? WHERE id = ?')->execute([(int) $template['id'], $requestId]);
        foreach (array_values($template['steps'] ?? []) as $position => $etape) {
            $db->prepare('INSERT INTO request_steps (request_id, position, title, documents, due_days) VALUES (?, ?, ?, ?, ?)')
                ->execute([$requestId, $position + 1, mb_substr((string) ($etape['titre'] ?? ''), 0, 190), json_encode($etape['pieces'] ?? [], JSON_UNESCAPED_UNICODE),
                    isset($etape['delai']) ? (int) $etape['delai'] : null]);
        }
        $piece = $db->prepare("INSERT INTO request_requirements (request_id, title, hint, optional, provider, source) VALUES (?, ?, ?, ?, ?, 'procedure')
            ON DUPLICATE KEY UPDATE id = id");
        foreach (self::pieces($template) as $p) {
            $piece->execute([$requestId, $p['titre'], $p['aide'], $p['obligatoire'] ? 0 : 1, $p['fournisseur']]);
        }
    }

    /** Pays saisi dans un formulaire : un pays couvert, ou le texte libre de « Autre pays ». */
    public static function paysSaisi(string $choix, string $autre): string
    {
        if ($choix === '__autre') {
            return trim($autre) !== '' ? mb_substr(trim($autre), 0, 120) : self::AUTRE_PAYS;
        }

        return mb_substr(trim($choix), 0, 120);
    }
}
