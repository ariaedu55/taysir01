<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Models\Appointment;
use App\Models\RequestRequirement;
use App\Models\RequestTutorAccess;
use App\Models\ServiceRequest;
use App\Services\Audit;
use App\Services\DocumentStorage;
use App\Services\FormInput;
use App\Services\Notifier;
use DomainException;
use OutOfBoundsException;

/** Espace client : dossiers personnels ou explicitement partagés, rendez-vous personnels. */
final class RequestController extends Controller
{
    /** Documents libres par demande, au total. */
    private const MAX_DOCUMENTS = 10;

    public function index(): void
    {
        $user = Auth::user();
        $userId = (int) $user['id'];
        $isParent = $user['role'] === 'client' && $user['profile'] === 'parent';

        $this->render('client/requests', [
            'requests' => ServiceRequest::forUser($userId),
            'sharedRequests' => $isParent ? RequestTutorAccess::forParent($userId) : null,
            'appointments' => Appointment::forUser($userId),
            'services' => ServiceRequest::services(),
            'title' => 'Mes demandes',
            'description' => 'Suivi de vos demandes et de vos rendez-vous Taysir.',
            'active' => 'demandes',
        ], 'client');
    }

    public function show(string $reference): void
    {
        [$request, $permissions] = $this->accessible($reference);
        $requestId = (int) $request['id'];
        $owner = $permissions['owner'];
        $open = RequestRequirement::isOpen($request);

        $this->render('client/request', [
            'request' => $request,
            'service' => ServiceRequest::service($request['service']),
            'requirements' => $requirements = RequestRequirement::forRequest($requestId),
            'pieceFiles' => RequestRequirement::versionFiles($requestId, $requirements),
            'documents' => ServiceRequest::documents($requestId),
            'events' => ServiceRequest::events($requestId),
            'owner' => $owner,
            'tutorAccesses' => $owner ? RequestTutorAccess::forRequest($requestId) : [],
            'canReply' => $owner && $open,
            'canDeposit' => $permissions['deposit'] && $open,
            'canDepositAsTutor' => !$owner && $permissions['deposit'],
            'title' => 'Demande ' . $request['reference'],
            'description' => 'Suivi de votre demande Taysir.',
            'active' => 'demandes',
            'scripts' => ['js/portal.js'],
        ], 'client');
    }

    /** Le client complète sa demande : message et/ou documents supplémentaires. */
    public function reply(string $reference): void
    {
        $request = $this->owned($reference);
        $back = '/mon-espace/demandes/' . $request['reference'] . '/';
        if (!RequestRequirement::isOpen($request)) {
            redirect_to($back);
        }

        $message = FormInput::multiline('message');
        [$files, $error] = DocumentStorage::collect('documents');
        if ($error === null && $message === '' && $files === []) {
            $error = 'Écrivez un message ou joignez un document.';
        }
        if ($error !== null) {
            $this->back(['reply' => $error], $back);
        }

        $requestId = (int) $request['id'];
        $userId = (int) Auth::id();
        $documentCount = count($files);
        $summary = match ($documentCount) {
            0 => '',
            1 => '1 document ajouté.',
            default => $documentCount . ' documents ajoutés.',
        };
        $text = trim($message . "\n\n" . $summary);
        $storedNames = [];
        try {
            $saved = RequestRequirement::withLockedRequest($requestId, static function (array $locked) use ($requestId, $userId, $files, $documentCount, $text, &$storedNames): bool {
                if (!ServiceRequest::appartientAuClient($locked, $userId)) {
                    throw new OutOfBoundsException('Dossier introuvable.');
                }
                if (!RequestRequirement::isOpen($locked)) {
                    return false;
                }
                if ($documentCount > 0) {
                    $statement = Database::connection()->prepare('SELECT COUNT(*) FROM request_documents WHERE request_id = ? AND requirement_id IS NULL');
                    $statement->execute([$requestId]);
                    if ((int) $statement->fetchColumn() + $documentCount > self::MAX_DOCUMENTS) {
                        throw new DomainException('Une demande peut contenir ' . self::MAX_DOCUMENTS . ' documents libres au maximum.');
                    }
                }
                foreach ($files as $file) {
                    $stored = DocumentStorage::store($file);
                    $storedNames[] = $stored;
                    ServiceRequest::addDocument($requestId, $file['name'], $stored, $file['mime'], $file['size']);
                }
                ServiceRequest::addEvent($requestId, $userId, $text);

                return true;
            });
        } catch (\Throwable $exception) {
            foreach ($storedNames as $stored) {
                if (!@unlink(DocumentStorage::directory() . '/' . $stored)) {
                    error_log('Impossible de nettoyer un document libre après un échec.');
                }
            }
            if ($exception instanceof OutOfBoundsException) {
                $this->abort(404);
            }
            if ($exception instanceof DomainException) {
                $this->back(['reply' => $exception->getMessage()], $back);
            }
            throw $exception;
        }
        if (!$saved) {
            redirect_to($back);
        }
        Notifier::clientReplied($request, $message, $documentCount);

        Session::flash('_success', 'Votre message a bien été transmis à votre conseiller.');
        redirect_to($back);
    }

