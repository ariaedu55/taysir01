<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Appointment;
use App\Models\ContactMessage;
use App\Models\ServiceRequest;
use App\Models\User;

/** Back-office (conseillers et administrateurs) : chiffres clés et dernières inscriptions. */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $db = Database::connection();
        $this->render('admin/dashboard', [
            'user' => Auth::user(),
            'clientCount' => (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn(),
            'requestCounts' => ServiceRequest::countsByStatus(),
            'appointmentCounts' => Appointment::countsByStatus(),
            'messageCount' => (int) (ContactMessage::countsByStatus()['nouveau'] ?? 0),
            'documentCount' => (int) $db->query('SELECT COUNT(*) FROM request_documents')->fetchColumn(),
            'latest' => $db->query("SELECT first_name, last_name, email, profile, created_at FROM users WHERE role = 'client' ORDER BY created_at DESC LIMIT 10")->fetchAll(),
            'profiles' => User::PROFILES,
            'title' => 'Back-office',
            'robots' => 'noindex',
            'styles' => ['css/auth.css', 'css/portal.css'],
        ]);
    }
}
