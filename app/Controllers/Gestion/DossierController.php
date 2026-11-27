<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Assignment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\DocumentStorage;
use App\Services\FormInput;
use App\Services\Notifier;

/**
 * Dossiers de l'espace équipe (/gestion/dossiers/), limités au périmètre du compte (docs/gestion-phase-1.md, 1B).
 * Chaque action vérifie le droit côté serveur : la liste, la fiche, la pièce, le statut et l'affectation.
 */
final class DossierController extends EspaceController
{
    public function index(): void
    {
        $me = Auth::user();
        $vue = in_array($this->filter('vue'), ['mes', 'a-prendre'], true) ? $this->filter('vue') : null;
        $status = $this->filter('statut');
        $service = $this->filter('service');

        $this->page('dossiers', 'dossiers', 'Dossiers', [
            'dossiers' => ServiceRequest::searchScoped($me, $status, $service, $vue),
            'counts' => ServiceRequest::countsScoped($me),
            'vue' => $vue,
            'status' => $status,
            'service' => $service,
            'services' => ServiceRequest::services(),
            'affectable' => array_intersect(Access::postes($me), array_keys(Access::AFFECTATIONS)) !== [],
        ]);
    }

    public function show(string $reference): void
    {
        $me = Auth::user();
        $request = $this->dossier($reference);
        $affectations = Assignment::forRequest((int) $request['id']);
        $responsable = Access::estResponsable($me, $affectations);

        $this->page('dossier', 'dossiers', $request['first_name'] . ' ' . $request['last_name'] . ' · ' . $request['reference'], [
            'request' => $request,
            'service' => ServiceRequest::service($request['service']),
            'affectations' => $affectations,
            'responsable' => $responsable,
            // Pièces et historique complet : seulement pour le responsable (la vue transversale voit l'en-tête)
            'documents' => ServiceRequest::documents((int) $request['id']),
            'events' => ServiceRequest::events((int) $request['id'], $responsable),
            'prenables' => Access::prenables($me, $affectations),
            'transmissibles' => Access::transmissibles($me, $affectations),
            'liberables' => Access::liberables($me, $affectations),
            'equipe' => $this->equipeParPoste(),
        ]);
    }

    public function update(string $reference): void
    {
        $me = Auth::user();
        $request = $this->dossier($reference);
        if (!Access::estResponsable($me, Assignment::forRequest((int) $request['id']))) {
            $this->refuser('dossier', $request['reference'], 'statut sans affectation');
        }
        $back = '/gestion/dossiers/' . $request['reference'] . '/';
        $status = FormInput::text('status', 20);
        $note = FormInput::multiline('note');
        $visible = !empty($_POST['visible']);
        if (!isset(ServiceRequest::STATUSES[$status])) {
            $this->back(['status' => 'Statut inconnu.'], $back);
        }
        if ($status === $request['status'] && $note === '') {
            $this->back(['note' => 'Changez le statut ou écrivez une note.'], $back);
        }

        ServiceRequest::update($request, $status, $note, $visible, (int) $me['id']);
        Notifier::requestUpdated($request, $status, $note, $visible);
        Session::flash('_success', 'Dossier mis à jour.');
        redirect_to($back);
    }

