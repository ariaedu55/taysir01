<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Controller;
use App\Core\Session;

/**
 * Maquette navigable de l'espace équipe (docs/gestion-phase-1.md, étape 1A).
 * Uniquement en local : la variable GESTION_MAQUETTE=1 doit être présente dans .env, sinon 404 (production).
 * Données fictives (app/Views/gestion/maquette/donnees.php) ; aucune lecture ni écriture en base.
 */
final class MaquetteController extends Controller
{
    public const POSTES = ['admin', 'secretaire', 'prospection', 'commerciale', 'conseiller', 'orientation', 'traitement', 'comptable'];

    public function journee(): void
    {
        $this->show('journee', 'journee', 'Ma journée');
    }

    public function dossiers(): void
    {
        $this->show('dossiers', 'dossiers', 'Dossiers');
    }

    public function dossier(string $reference): void
    {
        $data = $this->data();
        $found = null;
        foreach ($data['dossiers'] as $dossier) {
            if ($dossier[0] === $reference) {
                $found = $dossier;
            }
        }
        // Référence inconnue : 404, jamais une autre fiche à la place (revue 1A)
        if ($found === null) {
            $this->guard();
            $this->abort(404);
        }
        $this->show('dossier', 'dossiers', $found[1] . ' · ' . $found[0], ['dossier' => $found]);
    }

    public function prospects(): void
    {
        $this->show('prospects', 'prospects', 'Prospects');
    }

    /** Suivi côté client, dans le gabarit public (comme /mon-espace/). */
    public function client(): void
    {
        $this->guard();
        $parcours = ($_GET['parcours'] ?? 'etudes') === 'voyage' ? 'voyage' : 'etudes';
        $this->render('gestion/maquette/client', $this->data() + [
            'parcours' => $parcours,
            'title' => 'Mon espace (maquette)',
            'robots' => 'noindex',
            'styles' => ['css/gestion-client.css'],
            'scripts' => ['js/gestion.js'],
        ]);
    }

    private function show(string $view, string $page, string $title, array $extra = []): void
    {
        $this->guard();
        // Icônes utilisées par les vues, rendues avant le gabarit
        require_once BASE_PATH . '/app/Views/gestion/icones.php';
        $this->render('gestion/maquette/' . $view, $this->data() + $extra + [
            'poste' => $this->poste(),
            'page' => $page,
            'title' => $title,
        ], 'gestion');
    }

    private function guard(): void
    {
        // Sans le chargeur .env (env()), ou sans le drapeau : 404, jamais d'erreur serveur
        if (!function_exists('env') || !env('GESTION_MAQUETTE', false)) {
            $this->abort(404);
        }
    }

    /** Poste affiché : ?poste=… (sélecteur « Voir en tant que »), mémorisé dans la session. */
    private function poste(): string
    {
        $poste = $_GET['poste'] ?? Session::get('maquette_poste', 'conseiller');
        if (!in_array($poste, self::POSTES, true)) {
            $poste = 'conseiller';
        }
        Session::set('maquette_poste', $poste);

        return $poste;
    }

    private function data(): array
    {
        return require BASE_PATH . '/app/Views/gestion/maquette/donnees.php';
    }
}
