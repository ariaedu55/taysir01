<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\ContactMessage;

/** Back-office : messages de la page Contact. */
final class MessageController extends Controller
{
    public function index(): void
    {
        $status = $_GET['statut'] ?? null;
        $status = is_string($status) && isset(ContactMessage::STATUSES[$status]) ? $status : null;

        $this->render('admin/messages', [
            'messages' => ContactMessage::search($status),
            'counts' => ContactMessage::countsByStatus(),
            'status' => $status,
            'title' => 'Messages · Back-office',
            'robots' => 'noindex',
            'styles' => ['css/auth.css', 'css/portal.css'],
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
        $this->back([], '/admin/messages/');
    }
}
