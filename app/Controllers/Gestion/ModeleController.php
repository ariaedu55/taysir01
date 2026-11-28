<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\ProcedureTemplate;
use App\Services\Audit;
use App\Services\Demarches;
use App\Services\FormInput;

/**
 * Démarches et pièces (/gestion/modeles/), administrateur seulement (module « modeles », acces:modeles).
 * Phase 12 (docs/gestion-phase-12.md) : une démarche = service + pays + motif, avec ses pièces (une par ligne), ses
 * étapes et ses sources ; versions, vérification et publication héritées de la 2C (E8, E9, N5).
 * Étapes : une par ligne, « Titre | délai en jours » (l'ancien format « Titre | pièces | délai » reste lu).
 */
final class ModeleController extends EspaceController
{
    public function index(): void
    {
        $this->page('modeles', 'modeles', 'Démarches et pièces', [
            'modeles' => ProcedureTemplate::toutes(),
            'services' => array_column(config('services'), 'title', 'key'),
            'fraicheur' => (int) config('gestion.fraicheur_mois', 6),
            'pays' => Demarches::tousLesPays(),
        ]);
    }

    /** Nouvelle démarche, éventuellement pré-remplie : ?service=visa&pays=France&motif=etudes, ou ?depuis={id} (duplication). */
    public function create(): void
    {
        $source = ctype_digit((string) ($_GET['depuis'] ?? '')) ? ProcedureTemplate::get((int) $_GET['depuis']) : null;
        $prefill = [
            'service' => is_string($_GET['service'] ?? null) ? $_GET['service'] : ($source['service'] ?? 'admission'),
            'destination' => is_string($_GET['pays'] ?? null) ? $_GET['pays'] : null,
            'purpose' => is_string($_GET['motif'] ?? null) ? $_GET['motif'] : ($source['purpose'] ?? null),
        ];
        // Point de départ : la démarche dupliquée, sinon celle qui s'applique aujourd'hui (plus générale)
        $base = $source ?? Demarches::choisir($prefill['service'], $prefill['destination'], $prefill['purpose']);
        $this->page('modele', 'modeles', 'Nouvelle démarche', [
            'modele' => null,
            'prefill' => $prefill + [
                'title' => '',
                'documents' => $base !== null ? Demarches::pieces($base) : [],
                'steps' => $base['steps'] ?? [],
                'sources' => $base['sources'] ?? [],
            ],
            'source' => $source,
            'services' => array_column(config('services'), 'title', 'key'),
            'pays' => Demarches::pays(),
        ]);
    }

    public function store(): void
    {
        $data = $this->saisie(true);
        $modele = ProcedureTemplate::creer($data, (int) Auth::id());
        Audit::log('modele_cree', 'modele', $modele['template_key'] . ' v' . $modele['version']);
        Session::flash('_success', 'Démarche créée en brouillon. Faites-la vérifier, puis publiez-la.');
        redirect_to('/gestion/modeles/' . $modele['id'] . '/');
    }

