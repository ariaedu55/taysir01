<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Services\DocumentStorage;
use App\Services\FormInput;
use App\Services\Notifier;

/** Espace client : suivi des demandes et des rendez-vous. Chaque client ne voit que les siens. */
final class RequestController extends Controller
{
    /** Documents par demande, au total. */
    private const MAX_DOCUMENTS = 10;

    public function index(): void
    {
        $userId = (int) Auth::id();

        $this->render('client/requests', [
            'requests' => ServiceRequest::forUser($userId),
            'appointments' => Appointment::forUser($userId),
            'services' => ServiceRequest::services(),
            'title' => 'Mes demandes',
            'description' => 'Suivi de vos demandes et de vos rendez-vous Taysir.',
            'styles' => ['css/auth.css', 'css/portal.css'],
        ]);
    }

    public function show(string $reference): void
    {
        $request = $this->owned($reference);

        $this->render('client/request', [
            'request' => $request,
            'service' => ServiceRequest::service($request['service']),
            'documents' => ServiceRequest::documents((int) $request['id']),
            'events' => ServiceRequest::events((int) $request['id']),
            'canAdd' => !in_array($request['status'], ['terminee', 'annulee'], true),
            'title' => 'Demande ' . $request['reference'],
            'description' => 'Suivi de votre demande Taysir.',
            'styles' => ['css/auth.css', 'css/portal.css'],
            'scripts' => ['js/portal.js'],
        ]);
    }

    /** Le client complète sa demande : message et/ou documents supplémentaires. */
    public function reply(string $reference): void
    {
        $request = $this->owned($reference);
        $back = '/mon-espace/demandes/' . $request['reference'] . '/';
        if (in_array($request['status'], ['terminee', 'annulee'], true)) {
            redirect_to($back);
        }

        $message = FormInput::multiline('message');
        [$files, $error] = DocumentStorage::collect('documents');
        $existing = count(ServiceRequest::documents((int) $request['id']));
        if ($error === null && $existing + count($files) > self::MAX_DOCUMENTS) {
            $error = 'Une demande peut contenir ' . self::MAX_DOCUMENTS . ' documents au maximum.';
        }
        if ($error === null && $message === '' && $files === []) {
            $error = 'Écrivez un message ou joignez un document.';
        }
        if ($error !== null) {
            $this->back(['reply' => $error], $back);
        }

        $names = [];
        foreach ($files as $file) {
            ServiceRequest::addDocument((int) $request['id'], $file['name'], DocumentStorage::store($file), $file['mime'], $file['size']);
            $names[] = $file['name'];
        }
        $text = trim($message . ($names !== [] ? "\n\nDocuments ajoutés : " . implode(', ', $names) : ''));
        ServiceRequest::addEvent((int) $request['id'], (int) Auth::id(), $text);
        Notifier::clientReplied($request, $message, $names);

        Session::flash('_success', 'Votre message a bien été transmis à votre conseiller.');
        redirect_to($back);
    }

    public function document(string $reference, string $id): void
    {
        $request = $this->owned($reference);
        $document = ctype_digit($id) ? ServiceRequest::document((int) $request['id'], (int) $id) : null;
        DocumentStorage::send($document ?? $this->abort(404));
    }

    /** Demande du client connecté ; 404 sinon, sans révéler qu'elle existe. */
    private function owned(string $reference): array
    {
        $request = ServiceRequest::findByReference($reference);
        if ($request === null || (int) $request['user_id'] !== Auth::id()) {
            $this->abort(404);
        }

        return $request;
    }
}
