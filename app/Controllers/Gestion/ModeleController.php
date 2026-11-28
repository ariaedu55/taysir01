<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\ProcedureTemplate;
use App\Services\Audit;
use App\Services\FormInput;

/**
 * Modèles de procédure (/gestion/modeles/), administrateur seulement (module « modeles », route protégée par
 * acces:modeles) ; docs/gestion-phase-2.md, 2C, E8, E9, N5.
 * Étapes saisies une par ligne : « Titre | pièce 1 ; pièce 2 | délai en jours ». Sources : une URL par ligne.
 */
final class ModeleController extends EspaceController
{
    public function index(): void
    {
        $this->page('modeles', 'modeles', 'Modèles de procédure', [
            'modeles' => ProcedureTemplate::toutes(),
            'services' => array_column(config('services'), 'title', 'key'),
            'fraicheur' => (int) config('gestion.fraicheur_mois', 6),
        ]);
    }

    public function create(): void
    {
        $this->page('modele', 'modeles', 'Nouveau modèle', [
            'modele' => null,
            'services' => array_column(config('services'), 'title', 'key'),
        ]);
    }

    public function store(): void
    {
        $data = $this->saisie(true);
        $modele = ProcedureTemplate::creer($data, (int) Auth::id());
        Audit::log('modele_cree', 'modele', $modele['template_key'] . ' v' . $modele['version']);
        Session::flash('_success', 'Modèle créé en brouillon.');
        redirect_to('/gestion/modeles/' . $modele['id'] . '/');
    }

    public function show(string $id): void
    {
        $modele = $this->modele($id);
        $this->page('modele', 'modeles', $modele['title'] . ' · v' . $modele['version'], [
            'modele' => $modele,
            'services' => array_column(config('services'), 'title', 'key'),
            'aRevoir' => $modele['status'] === 'publie' && ProcedureTemplate::aRevoir($modele),
        ]);
    }

    /** Actions : enregistrer (brouillon), verifier, publier, retirer, nouvelle-version. */
    public function update(string $id): void
    {
        $modele = $this->modele($id);
        $action = (string) ($_POST['action'] ?? '');
        $retour = '/gestion/modeles/' . $modele['id'] . '/';
        $libelle = $modele['template_key'] . ' v' . $modele['version'];
        try {
            switch ($action) {
                case 'enregistrer':
                    ProcedureTemplate::modifierBrouillon($modele, $this->saisie(false));
                    Session::flash('_success', 'Brouillon enregistré.');
                    break;
                case 'verifier':
                    ProcedureTemplate::verifier($modele, (int) Auth::id());
                    Session::flash('_success', 'Vérification enregistrée à votre nom, à la date du jour.');
                    break;
                case 'publier':
                    ProcedureTemplate::publier($modele);
                    Session::flash('_success', 'Version publiée ; la version publiée précédente est retirée. Les dossiers en cours gardent leur version.');
                    break;
                case 'retirer':
                    ProcedureTemplate::retirer($modele);
                    Session::flash('_success', 'Version retirée : elle n’est plus proposée. Rien n’est supprimé.');
                    break;
                case 'nouvelle-version':
                    $nouvelle = ProcedureTemplate::nouvelleVersion($modele, (int) Auth::id());
                    Audit::log('modele_version', 'modele', $nouvelle['template_key'] . ' v' . $nouvelle['version']);
                    Session::flash('_success', 'Nouvelle version créée en brouillon.');
                    redirect_to('/gestion/modeles/' . $nouvelle['id'] . '/');
                default:
                    $this->abort(400);
            }
        } catch (\DomainException $exception) {
            $this->back(['modele' => $exception->getMessage()], $retour);
        }
        Audit::log('modele_' . str_replace('-', '_', $action), 'modele', $libelle);
        redirect_to($retour);
    }

    private function modele(string $id): array
    {
        return (ctype_digit($id) ? ProcedureTemplate::get((int) $id) : null) ?? $this->abort(404);
    }

    /** Saisie validée du formulaire ; renvoie au formulaire avec les erreurs sinon. */
    private function saisie(bool $creation): array
    {
        $services = array_column(config('services'), 'title', 'key');
        $data = [
            'template_key' => strtolower(FormInput::text('template_key', 60)),
            'title' => FormInput::text('title', 190),
            'service' => FormInput::text('service', 30),
            'destination' => FormInput::text('destination', 120),
            'steps' => [],
            'sources' => [],
        ];
        $errors = [];
        if ($creation && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $data['template_key'])) {
            $errors['template_key'] = 'Clé en minuscules, chiffres et tirets (ex. france-campus).';
        }
        if ($data['title'] === '') {
            $errors['title'] = 'Indiquez un titre.';
        }
        if (!isset($services[$data['service']])) {
            $errors['service'] = 'Choisissez un service.';
        }
        foreach (preg_split('/\R/', FormInput::multiline('steps', 8000)) as $ligne) {
            $morceaux = array_map('trim', explode('|', $ligne));
            if ($morceaux[0] === '') {
                continue;
            }
            $data['steps'][] = [
                'titre' => mb_substr($morceaux[0], 0, 190),
                'pieces' => array_values(array_filter(array_map('trim', explode(';', $morceaux[1] ?? '')), static fn (string $p): bool => $p !== '')),
                'delai' => isset($morceaux[2]) && ctype_digit($morceaux[2]) ? (int) $morceaux[2] : null,
            ];
        }
        if ($data['steps'] === []) {
            $errors['steps'] = 'Indiquez au moins une étape.';
        }
        foreach (preg_split('/\R/', FormInput::multiline('sources', 3000)) as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
                $errors['sources'] = 'Une source par ligne, adresse complète en http(s).';
                break;
            }
            $data['sources'][] = $url;
        }
        if ($creation && $errors === [] && ProcedureTemplate::cleExiste($data['template_key'])) {
            $errors['template_key'] = 'Cette clé existe déjà : créez une nouvelle version depuis le modèle existant.';
        }
        if ($errors !== []) {
            $this->back($errors, '/gestion/modeles/');
        }

        return $data;
    }
}
