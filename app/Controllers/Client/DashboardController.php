<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Appointment;
use App\Models\Note;
use App\Models\ServiceRequest;
use App\Models\User;

/** Espace client : point d'entrée après connexion, avec le résumé des demandes et des rendez-vous. */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $userId = (int) $user['id'];
        $counts = ServiceRequest::countsByStatus($userId);
        $appointments = array_filter(Appointment::forUser($userId), static fn (array $row): bool =>
            ($row['status'] === 'confirme' && ($row['starts_at_utc'] ?: $row['scheduled_at']) >= gmdate('Y-m-d H:i:s'))
            || ($row['status'] === 'demande' && $row['preferred_date'] >= date('Y-m-d')));
        $requests = ServiceRequest::dashboardForUser($userId);

        $this->render('client/dashboard', [
            'user' => $user,
            'profileLabel' => User::PROFILES[$user['profile']] ?? '',
            'requests' => $requests,
            'requestCount' => array_sum($counts),
            'openCount' => array_sum(array_diff_key($counts, array_flip(['terminee', 'annulee']))),
            'waitingCount' => (int) ($counts['attente-client'] ?? 0),
            'appointmentCount' => count($appointments),
            // 2C (E3, N4) : comptes rendus PARTAGÉS de la personne du compte ; jamais une note interne
            'comptesRendus' => Note::partageesPourClient((int) $user['id']),
            'title' => 'Mon espace',
            'description' => 'Votre espace Taysir Orientation Voyage.',
            'robots' => 'noindex',
            'active' => 'accueil',
        ], 'client');
    }
}
