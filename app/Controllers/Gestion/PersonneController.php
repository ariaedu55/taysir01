<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\Note;
use App\Models\Person;
use App\Models\ProcedureTemplate;
use App\Models\User;
use App\Services\Notifier;
use App\Services\OuvertureDossier;
use App\Services\Access;
use App\Services\Audit;
use App\Services\FormInput;

/**
 * Personnes de l'espace équipe (/gestion/personnes/), étape 2A (docs/gestion-phase-2.md, C5 et K4).
 * Liste, fiche et création, toujours limitées au périmètre d'Access::personnesSql() ; un refus est un 403 journalisé.
 */
final class PersonneController extends EspaceController
{
    public function index(): void
    {
        $me = Auth::user();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        [$personnes, $total] = Person::searchScoped($me, $q, $page);

        $this->page('personnes', 'personnes', 'Personnes', [
            'personnes' => $personnes,
            'total' => $total,
            'q' => $q,
            'pageCourante' => $page,
            'pages' => max(1, (int) ceil($total / Person::PAGE)),
            'peutCreer' => Access::peutCreerPersonne($me),
        ]);
    }

    public function show(string $id): void
    {
        $me = Auth::user();
        $ancienne = ctype_digit($id) ? Person::find((int) $id) : null;
        if (!empty($ancienne['merged_into'])) {
            $target = Person::findScoped($me, (int) $ancienne['merged_into']);
            if ($target === null) {
                $this->refuser('personne', $id, 'hors périmètre');
            }
            $this->redirect('/gestion/personnes/' . $target['id'] . '/');
        }
        $personne = ctype_digit($id) ? Person::findScoped($me, (int) $id) : null;
        if ($personne === null) {
            // 404 si elle n'existe pas, 403 journalisé si elle existe hors périmètre (jamais un 404 qui masquerait la tentative)
            $existe = ctype_digit($id) && Person::find((int) $id) !== null;
            $existe ? $this->refuser('personne', $id, 'hors périmètre') : $this->abort(404);
        }

        $peutVoirRendezVous = Access::peut($me, 'rendez-vous');
        $peutOuvrir = Access::peutOuvrirDossier($me, (int) $personne['id']);
        $jeton = '';
        if ($peutOuvrir) {
            // N7 : jeton d'ouverture, enregistré dans la colonne UNIQUE du dossier créé
            $jeton = bin2hex(random_bytes(16));
        }
        $this->page('personne', 'personnes', $personne['first_name'] . ' ' . $personne['last_name'], [
            'personne' => $personne,
            'dossiers' => Person::dossiers($me, (int) $personne['id']),
            'rendezVous' => $peutVoirRendezVous ? Person::rendezVous($me, (int) $personne['id']) : [],
            'peutVoirRendezVous' => $peutVoirRendezVous,
            // 2C : notes (filtre unique Access::notesVisiblesSql), écriture et ouverture d'un dossier
            'notes' => Note::forPerson($me, (int) $personne['id']),
            'peutEcrire' => Access::peutEcrireNote($me, (int) $personne['id']),
            'peutOuvrir' => $peutOuvrir,
            'jetonOuverture' => $jeton,
            'modeles' => $peutOuvrir ? ProcedureTemplate::toutes() : [],
            'conseillers' => $peutOuvrir && in_array('admin', Access::postes($me), true)
                ? array_values(array_filter(User::staff(true), static fn (array $m): bool => in_array('conseiller', Access::postes($m), true)))
                : [],
        ]);
    }

    /** Note libre sur la personne (E1, E4, N4) : choix de visibilité obligatoire ; jamais modifiée ensuite. */
    public function noter(string $id): void
    {
        $me = Auth::user();
        $personne = ctype_digit($id) ? Person::find((int) $id) : null;
        if ($personne === null) {
            $this->abort(404);
        }
        if (!Access::peutEcrireNote($me, (int) $personne['id'])) {
            $this->refuser('personne', $id, 'note non permise');
        }
        $retour = '/gestion/personnes/' . $personne['id'] . '/#notes';
        [$visibilite, $texte] = self::saisieNote();
        if ($visibilite === null || $texte === '') {
            $this->back(['note' => $visibilite === null ? 'Choisissez « Interne » ou « Partagé » : aucun choix n’est fait par défaut.' : 'Écrivez la note.'], $retour);
        }
        $note = Note::create((int) $personne['id'], $visibilite, $texte, $me, (string) Access::posteEcriture($me));
        Audit::log('note_ecrite', 'personne', (string) $personne['id'], ['note' => (int) $note['id'], 'visibilite' => $visibilite]);
        if ($visibilite === 'partagee') {
            Notifier::noteShared((int) $personne['id']);
        }
        Session::flash('_success', $visibilite === 'partagee' ? 'Note partagée avec la personne.' : 'Note interne enregistrée.');
        redirect_to($retour);
    }

