<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Appointment;
use App\Models\Person;
use App\Services\Audit;
use App\Services\DonneesPersonnelles;
use DomainException;

/**
 * Données personnelles (phase 7, lot 7C ; docs/gestion-phase-7.md), administrateur seulement (module donnees-personnelles).
 * - /gestion/personnes/{id}/donnees : ce que Taysir garde, export JSON, anonymisation (double confirmation : le nom tapé).
 * - /gestion/conservation : fiches au-delà des durées proposées. Chaque export et chaque anonymisation est journalisé.
 */
final class DonneesController extends EspaceController
{
    public function show(string $id): void
    {
        $personne = $this->personne($id);
        $this->page('donnees-personne', 'personnes', 'Données de ' . $personne['first_name'] . ' ' . $personne['last_name'], [
            'personne' => $personne,
            'resume' => array_map(static fn ($bloc): int => is_array($bloc) && array_is_list($bloc) ? count($bloc) : 0,
                DonneesPersonnelles::exporter((int) $personne['id'])),
        ]);
    }

    public function export(string $id): never
    {
        $personne = $this->personne($id);
        $donnees = DonneesPersonnelles::exporter((int) $personne['id']);
        Audit::log('export_personne', 'personne', (string) $personne['id'], ['fiches' => count($donnees['fiches'])]);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="taysir-donnees-personne-' . (int) $personne['id'] . '-' . gmdate('Y-m-d') . '.json"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function anonymiser(string $id): void
    {
        $personne = $this->personne($id);
        $retour = '/gestion/personnes/' . $personne['id'] . '/donnees/';
        $attendu = mb_strtolower(trim($personne['first_name'] . ' ' . $personne['last_name']));
        $tape = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($_POST['confirmation'] ?? '')) ?? ''));
        if (empty($_POST['comprend']) || $tape !== $attendu) {
            $this->back(['anonymiser' => 'Cochez la case et tapez exactement le prénom et le nom de la personne.'], $retour);
        }
        try {
            $fichiers = DonneesPersonnelles::anonymiser((int) $personne['id']);
        } catch (DomainException $e) {
            $this->back(['anonymiser' => $e->getMessage()], $retour);
        }
        // Le journal garde le numéro de fiche, jamais le nom
        Audit::log('anonymisation', 'personne', (string) $personne['id'], ['fichiers_supprimes' => $fichiers], null, true);
        Session::flash('_success', 'Fiche anonymisée. ' . $fichiers . ' fichier' . ($fichiers > 1 ? 's' : '') . ' supprimé' . ($fichiers > 1 ? 's' : '') . ' ; règlements et reçus gardés.');
        redirect_to('/gestion/personnes/' . $personne['id'] . '/');
    }

    public function conservation(): void
    {
        $me = Auth::user();
        $today = (new \DateTimeImmutable('now', Appointment::zone(isset($me['agency_id']) ? (int) $me['agency_id'] : null)))->format('Y-m-d');
        $this->page('conservation', 'conservation', 'Durées de conservation', [
            'listes' => DonneesPersonnelles::conservation($today),
            'durees' => DonneesPersonnelles::DUREES,
        ]);
    }

    private function personne(string $id): array
    {
        $personne = ctype_digit($id) ? Person::find((int) $id) : null;
        if ($personne === null) {
            $this->abort(404);
        }
        if (!empty($personne['merged_into'])) {
            redirect_to('/gestion/personnes/' . (int) $personne['merged_into'] . '/donnees/');
        }

        return $personne;
    }
}
