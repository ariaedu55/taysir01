<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Database;
use App\Models\CaseFinance;
use App\Models\ServiceRequest;
use App\Services\Access;
use App\Services\Audit;

/**
 * Pilotage et exports (phase 7, lot 7B ; docs/gestion-phase-7.md).
 * - /gestion/pilotage : indicateurs du mois et des 12 derniers mois (administrateur), peu nombreux et fiables.
 * - /gestion/exports/{type} : CSV (dossiers, prospects : administrateur ; finance : administrateur et comptable),
 *   séparateur « ; », UTF-8 avec BOM pour Excel ; chaque export est journalisé (type, filtres, nombre de lignes).
 * Dates calculées en PHP dans le fuseau de l'agence du compte, jamais NOW() ni CURDATE().
 */
final class PilotageController extends EspaceController
{
    public function index(): void
    {
        $db = Database::connection();
        [$debutMois, $finMois] = self::mois(0);
        $un = static function (string $sql, array $params = []) use ($db): int {
            $s = $db->prepare($sql);
            $s->execute($params);

            return (int) $s->fetchColumn();
        };
        $mensuel = [];
        for ($k = 11; $k >= 0; $k--) {
            [$d, $f] = self::mois($k);
            $mensuel[] = [
                'mois' => $d,
                'dossiers' => $un('SELECT COUNT(*) FROM service_requests WHERE created_at >= ? AND created_at < ?', [$d, $f]),
                'encaisse' => $un('SELECT COALESCE(SUM(p.amount), 0) FROM finance_payments p LEFT JOIN finance_cancellations c ON c.payment_id = p.id
                    WHERE c.payment_id IS NULL AND p.received_on >= ? AND p.received_on < ?', [substr($d, 0, 10), substr($f, 0, 10)]),
            ];
        }
        $parService = $db->prepare('SELECT service, COUNT(*) FROM service_requests WHERE created_at >= ? AND created_at < ? GROUP BY service');
        $parService->execute([$debutMois, $finMois]);
        $rdv = $db->prepare("SELECT status, COUNT(*) FROM appointments WHERE starts_at_utc >= ? AND starts_at_utc < ? AND status IN ('honore', 'absent', 'annule', 'reporte') GROUP BY status");
        $rdv->execute([$debutMois, $finMois]);
        $prospectsMois = $un('SELECT COUNT(*) FROM prospects WHERE created_at >= ? AND created_at < ?', [$debutMois, $finMois]);
        $transmisMois = $un("SELECT COUNT(*) FROM prospects WHERE state = 'transmis' AND created_at >= ? AND created_at < ?", [$debutMois, $finMois]);
        $today = (new \DateTimeImmutable('now', self::zone()))->format('Y-m-d');
        $finance = CaseFinance::synthese($today, '', '', 100000);

        $this->page('pilotage', 'pilotage', 'Pilotage', [
            'mois' => $debutMois,
            'mensuel' => $mensuel,
            'parService' => $parService->fetchAll(\PDO::FETCH_KEY_PAIR),
            'services' => array_column(config('services'), 'title', 'key'),
            'kpis' => [
                'ouverts' => $un('SELECT COUNT(*) FROM service_requests WHERE created_at >= ? AND created_at < ?', [$debutMois, $finMois]),
                'enCours' => $un("SELECT COUNT(*) FROM service_requests WHERE status NOT IN ('terminee', 'annulee')"),
                'termines' => $un("SELECT COUNT(*) FROM service_requests WHERE status = 'terminee' AND updated_at >= ? AND updated_at < ?", [$debutMois, $finMois]),
                'encaisse' => $mensuel[11]['encaisse'],
                'reste' => array_sum(array_column($finance, 'balance')),
            ],
            'rdv' => $rdv->fetchAll(\PDO::FETCH_KEY_PAIR),
            'prospects' => ['nouveaux' => $prospectsMois, 'transmis' => $transmisMois],
        ]);
    }

    public function export(string $type): void
    {
        $me = Auth::user();
        $postes = Access::postes($me);
        $admin = in_array('admin', $postes, true);
        $permis = match ($type) {
            'dossiers', 'prospects' => $admin,
            'finance' => $admin || in_array('comptable', $postes, true),
            default => $this->abort(404),
        };
        if (!$permis) {
            $this->refuser('export', $type, 'export non permis');
        }
        $db = Database::connection();
        $services = array_column(config('services'), 'title', 'key');
        [$entetes, $lignes] = match ($type) {
            'dossiers' => [
                ['Référence', 'Prénom', 'Nom', 'Service', 'Statut', 'Destination', 'Rentrée', 'Ouvert le', 'Mis à jour le'],
                array_map(static fn (array $r): array => [$r['reference'], $r['first_name'], $r['last_name'], $services[$r['service']] ?? $r['service'],ServiceRequest::statusLabel($r['status'], true),
                    $r['destination'], $r['intake'], substr($r['created_at'], 0, 10), substr($r['updated_at'], 0, 10)],
                    $db->query('SELECT reference, first_name, last_name, service, status, destination, intake, created_at, updated_at FROM service_requests ORDER BY id')->fetchAll()),
            ],
            'finance' => [
                ['Dossier', 'Prénom', 'Nom', 'Devis', 'Devis FCFA', 'Encaissé FCFA', 'Solde FCFA', 'En retard FCFA', 'Prochaine échéance'],
                array_map(static fn (array $l): array => [$l['reference'], $l['first_name'], $l['last_name'], $l['title'], $l['amount'], $l['paid'], $l['balance'], $l['overdue'], $l['next_due']],
                    CaseFinance::synthese((new \DateTimeImmutable('now', self::zone()))->format('Y-m-d'), '', '', 100000)),
            ],
            'prospects' => [
                ['Prénom', 'Nom', 'Téléphone', 'E-mail', 'Établissement', 'Campagne', 'Niveau', 'État', 'Commerciale', 'Créé le'],
                array_map(static fn (array $p): array => [$p['first_name'], $p['last_name'], $p['phone'], $p['email'], $p['ecole'], $p['campagne'], $p['level'], $p['state'], $p['commerciale'], substr($p['created_at'], 0, 10)],
                    $db->query("SELECT pe.first_name, pe.last_name, pe.phone, pe.email, s.name AS ecole, c.name AS campagne, pr.level, pr.state,
                            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS commerciale, pr.created_at
                        FROM prospects pr JOIN people pe ON pe.id = pr.person_id AND pe.merged_into IS NULL
                        LEFT JOIN prospect_schools s ON s.id = pr.school_id LEFT JOIN prospect_campaigns c ON c.id = pr.campaign_id
                        LEFT JOIN users u ON u.id = pr.assigned_to ORDER BY pr.id")->fetchAll()),
            ],
        };
        Audit::log('export', 'export', $type, ['lignes' => count($lignes)]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="taysir-' . $type . '-' . gmdate('Y-m-d') . '.csv"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        $sortie = fopen('php://output', 'w');
        fwrite($sortie, "\xEF\xBB\xBF");
        fputcsv($sortie, $entetes, ';', '"', '');
        foreach ($lignes as $ligne) {
            // Neutralise les formules (=, +, -, @) qu'Excel exécuterait à l'ouverture
            fputcsv($sortie, array_map(static fn ($v): string => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $ligne), ';', '"', '');
        }
        fclose($sortie);
        exit;
    }

    /** @return array{0: string, 1: string} Début et fin (exclue) du mois décalé de $decalage mois, en UTC */
    private static function mois(int $decalage): array
    {
        $debut = (new \DateTimeImmutable('first day of this month 00:00', self::zone()))->modify("-{$decalage} months");
        $utc = new \DateTimeZone('UTC');

        return [$debut->setTimezone($utc)->format('Y-m-d H:i:s'), $debut->modify('+1 month')->setTimezone($utc)->format('Y-m-d H:i:s')];
    }

    private static function zone(): \DateTimeZone
    {
        $me = Auth::user();

        return \App\Models\Appointment::zone(isset($me['agency_id']) ? (int) $me['agency_id'] : null);
    }
}
