<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Session;
use App\Models\Appointment;
use App\Services\FormInput;
use App\Services\Notifier;

/** Rendez-vous demandés sur le site (/gestion/rendez-vous/) : confirmation avec date et heure. */
final class RendezVousController extends EspaceController
{
    public function index(): void
    {
        $status = $this->filter('statut');
        $status = $status !== null && isset(Appointment::STATUSES[$status]) ? $status : null;

        $this->page('rendez-vous', 'rendez-vous', 'Rendez-vous', [
            'appointments' => Appointment::search($status),
            'counts' => Appointment::countsByStatus(),
            'status' => $status,
        ]);
    }

    public function update(string $reference): void
    {
        $appointment = Appointment::findByReference($reference) ?? $this->abort(404);
        $status = FormInput::text('status', 20);
        $scheduled = FormInput::text('scheduled_at', 16);
        $scheduledAt = null;
        $key = 'rdv_' . $appointment['id'];

        $errors = [];
        if (!isset(Appointment::STATUSES[$status])) {
            $errors[$key] = 'Statut inconnu.';
        }
        if ($scheduled !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $scheduled);
            if ($date === false) {
                $errors[$key] = 'Date et heure non valides.';
            } else {
                $scheduledAt = $date->format('Y-m-d H:i:s');
            }
        }
        if ($status === 'confirme' && $scheduledAt === null) {
            $errors[$key] = 'Indiquez la date et l’heure pour confirmer.';
        }
        if ($errors !== []) {
            $this->back($errors, '/gestion/rendez-vous/');
        }

        Appointment::update((int) $appointment['id'], $status, $scheduledAt);
        Notifier::appointmentUpdated($appointment, $status, $scheduledAt);
        Session::flash('_success', 'Rendez-vous ' . $appointment['reference'] . ' mis à jour.');
        $this->back([], '/gestion/rendez-vous/');
    }
}
