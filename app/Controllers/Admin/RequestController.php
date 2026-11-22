<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\DocumentStorage;
use App\Services\FormInput;
use App\Services\Notifier;

/** Back-office : traitement des demandes et des rendez-vous (conseillers et administrateurs). */
final class RequestController extends Controller
{
    private const STYLES = ['css/auth.css', 'css/portal.css'];

    public function index(): void
    {
        $status = $this->filter('statut');
        $service = $this->filter('service');
        $assigned = in_array($this->filter('conseiller'), ['moi', 'aucun'], true) ? $this->filter('conseiller') : null;
        $me = (int) Auth::id();

        $this->render('admin/requests', [
            'requests' => ServiceRequest::search($status, $service, $assigned, $me),
            'assigned' => $assigned,
            'assignmentCounts' => ServiceRequest::assignmentCounts($me),
            'counts' => ServiceRequest::countsByStatus(),
            'services' => ServiceRequest::services(),
            'status' => $status,
            'service' => $service,
            'title' => 'Demandes · Back-office',
            'robots' => 'noindex',
            'styles' => self::STYLES,
        ]);
    }

    public function show(string $reference): void
    {
        $request = ServiceRequest::findByReference($reference) ?? $this->abort(404);

        $this->render('admin/request', [
            'request' => $request,
            'service' => ServiceRequest::service($request['service']),
            'documents' => ServiceRequest::documents((int) $request['id']),
            'events' => ServiceRequest::events((int) $request['id'], true),
            'staff' => User::staff(true),
            'me' => Auth::user(),
            'title' => $request['reference'] . ' · Back-office',
            'robots' => 'noindex',
            'styles' => self::STYLES,
        ]);
    }

    public function update(string $reference): void
    {
        $request = ServiceRequest::findByReference($reference) ?? $this->abort(404);
        $back = '/admin/demandes/' . $request['reference'] . '/';
        $status = FormInput::text('status', 20);
        $note = FormInput::multiline('note');
        $visible = !empty($_POST['visible']);

        if (!isset(ServiceRequest::STATUSES[$status])) {
            $this->back(['status' => 'Statut inconnu.'], $back);
        }
        if ($status === $request['status'] && $note === '') {
            $this->back(['note' => 'Changez le statut ou écrivez une note.'], $back);
        }

        ServiceRequest::update($request, $status, $note, $visible, (int) Auth::id());
        Notifier::requestUpdated($request, $status, $note, $visible);
        Session::flash('_success', 'Demande mise à jour.');
        redirect_to($back);
    }

    /** Attribution à un conseiller : « moi », un identifiant de l'équipe, ou vide pour libérer la demande. */
    public function assign(string $reference): void
    {
        $request = ServiceRequest::findByReference($reference) ?? $this->abort(404);
        $back = '/admin/demandes/' . $request['reference'] . '/';
        $me = Auth::user();
        $choice = (string) ($_POST['assigned_to'] ?? '');
        $assignee = null;
        if ($choice === 'moi') {
            $assignee = $me;
        } elseif ($choice !== '') {
            $assignee = ctype_digit($choice) ? User::find((int) $choice) : null;
            if ($assignee === null || !in_array($assignee['role'], ['conseiller', 'admin'], true) || empty($assignee['active'])) {
                $this->back(['assigned_to' => 'Choisissez un membre actif de l’équipe.'], $back);
            }
        }
        if ((int) ($assignee['id'] ?? 0) === (int) ($request['assigned_to'] ?? 0)) {
            redirect_to($back);
        }

        ServiceRequest::assign($request, $assignee, (int) $me['id']);
        if ($assignee !== null && (int) $assignee['id'] !== (int) $me['id']) {
            Notifier::requestAssigned($request, $assignee, $me['first_name'] . ' ' . $me['last_name']);
        }
        Session::flash('_success', $assignee === null
            ? 'La demande n’est plus attribuée.'
            : ((int) $assignee['id'] === (int) $me['id'] ? 'La demande vous est attribuée.' : 'Demande attribuée à ' . $assignee['first_name'] . ' ' . $assignee['last_name'] . ', qui est prévenu(e) par e-mail.'));
        redirect_to($back);
    }

    public function document(string $reference, string $id): void
    {
        $request = ServiceRequest::findByReference($reference) ?? $this->abort(404);
        $document = ctype_digit($id) ? ServiceRequest::document((int) $request['id'], (int) $id) : null;
        DocumentStorage::send($document ?? $this->abort(404));
    }

    public function appointments(): void
    {
        $status = $this->filter('statut');

        $this->render('admin/appointments', [
            'appointments' => Appointment::search($status),
            'counts' => Appointment::countsByStatus(),
            'status' => $status,
            'title' => 'Rendez-vous · Back-office',
            'robots' => 'noindex',
            'styles' => self::STYLES,
        ]);
    }

    public function updateAppointment(string $reference): void
    {
        $appointment = Appointment::findByReference($reference) ?? $this->abort(404);
        $status = FormInput::text('status', 20);
        $scheduled = FormInput::text('scheduled_at', 16);
        $scheduledAt = null;

        $errors = [];
        if (!isset(Appointment::STATUSES[$status])) {
            $errors['status_' . $appointment['id']] = 'Statut inconnu.';
        }
        if ($scheduled !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $scheduled);
            if ($date === false) {
                $errors['status_' . $appointment['id']] = 'Date et heure non valides.';
            } else {
                $scheduledAt = $date->format('Y-m-d H:i:s');
            }
        }
        if ($status === 'confirme' && $scheduledAt === null) {
            $errors['status_' . $appointment['id']] = 'Indiquez la date et l’heure pour confirmer.';
        }
        if ($errors !== []) {
            $this->back($errors, '/admin/rendez-vous/');
        }

        Appointment::update((int) $appointment['id'], $status, $scheduledAt);
        Notifier::appointmentUpdated($appointment, $status, $scheduledAt);
        Session::flash('_success', 'Rendez-vous ' . $appointment['reference'] . ' mis à jour.');
        $this->back([], '/admin/rendez-vous/');
    }

    private function filter(string $key): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