    /**
     * Affectation (revue 1B, point 2), trois opérations distinctes :
     * - « prendre » : pour soi, seulement si le dossier est dans la file de son poste ; prise atomique ;
     * - « transmettre » : au poste suivant, dans sa file (personne choisie vide) ou à un membre de ce poste ;
     * - « liberer » : remettre dans la file le poste qu'on tient (ou n'importe lequel pour l'administrateur).
     * Ancien formulaire (/admin/…/attribution/) : assigned_to=moi vaut « prendre », sinon « transmettre ».
     */
    public function assign(string $reference): void
    {
        $me = Auth::user();
        $poste = (string) ($_POST['poste'] ?? 'conseiller');
        $operation = (string) ($_POST['operation'] ?? ((string) ($_POST['assigned_to'] ?? '') === 'moi' ? 'prendre' : 'transmettre'));
        // Prise perdue de peu : un collègue du même poste vient de prendre le dossier, qui sort donc de notre
        // périmètre. Ce n'est pas une tentative d'accès : retour à la file avec un message (jamais la fiche).
        if ($operation === 'prendre' && isset(Access::AFFECTATIONS[$poste]) && in_array($poste, Access::postes($me), true)) {
            $existant = ServiceRequest::findByReference($reference);
            if ($existant !== null && !Access::peutOuvrir($me, $existant)) {
                $titulaire = Assignment::forRequest((int) $existant['id'])[$poste] ?? null;
                if (!empty($titulaire['user_id'])) {
                    Session::flash('_notice', $existant['reference'] . ' vient d’être pris par ' . $titulaire['first_name'] . ' ' . $titulaire['last_name'] . '. Choisissez un autre dossier.');
                    redirect_to('/gestion/dossiers/?vue=a-prendre');
                }
            }
        }
        $request = $this->dossier($reference);
        $ref = $request['reference'];
        $back = '/gestion/dossiers/' . $ref . '/';
        $affectations = Assignment::forRequest((int) $request['id']);
        $choice = (string) ($_POST['assigned_to'] ?? '');
        if (!isset(Access::AFFECTATIONS[$poste])) {
            $this->abort(404);
        }

        if ($operation === 'prendre') {
            if (!in_array($poste, Access::prenables($me, $affectations), true)) {
                $this->refuser('dossier', $ref, 'prise ' . $poste . ' hors file');
            }
            if (!Assignment::prendre($request, $poste, $me)) {
                $titulaire = Assignment::forRequest((int) $request['id'])[$poste] ?? null;
                // Le dossier n'est peut-être plus dans notre périmètre : retour à la file, avec le nom de celui qui l'a pris
                Session::flash('_notice', $ref . ' vient d’être pris' . (!empty($titulaire['first_name']) ? ' par ' . $titulaire['first_name'] . ' ' . $titulaire['last_name'] : '') . '. Choisissez un autre dossier.');
                redirect_to('/gestion/dossiers/?vue=a-prendre');
            }
            Audit::log('affectation', 'dossier', $ref, ['operation' => 'prise', 'poste' => $poste]);
            Session::flash('_success', 'Le dossier vous est affecté.');
            redirect_to($back);
        }

        if ($operation === 'liberer') {
            if (!in_array($poste, Access::liberables($me, $affectations), true)) {
                $this->refuser('dossier', $ref, 'remise en file ' . $poste . ' non permise');
            }
            Assignment::transmettre($request, $poste, null, (int) $me['id']);
            Audit::log('affectation', 'dossier', $ref, ['operation' => 'remise en file', 'poste' => $poste]);
            Session::flash('_success', 'Le dossier est remis dans la file du poste ' . mb_strtolower(Access::AFFECTATIONS[$poste]) . '.');
            redirect_to($back);
        }

        // Transmission : au poste suivant de la chaîne, par le responsable actuel (ou la vue transversale)
        if (!in_array($poste, Access::transmissibles($me, $affectations), true)) {
            $this->refuser('dossier', $ref, 'transmission ' . $poste . ' non permise');
        }
        $member = null;
        if ($choice !== '') {
            $member = ctype_digit($choice) ? User::find((int) $choice) : null;
            if ($member === null || empty($member['active']) || !in_array($poste, Access::postes($member), true)) {
                $this->back(['assigned_to_' . $poste => 'Choisissez un membre actif de ce poste, ou la file du poste.'], $back);
            }
        }
        if ($member !== null && (int) $member['id'] === (int) ($affectations[$poste]['user_id'] ?? 0)) {
            redirect_to($back);
        }

        Assignment::transmettre($request, $poste, $member, (int) $me['id']);
        Audit::log('affectation', 'dossier', $ref, ['operation' => 'transmission', 'poste' => $poste, 'vers' => $member['id'] ?? 'file']);
        if ($member !== null && (int) $member['id'] !== (int) $me['id']) {
            Notifier::requestAssigned($request, $member, $me['first_name'] . ' ' . $me['last_name']);
        }
        Session::flash('_success', $member === null
            ? 'Dossier mis dans la file du poste ' . mb_strtolower(Access::AFFECTATIONS[$poste]) . ' : le premier disponible le prendra.'
            : 'Dossier transmis à ' . $member['first_name'] . ' ' . $member['last_name'] . ', prévenu(e) par e-mail.');
        redirect_to($back);
    }

    /** Pièce : seulement le responsable affecté (ou l'administrateur). Chaque téléchargement est journalisé. */
    public function document(string $reference, string $id): void
    {
        $me = Auth::user();
        $request = $this->dossier($reference);
        if (!Access::estResponsable($me, Assignment::forRequest((int) $request['id']))) {
            $this->refuser('piece', $request['reference'] . '/' . $id, 'pièce sans affectation');
        }
        $document = ctype_digit($id) ? ServiceRequest::document((int) $request['id'], (int) $id) : null;
        if ($document === null) {
            $this->abort(404);
        }
        Audit::log('piece_telechargee', 'piece', $request['reference'] . '/' . $document['id'], ['nom' => $document['original_name']]);
        DocumentStorage::send($document);
    }

    /** Dossier du périmètre ; 404 s'il n'existe pas, 403 journalisé s'il existe hors périmètre. */
    private function dossier(string $reference): array
    {
        $request = ServiceRequest::findByReference($reference) ?? $this->abort(404);
        if (!Access::peutOuvrir(Auth::user(), $request)) {
            $this->refuser('dossier', $request['reference'], 'hors périmètre');
        }

        return $request;
    }

    /** @return array<string, list<array>> Membres actifs par poste affectable (missions comprises) */
    private function equipeParPoste(): array
    {
        $parPoste = array_fill_keys(array_keys(Access::AFFECTATIONS), []);
        foreach (User::staff(true) as $member) {
            foreach (Access::postes($member) as $poste) {
                if (isset($parPoste[$poste])) {
                    $parPoste[$poste][] = $member;
                }
            }
        }

        return $parPoste;
    }
}
