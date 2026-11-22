<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Models\User;

/** Espace client : point d'entrée après connexion, avec le résumé des demandes et des rendez-vous. */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $counts = ServiceRequest::countsByStatus((int) $user['id']);
        $appointments = Appointment::forUser((int) $user['id']);

        $this->render('client/dashboard', [
            'user' => $user,
            'profileLabel' => User::PROFILES[$user['profile']] ?? '',
            'requestCount' => array_sum($counts),
            'openCount' => array_sum(array_diff_key($counts, array_flip(['terminee', 'annulee']))),
            'waitingCount' => (int) ($counts['attente-client'] ?? 0),
            'appointmentCount' => count(array_filter($appointments, static fn ($a) => in_array($a['status'], ['demande', 'confirme'], true))),
            'title' => 'Mon espace',
            'description' => 'Votre espace Taysir Orientation Voyage.',
            'robots' => 'noindex',
            'styles' => ['css/auth.css', 'css/portal.css'],
        ]);
    }
}