    /** Ouvrir un dossier depuis la fiche (E7, N6, N7). */
    public function ouvrirDossier(string $id): void
    {
        $me = Auth::user();
        $personne = ctype_digit($id) ? Person::find((int) $id) : null;
        if ($personne === null) {
            $this->abort(404);
        }
        if (!Access::peutOuvrirDossier($me, (int) $personne['id'])) {
            $this->refuser('personne', $id, 'ouverture de dossier non permise');
        }
        $modele = (string) ($_POST['template_id'] ?? '');
        $conseiller = (string) ($_POST['conseiller_id'] ?? '');
        try {
            [$dossier, $nouveau] = OuvertureDossier::ouvrir($me, $personne, [
                'service' => FormInput::text('service', 30),
                // Phase 12 : pays couvert ou « Autre pays » (texte libre), motif, démarche automatique
                'destination' => ($pays = FormInput::text('destination', 120)) === '' ? '' : \App\Services\Demarches::paysSaisi($pays, FormInput::text('destination_autre', 120)),
                'purpose' => FormInput::text('purpose', 40),
                'auto' => $modele === 'auto',
                'intake' => FormInput::text('intake', 40),
                'template_id' => ctype_digit($modele) ? (int) $modele : null,
                'conseiller_id' => ctype_digit($conseiller) ? (int) $conseiller : null,
                'token' => FormInput::text('open_token', 32),
            ]);
        } catch (\DomainException $exception) {
            $this->back(['ouvrir' => $exception->getMessage()], '/gestion/personnes/' . $personne['id'] . '/#ouvrir');
        }
        Session::flash('_success', $nouveau ? 'Dossier ' . $dossier['reference'] . ' ouvert.' : 'Ce dossier était déjà ouvert : ' . $dossier['reference'] . '.');
        redirect_to('/gestion/dossiers/' . $dossier['reference'] . '/');
    }

    /**
     * Visibilité et texte d'une note. La visibilité n'a AUCUNE valeur par défaut (E1) : null si absente ou inconnue.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function saisieNote(): array
    {
        $visibilite = is_string($_POST['visibility'] ?? null) && isset(Note::VISIBILITES[$_POST['visibility']]) ? $_POST['visibility'] : null;

        return [$visibilite, FormInput::multiline('body', Note::LONGUEUR_MAX)];
    }

    public function create(): void
    {
        $me = Auth::user();
        if (!Access::peutCreerPersonne($me)) {
            $this->refuser('personne', 'nouvelle', 'création non permise');
        }

        $formulaires = Session::get('creation_personnes', []);
        $jeton = bin2hex(random_bytes(16));
        $formulaires[$jeton] = 0;
        Session::set('creation_personnes', array_slice($formulaires, -30, null, true));

        $this->page('personne-nouvelle', 'personnes', 'Nouvelle personne', [
            'jetonCreation' => $jeton,
            'agences' => Database::connection()->query('SELECT id, name FROM agencies ORDER BY is_head DESC, name')->fetchAll(),
            'origineParDefaut' => in_array('secretaire', Access::postes($me), true) ? 'visite' : 'site',
        ]);
    }

    public function store(): void
    {
        $me = Auth::user();
        if (!Access::peutCreerPersonne($me)) {
            $this->refuser('personne', 'nouvelle', 'création non permise');
        }
        $jeton = $_POST['creation_token'] ?? null;
        $formulaires = Session::get('creation_personnes', []);
        if (!is_string($jeton) || !isset($formulaires[$jeton])) {
            $this->back(['creation' => 'Ouvrez à nouveau le formulaire avant de l’envoyer.'], '/gestion/personnes/nouvelle/');
        }
        if ($formulaires[$jeton] > 0) {
            redirect_to('/gestion/personnes/' . $formulaires[$jeton] . '/');
        }
        $data = [
            'first_name' => FormInput::text('first_name', 80),
            'last_name' => FormInput::text('last_name', 80),
            'email' => mb_strtolower(FormInput::text('email', 190)),
            'phone' => FormInput::phone('phone'),
            'profile' => FormInput::text('profile', 20),
            'birth_date' => FormInput::text('birth_date', 10),
            'timezone' => FormInput::text('timezone', 40),
            'agency_id' => FormInput::text('agency_id', 3),
        ];
        $origin = FormInput::text('origin', 20);
        $errors = [];
        if ($data['first_name'] === '') {
            $errors['first_name'] = 'Indiquez le prénom.';
        }
        if ($data['last_name'] === '') {
            $errors['last_name'] = 'Indiquez le nom.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        if ($data['phone'] !== '' && !FormInput::validPhone($data['phone'])) {
            $errors['phone'] = 'Numéro de téléphone invalide.';
        }
        if ($data['email'] === '' && $data['phone'] === '') {
            $errors['phone'] = 'Indiquez au moins un téléphone ou une adresse e-mail.';
        }
        if (!isset(Person::PROFILS[$data['profile']])) {
            $data['profile'] = null;
        }
        $naissance = $data['birth_date'] !== '' ? FormInput::date($data['birth_date']) : null;
        if ($data['birth_date'] !== '' && ($naissance === null || $naissance > FormInput::today())) {
            $errors['birth_date'] = 'Date de naissance invalide.';
        }
        $data['birth_date'] = $naissance?->format('Y-m-d');
        if (!in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $data['timezone'] = 'Africa/Dakar';
        }
        $agence = ctype_digit($data['agency_id']) ? Database::connection()->prepare('SELECT id FROM agencies WHERE id = ?') : null;
        $agence?->execute([(int) $data['agency_id']]);
        $data['agency_id'] = $agence !== null && $agence->fetchColumn() ? (int) $data['agency_id'] : null;
        if (!in_array($origin, ['site', 'visite', 'prospect'], true)) {
            $origin = 'visite';
        }
        if ($errors !== []) {
            $this->back($errors, '/gestion/personnes/nouvelle/');
        }

        // Aucune fusion automatique : un e-mail déjà connu marque la fiche « à rapprocher » (phase 4)
        $personne = Person::create($data, $origin, (int) $me['id'], Person::emailConnu($data['email']));
        $formulaires[$jeton] = (int) $personne['id'];
        Session::set('creation_personnes', $formulaires);
        Audit::log('personne_creee', 'personne', (string) $personne['id'], ['origine' => $origin]);
        Session::flash('_success', 'Fiche créée' . ($personne['to_reconcile'] ? ' : cette adresse e-mail est déjà connue, la fiche est marquée « à rapprocher ».' : '.'));
        redirect_to('/gestion/personnes/' . $personne['id'] . '/');
    }
}
