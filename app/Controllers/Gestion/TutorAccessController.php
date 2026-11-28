<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\RequestTutorAccess;
use App\Models\ServiceRequest;
use App\Services\Audit;
use DomainException;
use OutOfBoundsException;

/** L’administrateur identifie le compte puis confirme la vérification qu’il a effectuée hors outil. */
final class TutorAccessController extends EspaceController
{
    public function show(string $reference): void
    {
        $request = $this->dossier($reference);
        $lookup = $_GET['compte'] ?? null;
        $parentId = self::positiveId($lookup);
        $account = $parentId !== false ? RequestTutorAccess::parentAccount($parentId) : null;
        $lookupError = $lookup !== null && $account === null ? 'Indiquez l’identifiant d’un compte client actif de profil parent.' : null;
        if ($account !== null && ServiceRequest::appartientAuClient($request, (int) $account['id'])) {
            $lookupError = 'Ce compte est déjà titulaire du dossier.';
            $account = null;
        }

        $this->page('acces-tuteurs', 'dossiers', 'Accès tuteurs', [
            'request' => $request,
            'accesses' => RequestTutorAccess::forRequest((int) $request['id']),
            'account' => $account,
            'lookup' => is_string($lookup) ? substr($lookup, 0, 20) : '',
            'lookupError' => $lookupError,
        ]);
    }

    public function grant(string $reference): void
    {
        $request = $this->dossier($reference);
        $ref = $request['reference'];
        $back = '/gestion/dossiers/' . $ref . '/tuteurs/';
        $parentId = self::positiveId($_POST['parent_user_id'] ?? null);
        $canDeposit = ($_POST['can_deposit'] ?? null) === '1';
        $verification = \App\Services\FormInput::text('verification_reference', 101);
        try {
            if ($parentId === false || ($_POST['verified'] ?? null) !== '1' || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]{0,99}\z/', $verification)) {
                throw new DomainException('Vérifiez le compte, confirmez le lien et indiquez la référence interne du justificatif.');
            }
            $accessId = RequestTutorAccess::grant((int) $request['id'], $parentId, (int) Auth::id(), $canDeposit, $verification);
        } catch (OutOfBoundsException) {
            $this->refuser('dossier', $ref, 'octroi tuteur réservé à l’administrateur');
        } catch (DomainException $exception) {
            Session::flash('_errors', ['tutor_grant' => $exception->getMessage()]);
            Session::flash('_old_input', ['can_deposit' => $canDeposit ? '1' : '0']);
            redirect_to($back . ($parentId !== false ? '?compte=' . $parentId : ''));
        }

        Audit::log('acces_tuteur_accorde', 'dossier', $ref, [
            'access_id' => $accessId, 'parent_user_id' => $parentId, 'can_deposit' => $canDeposit, 'verification_reference' => $verification,
        ]);
        Session::flash('_success', 'L’accès tuteur a été accordé.');
        redirect_to($back);
    }

    public function revoke(string $reference, string $id): void
    {
        $request = $this->dossier($reference);
        $accessId = self::positiveId($id);
        if ($accessId === false) {
            $this->abort(404);
        }
        $ref = $request['reference'];
        try {
            $revoked = RequestTutorAccess::revoke((int) $request['id'], $accessId, (int) Auth::id(), true);
        } catch (OutOfBoundsException) {
            $this->refuser('dossier', $ref, 'retrait tuteur réservé à l’administrateur');
        }
        if (!$revoked) {
            $this->abort(404);
        }

        Audit::log('acces_tuteur_revoque', 'dossier', $ref, ['access_id' => $accessId]);
        Session::flash('_success', 'L’accès tuteur a été retiré.');
        redirect_to('/gestion/dossiers/' . $ref . '/tuteurs/');
    }

    private function dossier(string $reference): array
    {
        if (!Auth::hasRole('admin')) {
            $this->refuser('dossier', $reference, 'accès tuteurs réservé à l’administrateur');
        }

        return ServiceRequest::findByReference($reference) ?? $this->abort(404);
    }

    private static function positiveId(mixed $value): int|false
    {
        return is_string($value) && ctype_digit($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    }
}