    public function depositRequirement(string $reference, string $id): void
    {
        [$request, $permissions] = $this->accessible($reference);
        if (!$permissions['deposit']) {
            $this->abort(403);
        }
        $requirementId = self::positiveId($id);
        if ($requirementId === false) {
            $this->abort(404);
        }
        $back = '/mon-espace/demandes/' . $request['reference'] . '/#pieces-attendues';

        // 1 à DocumentStorage::MAX_FILES fichiers pour une même pièce (recto-verso, plusieurs pages)
        $names = $_FILES['documents']['name'] ?? null;
        [$files, $error] = is_array($names) && $names !== [] && count($names) <= DocumentStorage::MAX_FILES
            && array_filter($names, 'is_string') === $names
            ? DocumentStorage::collect('documents')
            : [[], null];
        if ($error !== null || $files === [] || count($files) > DocumentStorage::MAX_FILES) {
            Session::flash('_errors', ['requirement' => $error ?? 'Joignez entre 1 et ' . DocumentStorage::MAX_FILES . ' fichiers pour cette pièce.']);
            redirect_to($back);
        }
        try {
            $previous = filter_var($_POST['previous_document_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($previous === false) {
                throw new DomainException('Rechargez le dossier avant de déposer la pièce.');
            }
            $documentId = RequestRequirement::deposit((int) $request['id'], $requirementId, (int) Auth::id(), $files, $previous);
        } catch (OutOfBoundsException) {
            $this->abort(404);
        } catch (DomainException $exception) {
            Session::flash('_errors', ['requirement' => $exception->getMessage()]);
            redirect_to($back);
        }

        Audit::log('piece_attendue_deposee', 'dossier', $request['reference'], [
            'requirement_id' => $requirementId, 'document_id' => $documentId, 'fichiers' => count($files),
        ]);
        Session::flash('_success', count($files) > 1 ? 'Votre pièce a bien été déposée (' . count($files) . ' fichiers).' : 'Votre pièce a bien été déposée.');
        redirect_to($back);
    }

    public function document(string $reference, string $id): void
    {
        [$request, $permissions] = $this->accessible($reference);
        $documentId = self::positiveId($id);
        $document = $documentId === false ? null : ServiceRequest::document((int) $request['id'], $documentId);
        if ($document === null) {
            $this->abort(404);
        }
        if (!$permissions['owner']) {
            Audit::log('piece_telechargee', 'piece', $request['reference'] . '/' . $documentId, ['acces' => 'tuteur']);
        }
        DocumentStorage::send($document);
    }

    public function financeReceipt(string $reference, string $number): void
    {
        $request = $this->owned($reference);
        \App\Services\ReceiptPdf::send(\App\Models\CaseFinance::payment((int) $request['id'], $number) ?? $this->abort(404));
    }

    public function revokeTutor(string $reference, string $id): void
    {
        $request = $this->owned($reference);
        $accessId = self::positiveId($id);
        if ($accessId === false) {
            $this->abort(404);
        }
        try {
            $revoked = RequestTutorAccess::revoke((int) $request['id'], $accessId, (int) Auth::id());
        } catch (OutOfBoundsException) {
            $this->abort(404);
        }
        if (!$revoked) {
            $this->abort(404);
        }

        Audit::log('acces_tuteur_revoque', 'dossier', $request['reference'], ['access_id' => $accessId]);
        Session::flash('_success', 'L’accès tuteur a été retiré.');
        redirect_to('/mon-espace/demandes/' . $request['reference'] . '/#acces-tuteurs');
    }

    /** Dossier lisible par le titulaire ou par un compte parent explicitement autorisé. */
    private function accessible(string $reference): array
    {
        $request = ServiceRequest::findByReference($reference) ?? $this->abort(404);
        $permissions = ServiceRequest::clientPermissions($request, (int) Auth::id()) ?? $this->abort(404);

        return [$request, $permissions];
    }

    private static function positiveId(string $id): int|false
    {
        return ctype_digit($id) ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    }

    /** Demande du client connecté ; 404 sinon, sans révéler qu'elle existe. */
    private function owned(string $reference): array
    {
        $request = ServiceRequest::findByReference($reference);
        if ($request === null || !ServiceRequest::appartientAuClient($request, (int) Auth::id())) {
            $this->abort(404);
        }

        return $request;
    }
}
