<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Core\Database;
use App\Models\Assignment;
use App\Models\ProcedureTemplate;
use App\Models\RequestRequirement;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\DocumentStorage;
use App\Services\FormInput;
use App\Services\Notifier;
use DomainException;
use OutOfBoundsException;

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
        $isOpen = RequestRequirement::isOpen($request);

        $this->page('dossier', 'dossiers', $request['first_name'] . ' ' . $request['last_name'] . ' · ' . $request['reference'], [
            'request' => $request,
            'service' => ServiceRequest::service($request['service']),
            'affectations' => $affectations,
            'responsable' => $responsable,
            'requirements' => $responsable ? RequestRequirement::forRequest((int) $request['id']) : [],
            'canManageRequirements' => $isOpen && $responsable,
            'canReviewRequirements' => $isOpen && $responsable,
            // Pièces et historique complet : seulement pour le responsable (la vue transversale voit l'en-tête)
            'documents' => ServiceRequest::documents((int) $request['id']),
            'events' => ServiceRequest::events((int) $request['id'], $responsable),
            'prenables' => Access::prenables($me, $affectations),
            'transmissibles' => Access::transmissibles($me, $affectations),
            'liberables' => Access::liberables($me, $affectations),
            'equipe' => $this->equipeParPoste(),
            // 2C (N10) : étapes lues par l'administrateur, le conseiller, l'orientation, le traitement et le comptable ;
            // cochées par le traitement affecté et l'administrateur. Jamais montrées au client en 2C.
            'etapes' => self::lisentEtapes($me) ? self::etapes((int) $request['id']) : [],
            'peutCocher' => self::peutCocher($me, $affectations),
            'modele' => $request['template_id'] ? ProcedureTemplate::get((int) $request['template_id']) : null,
            'nouvelleVersion' => $request['template_id'] && $responsable ? ProcedureTemplate::versionPlusRecente((int) $request['template_id']) : null,
        ]);
    }

    public function followUp(string $reference): void
    {
        $request = $this->dossier($reference);
        $me = Auth::user();
        $action = FormInput::text('action', 20);
        $back = '/gestion/dossiers/' . $request['reference'] . '/#suivi-dossier';
        $changed = false;
        try {
            $changed = RequestRequirement::withLockedRequest((int) $request['id'], static function (array $locked, array $assignments) use ($me, $action): ?bool {
                if (!Access::estResponsable($me, $assignments)) {
                    return null;
                }
                RequestRequirement::ensureOpen($locked);
                $id = (int) $locked['id'];
                if ($action === 'deadline') {
                    $title = FormInput::text('title', 191);
                    $date = FormInput::text('due_date', 11);
                    if ($title === '' || mb_strlen($title) > 190 || FormInput::date($date) === null || $date < FormInput::today()->format('Y-m-d')) {
                        throw new DomainException('Indiquez un intitulé et une échéance à partir d’aujourd’hui.');
                    }
                    return \App\Models\RequestFollowUp::addDeadline($id, $title, $date);
                }
                if ($action === 'complete') {
                    $deadline = self::requirementId($_POST['deadline_id'] ?? null);
                    if (!$deadline) {
                        throw new DomainException('Choisissez une échéance.');
                    }
                    return \App\Models\RequestFollowUp::completeDeadline($id, $deadline);
                }
                $institution = FormInput::text('institution', 191);
                $result = FormInput::text('result', 25);
                if ($action !== 'result' || $locked['service'] !== 'admission' || $institution === '' || mb_strlen($institution) > 190
                    || !isset(\App\Models\RequestFollowUp::RESULTS[$result])) {
                    throw new DomainException('Indiquez un établissement et une décision d’admission valides.');
                }
                return \App\Models\RequestFollowUp::recordResult($id, $institution, $result);
            }, lockAssignments: true);
        } catch (DomainException $exception) {
            Session::flash('_errors', ['follow_up' => $exception->getMessage()]);
            redirect_to($back);
        }
        if ($changed === null) {
            $this->refuser('dossier', $request['reference'], 'modification du suivi non permise');
        }
        if ($changed) {
            Audit::log('suivi_dossier', 'dossier', $request['reference'], ['action' => $action]);
        }
        Session::flash('_success', $changed ? 'Suivi mis à jour.' : 'Le suivi est déjà à jour.');
        redirect_to($back);
    }

    public function addRequirement(string $reference): void
    {
        $this->changeRequirements($reference, false, static function (int $requestId): array {
            $title = FormInput::text('title', RequestRequirement::TITLE_MAX + 1);
            if ($title === '' || mb_strlen($title) > RequestRequirement::TITLE_MAX) {
                throw new DomainException('Saisissez un intitulé de 190 caractères maximum.');
            }

            $minimum = $_POST['minimum_valid_until'] ?? '';
            if ($minimum !== '' && (!is_string($minimum) || FormInput::date($minimum) === null || $minimum < FormInput::today()->format('Y-m-d'))) {
                throw new DomainException('Indiquez une date de validité minimale à venir.');
            }
            $id = RequestRequirement::add($requestId, $title, $minimum === '' ? null : $minimum);

            return ['Pièce attendue ajoutée.', ['requirement_id' => $id]];
        });
    }

    public function reviewRequirement(string $reference, string $id): void
    {
        $this->changeRequirements($reference, true, static function (int $requestId) use ($id): array {
            $requirementId = self::requirementId($id);
            if ($requirementId === false) {
                throw new OutOfBoundsException('Pièce attendue introuvable.');
            }
            $decision = $_POST['decision'] ?? null;
            if (!in_array($decision, ['validee', 'refusee'], true)) {
                throw new DomainException('Choisissez de valider ou de refuser la pièce.');
            }
            $documentId = self::requirementId($_POST['document_id'] ?? null);
            if ($documentId === false) {
                throw new DomainException('Rechargez le dossier pour examiner le dépôt actuel.');
            }
            $reason = FormInput::multiline('reason', RequestRequirement::REASON_MAX + 1);
            if ($decision === 'refusee' && (preg_match('/\S/u', $reason) !== 1 || mb_strlen($reason) > RequestRequirement::REASON_MAX)) {
                throw new DomainException('Indiquez le motif du refus en 2 000 caractères maximum.');
            }
            $expiresOn = $decision === 'validee' ? ($_POST['expires_on'] ?? '') : '';
            if ($expiresOn !== '' && (!is_string($expiresOn) || !preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $expiresOn)
                || FormInput::date($expiresOn) === null || $expiresOn < FormInput::today()->format('Y-m-d'))) {
                throw new DomainException('La date de validité doit être aujourd’hui ou ultérieure.');
            }
            RequestRequirement::review($requestId, $requirementId, $documentId, (int) Auth::id(), $decision, $reason, $expiresOn === '' ? null : $expiresOn);

            return [
                $decision === 'validee' ? 'Pièce validée.' : 'Pièce refusée.',
                ['requirement_id' => $requirementId, 'document_id' => $documentId, 'decision' => $decision],
            ];
        });
    }

    private function changeRequirements(string $reference, bool $review, callable $change): void
    {
        $request = $this->dossier($reference);
        $me = Auth::user();
        $back = '/gestion/dossiers/' . $request['reference'] . '/#pieces-attendues';

        try {
            $result = RequestRequirement::withLockedRequest((int) $request['id'], static function (array $locked, array $affectations) use ($me, $change): ?array {
                if (!Access::estResponsable($me, $affectations)) {
                    return null;
                }
                RequestRequirement::ensureOpen($locked);

                return $change((int) $locked['id']);
            }, lockAssignments: true);
        } catch (OutOfBoundsException) {
            $this->abort(404);
        } catch (DomainException $exception) {
            Session::flash('_errors', ['requirement' => $exception->getMessage()]);
            redirect_to($back);
        }
        if ($result === null) {
            $this->refuser('dossier', $request['reference'], 'gestion des pièces attendues non permise');
        }

        [$message, $details] = $result;
        Audit::log($review ? 'piece_attendue_examinee' : 'piece_attendue_ajoutee', 'dossier', $request['reference'], $details);
        Session::flash('_success', $message);
        redirect_to($back);
    }

    private static function requirementId(mixed $value): int|false
    {
        return is_string($value) && ctype_digit($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
    }

    /** Cocher ou décocher une étape (N10) : traitement affecté ou administrateur ; sinon 403 journalisé. */
    public function etape(string $reference, string $id): void
    {
        $me = Auth::user();
        $request = $this->dossier($reference);
        if (!self::peutCocher($me, Assignment::forRequest((int) $request['id']))) {
            $this->refuser('dossier', $request['reference'], 'étape non permise');
        }
        $fait = !empty($_POST['fait']);
        $statement = Database::connection()->prepare('UPDATE request_steps SET done_at = ' . ($fait ? 'UTC_TIMESTAMP()' : 'NULL') . ', done_by = ? WHERE id = ? AND request_id = ?');
        $statement->execute([$fait ? (int) $me['id'] : null, ctype_digit($id) ? (int) $id : 0, (int) $request['id']]);
        if ($statement->rowCount() === 0 && !self::etapeExiste((int) $request['id'], $id)) {
            $this->abort(404);
        }
        Audit::log('etape', 'dossier', $request['reference'], ['etape' => (int) $id, 'fait' => $fait]);
        Session::flash('_success', $fait ? 'Étape cochée.' : 'Étape décochée.');
        redirect_to('/gestion/dossiers/' . $request['reference'] . '/#etapes');
    }

    private static function lisentEtapes(array $me): bool
    {
        return array_intersect(Access::postes($me), ['admin', 'conseiller', 'orientation', 'traitement', 'comptable']) !== [];
    }

    private static function peutCocher(array $me, array $affectations): bool
    {
        return in_array('admin', Access::postes($me), true)
            || (in_array('traitement', Access::postes($me), true) && (int) ($affectations['traitement']['user_id'] ?? 0) === (int) $me['id']);
    }

    private static function etapes(int $requestId): array
    {
        $statement = Database::connection()->prepare('SELECT s.*, u.first_name AS done_first_name FROM request_steps s LEFT JOIN users u ON u.id = s.done_by WHERE s.request_id = ? ORDER BY s.position');
        $statement->execute([$requestId]);

        return array_map(static function (array $e): array {
            $e['documents'] = json_decode((string) $e['documents'], true) ?: [];

            return $e;
        }, $statement->fetchAll());
    }

    private static function etapeExiste(int $requestId, string $id): bool
    {
        $statement = Database::connection()->prepare('SELECT 1 FROM request_steps WHERE id = ? AND request_id = ?');
        $statement->execute([ctype_digit($id) ? (int) $id : 0, $requestId]);

        return (bool) $statement->fetchColumn();
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
        Audit::log('piece_telechargee', 'piece', $request['reference'] . '/' . $document['id']);
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