    public function show(string $id): void
    {
        $modele = $this->modele($id);
        $this->page('modele', 'modeles', Demarches::libelleModele($modele) . ' · v' . $modele['version'], [
            'modele' => $modele,
            'prefill' => null,
            'source' => null,
            'services' => array_column(config('services'), 'title', 'key'),
            'pays' => Demarches::pays(),
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
                    ProcedureTemplate::modifierBrouillon($modele, $this->saisie(false, $retour));
                    Session::flash('_success', 'Brouillon enregistré.');
                    break;
                case 'verifier':
                    ProcedureTemplate::verifier($modele, (int) Auth::id());
                    Session::flash('_success', 'Vérification enregistrée à votre nom, à la date du jour.');
                    break;
                case 'publier':
                    ProcedureTemplate::publier($modele);
                    Session::flash('_success', 'Démarche publiée : elle s’applique aux nouveaux dossiers. Les dossiers en cours gardent leur version.');
                    break;
                case 'retirer':
                    ProcedureTemplate::retirer($modele);
                    Session::flash('_success', 'Démarche retirée : une démarche plus générale s’applique désormais. Rien n’est supprimé.');
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

    /** Pays couverts : ajouter, désactiver ou réactiver (jamais supprimés : des dossiers peuvent les citer). */
    public function pays(): void
    {
        $db = Database::connection();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'ajouter') {
            $nom = FormInput::text('name', 120);
            if ($nom === '' || $nom === Demarches::AUTRE_PAYS) {
                $this->back(['pays' => 'Indiquez le nom du pays.'], '/gestion/modeles/#pays');
            }
            $db->prepare('INSERT INTO covered_countries (name, active, position) SELECT ?, 1, COALESCE(MAX(position), 0) + 1 FROM covered_countries
                ON DUPLICATE KEY UPDATE active = 1')->execute([$nom]);
            Audit::log('pays_couvert', 'pays', $nom, ['action' => 'ajout']);
            Session::flash('_success', $nom . ' fait partie des pays couverts.');
        } elseif (in_array($action, ['desactiver', 'reactiver'], true) && ctype_digit((string) ($_POST['id'] ?? ''))) {
            $db->prepare('UPDATE covered_countries SET active = ? WHERE id = ?')->execute([$action === 'reactiver' ? 1 : 0, (int) $_POST['id']]);
            Audit::log('pays_couvert', 'pays', (string) $_POST['id'], ['action' => $action]);
            Session::flash('_success', $action === 'reactiver' ? 'Pays réactivé.' : 'Pays retiré des listes (les dossiers existants le gardent).');
        } else {
            $this->abort(400);
        }
        redirect_to('/gestion/modeles/#pays');
    }

    private function modele(string $id): array
    {
        return (ctype_digit($id) ? ProcedureTemplate::get((int) $id) : null) ?? $this->abort(404);
    }

    /** Saisie validée du formulaire ; renvoie au formulaire avec les erreurs sinon. */
    private function saisie(bool $creation, string $retour = '/gestion/modeles/nouveau/'): array
    {
        $services = array_column(config('services'), 'title', 'key');
        $pays = FormInput::text('destination', 120);
        $data = [
            'template_key' => strtolower(FormInput::text('template_key', 60)),
            'title' => FormInput::text('title', 190),
            'service' => FormInput::text('service', 30),
            'destination' => $pays === '' ? null : Demarches::paysSaisi($pays, FormInput::text('destination_autre', 120)),
            'purpose' => FormInput::text('purpose', 40) ?: null,
            'steps' => [],
            'documents' => [],
            'sources' => [],
        ];
        $errors = [];
        if (!isset($services[$data['service']])) {
            $errors['service'] = 'Choisissez un service.';
        } elseif ($data['purpose'] !== null && !isset(Demarches::motifs($data['service'])[$data['purpose']])) {
            $errors['purpose'] = 'Ce motif n’existe pas pour ce service.';
        }
        if ($data['destination'] === Demarches::AUTRE_PAYS) {
            $errors['destination'] = 'Précisez le pays, ou choisissez « Tous les pays ».';
        }
        if ($data['title'] === '' && !isset($errors['service'])) {
            $data['title'] = mb_substr(Demarches::libelleModele($data), 0, 190);
        }
        // Clé : saisie (compatibilité), sinon dérivée du service, du pays et du motif ; unique
        if ($creation) {
            if ($data['template_key'] === '') {
                $base = substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', 'demarche ' . $data['service'] . ' ' . ($data['destination'] ?? '') . ' ' . ($data['purpose'] ?? '')) ?: '')), '-'), 0, 54);
                $cle = $base;
                for ($n = 2; ProcedureTemplate::cleExiste($cle); $n++) {
                    $cle = $base . '-' . $n;
                }
                $data['template_key'] = $cle;
            } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $data['template_key'])) {
                $errors['template_key'] = 'Clé en minuscules, chiffres et tirets (ex. france-campus).';
            } elseif (ProcedureTemplate::cleExiste($data['template_key'])) {
                $errors['template_key'] = 'Cette clé existe déjà : créez une nouvelle version depuis la démarche existante.';
            }
        }
        // Pièces : une par ligne du formulaire
        $titres = is_array($_POST['doc_titre'] ?? null) ? $_POST['doc_titre'] : [];
        foreach ($titres as $i => $titre) {
            $titre = is_string($titre) ? trim($titre) : '';
            if ($titre === '') {
                continue;
            }
            $aide = is_string($_POST['doc_aide'][$i] ?? null) ? trim($_POST['doc_aide'][$i]) : '';
            $fournisseur = is_string($_POST['doc_fournisseur'][$i] ?? null) ? $_POST['doc_fournisseur'][$i] : 'etudiant';
            $data['documents'][] = [
                'titre' => mb_substr($titre, 0, 190),
                'aide' => $aide === '' ? null : mb_substr($aide, 0, 255),
                'obligatoire' => ($_POST['doc_obligatoire'][$i] ?? '1') !== '0',
                'fournisseur' => isset(Demarches::FOURNISSEURS[$fournisseur]) ? $fournisseur : 'etudiant',
            ];
        }
        foreach (preg_split('/\R/', FormInput::multiline('steps', 8000)) as $ligne) {
            $morceaux = array_map('trim', explode('|', $ligne));
            if ($morceaux[0] === '') {
                continue;
            }
            // « Titre | délai » ou, ancien format, « Titre | pièce 1 ; pièce 2 | délai »
            $delai = count($morceaux) === 2 ? $morceaux[1] : ($morceaux[2] ?? '');
            $data['steps'][] = [
                'titre' => mb_substr($morceaux[0], 0, 190),
                'pieces' => count($morceaux) >= 3 ? array_values(array_filter(array_map('trim', explode(';', $morceaux[1])), static fn (string $p): bool => $p !== '')) : [],
                'delai' => ctype_digit($delai) ? (int) $delai : null,
            ];
        }
        if ($data['steps'] === [] && $data['documents'] === []) {
            $errors['steps'] = 'Indiquez au moins une pièce ou une étape.';
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
        if ($errors !== []) {
            $this->back($errors, $retour);
        }

        return $data;
    }
}
