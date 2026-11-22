<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Session;
use App\Models\MailOutbox;

/** Back-office : suivi des e-mails envoyés par le portail, et renvoi de ceux en échec. */
final class MailController extends Controller
{
    public function index(): void
    {
        $status = $_GET['statut'] ?? null;
        $status = is_string($status) && isset(MailOutbox::STATUSES[$status]) ? $status : null;

        $this->render('admin/mails', [
            'mails' => MailOutbox::search($status),
            'counts' => MailOutbox::countsByStatus(),
            'status' => $status,
            'driver' => (string) config('mail.driver'),
            'title' => 'E-mails · Back-office',
            'robots' => 'noindex',
            'styles' => ['css/auth.css', 'css/portal.css'],
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
        $this->back([], '/admin/e-mails/');
    }
}
