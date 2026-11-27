<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\ContactMessage;
use App\Models\MailOutbox;

/** Messages de la page Contact (/gestion/messages/) et suivi des e-mails (/gestion/e-mails/, administrateur). */
final class MessageController extends EspaceController
{
    public function index(): void
    {
        $status = $this->filter('statut');
        $status = $status !== null && isset(ContactMessage::STATUSES[$status]) ? $status : null;

        $this->page('messages', 'messages', 'Messages', [
            'messages' => ContactMessage::search($status),
            'counts' => ContactMessage::countsByStatus(),
            'status' => $status,
        ]);
    }

    public function update(string $id): void
    {
        $message = ctype_digit($id) ? ContactMessage::find((int) $id) : null;
        $status = (string) ($_POST['status'] ?? '');
        if ($message === null || !isset(ContactMessage::STATUSES[$status])) {
            $this->abort(404);
        }
        ContactMessage::setStatus((int) $message['id'], $status, (int) Auth::id());
        Session::flash('_success', $status === 'traite' ? 'Message marqué comme traité.' : 'Message remis à traiter.');
        $this->back([], '/gestion/messages/');
    }

    public function mails(): void
    {
        $status = $this->filter('statut');
        $status = $status !== null && isset(MailOutbox::STATUSES[$status]) ? $status : null;

        $this->page('e-mails', 'e-mails', 'E-mails', [
            'mails' => MailOutbox::search($status),
            'counts' => MailOutbox::countsByStatus(),
            'status' => $status,
            'driver' => (string) config('mail.driver'),
        ]);
    }

    public function retry(string $id): void
    {
        $mail = ctype_digit($id) ? MailOutbox::find((int) $id) : null;
        if ($mail === null) {
            $this->abort(404);
        }
        MailOutbox::retry((int) $mail['id']);
        $sent = MailOutbox::deliver((int) $mail['id']);
        Session::flash('_success', $sent ? 'E-mail renvoyé à ' . $mail['recipient'] . '.' : 'Nouvel échec : l’e-mail sera retenté automatiquement.');
        $this->back([], '/gestion/e-mails/');
    }
}
