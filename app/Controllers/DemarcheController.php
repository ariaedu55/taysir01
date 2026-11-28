<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Demarches;

/**
 * « Documents à préparer » d'une démarche publiée (phase 12), pour le formulaire public : fragment HTML, sans donnée
 * personnelle. GET /demarches/pieces/?service=visa&pays=France&motif=etudes
 */
final class DemarcheController extends Controller
{
    public function pieces(): void
    {
        $service = is_string($_GET['service'] ?? null) ? $_GET['service'] : '';
        if (!in_array($service, array_column(config('services'), 'key'), true)) {
            $this->abort(404);
        }
        $pays = is_string($_GET['pays'] ?? null) ? mb_substr(trim($_GET['pays']), 0, 120) : '';
        $pays = $pays === '__autre' ? Demarches::AUTRE_PAYS : $pays;
        $motif = is_string($_GET['motif'] ?? null) && isset(Demarches::motifs($service)[$_GET['motif']]) ? $_GET['motif'] : null;
        $demarche = Demarches::choisir($service, $pays !== '' ? $pays : null, $motif);

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        partial('demarche-pieces', [
            'pieces' => $demarche !== null ? Demarches::pieces($demarche) : [],
            'libelle' => $demarche !== null ? Demarches::libelle($service, $demarche['destination'], $demarche['purpose']) : null,
        ]);
    }
}
