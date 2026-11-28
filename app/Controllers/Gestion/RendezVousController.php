<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Session;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Appointment;
use App\Models\Note;
use App\Services\Audit;
use App\Models\User;
use App\Services\Access;
use App\Services\FormInput;
use App\Services\Notifier;

/** Rendez-vous demandés sur le site (/gestion/rendez-vous/) : confirmation avec date et heure. */
final class RendezVousController extends EspaceController
{
    public function index(): void
    {
        $status = $this->filter('statut');
        $status = $status !== null && isset(Appointment::STATUSES[$status]) ? $status : null;

        $me = Auth::user();
        if (Access::agendaCommercial($me)) {
            $this->page('rendez-vous-commercial', 'rendez-vous', 'Mes rendez-vous', [
                'appointments' => Appointment::search($status, $me),
                'status' => $status,
                'counts' => Appointment::countsByStatus($me),
            ]);
            return;
        }
        $canAssign = Appointment::peutAttribuer($me);
        $appointments = Appointment::search($status, $me);
        $day = FormInput::date(is_string($_GET['jour'] ?? null) ? $_GET['jour'] : '') ?? FormInput::today();
        // Période en heure locale de l'agence du compte (B2), bornes converties en UTC
        $zone = Appointment::zone($me['agency_id'] ? (int) $me['agency_id'] : null);
        $utc = new \DateTimeZone('UTC');
        $from = new \DateTimeImmutable(($canAssign ? $day : $day->modify('monday this week'))->format('Y-m-d') . ' 00:00:00', $zone);
        $until = $from->modify($canAssign ? '+1 day' : '+7 days');
        $calendar = [];
        foreach (Appointment::calendar($me, $from->setTimezone($utc)->format('Y-m-d H:i:s'), $until->setTimezone($utc)->format('Y-m-d H:i:s')) as $appointment) {
            $appointment['heure_locale'] = Appointment::local($appointment['starts_at_utc'], $zone, 'H:i');
            $group = $canAssign ? (int) $appointment['assigned_to'] : Appointment::local($appointment['starts_at_utc'], $zone, 'Y-m-d');
            $calendar[$group][] = $appointment;
        }
        ksort($calendar);
        $this->page('rendez-vous', 'rendez-vous', 'Agenda', [
            'appointments' => $appointments,
            'counts' => Appointment::countsByStatus($me),
            'members' => $canAssign ? array_filter(User::staff(true), Access::peutTenirRendezVous(...)) : [],
            'canAssign' => $canAssign,
            'calendar' => $calendar,
            'agendaDay' => $day,
            'zone' => $zone,
            'ville' => Appointment::city($me['agency_id'] ? (int) $me['agency_id'] : null),
            'availability' => $this->availability((int) $me['id']),
            'status' => $status,
            'durations' => $me['role'] === 'admin' ? Appointment::durations() : [],
        ]);
    }

    public function saveDuration(): void
    {
        if (Auth::user()['role'] !== 'admin') {
            $this->refuser('agenda', 'durees', 'réglage réservé à l’administration');
        }
        $motive = FormInput::text('motive', 30);
        $minutes = filter_var($_POST['minutes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 15, 'max_range' => 180]]);
        if (!isset(Appointment::MOTIVES[$motive]) || $minutes === false) {
            $this->back(['duration' => 'Choisissez un motif et une durée de 15 à 180 minutes.'], '/gestion/rendez-vous/#durees');
        }
        Appointment::setDuration($motive, $minutes);
        Session::flash('_success', 'Durée enregistrée.');
        redirect_to('/gestion/rendez-vous/#durees');
    }

    public function saveAvailability(): void
    {
        $me = Auth::user();
        if (!Access::peutTenirRendezVous($me)) {
            $this->refuser('agenda', 'disponibilites', 'réglage réservé à l’équipe qui tient les rendez-vous');
        }
        $db = Database::connection();
        $kind = FormInput::text('kind', 20);
        $start = FormInput::text('start', 16);
        $end = FormInput::text('end', 16);
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT agency_id FROM users WHERE id = ? FOR UPDATE');
            $lock->execute([$me['id']]);
            $agency = $lock->fetchColumn();
            if ($kind === 'rule') {
                $day = (int) FormInput::text('weekday', 1);
                $mode = FormInput::text('mode', 10);
                if (!$agency || $day < 1 || $day > 7 || !in_array($mode, ['agence', 'visio'], true)
                    || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start)
                    || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $end) || $start >= $end) {
                    throw new \DomainException('Indiquez une plage horaire et rattachez votre compte à une agence.');
                }
                $db->prepare('INSERT INTO availability_rules (user_id, agency_id, weekday, starts_at, ends_at, mode) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = id')
                    ->execute([$me['id'], $agency, $day, $start, $end, $mode]);
            } elseif ($kind === 'absence') {
                // Absence saisie en heure locale de l'agence du membre, enregistrée en UTC (B2)
                $utc = new \DateTimeZone('UTC');
                $memberZone = Appointment::zone($agency ? (int) $agency : null);
                $first = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start, $memberZone);
                $last = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end, $memberZone);
                if (!$first || !$last || $first->format('Y-m-d\TH:i') !== $start || $last->format('Y-m-d\TH:i') !== $end || $first >= $last) {
                    throw new \DomainException('Indiquez un début et une fin valides.');
                }
                $db->prepare('INSERT INTO availability_exceptions (user_id, starts_at_utc, ends_at_utc) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = id')
                    ->execute([$me['id'], $first->setTimezone($utc)->format('Y-m-d H:i:s'), $last->setTimezone($utc)->format('Y-m-d H:i:s')]);
            } elseif (in_array($kind, ['delete-rule', 'delete-absence'], true)) {
                $table = $kind === 'delete-rule' ? 'availability_rules' : 'availability_exceptions';
                $db->prepare("DELETE FROM {$table} WHERE id = ? AND user_id = ?")
                    ->execute([(int) FormInput::text('id', 20), $me['id']]);
            } else {
                throw new \DomainException('Action de disponibilité inconnue.');
            }
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            if (!$exception instanceof \DomainException) {
                throw $exception;
            }
            $this->back(['availability' => $exception->getMessage()], '/gestion/rendez-vous/');
        }
        Session::flash('_success', 'Disponibilités mises à jour.');
        redirect_to('/gestion/rendez-vous/#disponibilites');
    }

    private function availability(int $userId): array
    {
        $db = Database::connection();
        $rules = $db->prepare('SELECT * FROM availability_rules WHERE user_id = ? ORDER BY weekday, starts_at');
        $rules->execute([$userId]);
        $absences = $db->prepare('SELECT * FROM availability_exceptions WHERE user_id = ? AND ends_at_utc >= UTC_TIMESTAMP() ORDER BY starts_at_utc');
        $absences->execute([$userId]);
        return ['rules' => $rules->fetchAll(), 'absences' => $absences->fetchAll()];
    }

    public function update(string $reference): void
    {
        $me = Auth::user();
        if (!Access::peutTenirRendezVous($me)) {
            $this->refuser('rendez-vous', $reference, 'confirmation réservée à l’accueil');
        }
        $appointment = Appointment::findByReference($reference) ?? $this->abort(404);
        if (!Appointment::peutAttribuer($me) && (int) $appointment['assigned_to'] !== (int) $me['id']) {
            $this->refuser('rendez-vous', $reference, 'hors périmètre');
        }
        $status = FormInput::text('status', 20);
        $status = $status === 'termine' ? 'honore' : $status;
        $scheduled = FormInput::text('scheduled_at', 16);
        $end = FormInput::text('ends_at', 16);
        $assignee = Appointment::peutAttribuer($me) && array_key_exists('assigned_to', $_POST)
            ? ((int) FormInput::text('assigned_to', 20) ?: null)
            : ($appointment['assigned_to'] === null ? null : (int) $appointment['assigned_to']);
        $scheduledAt = null;
        $endsAt = null;
        $key = 'rdv_' . $appointment['id'];

        $errors = [];
        if (!isset(Appointment::STATUSES[$status])) {
            $errors[$key] = 'Statut inconnu.';
        }
        // Heure saisie dans le fuseau de l'agence du rendez-vous (B2)
        $timezone = Appointment::zone($appointment['agency_id'] ? (int) $appointment['agency_id'] : null);
        foreach (['scheduledAt' => $scheduled, 'endsAt' => $end] as $field => $value) {
            if ($value === '') {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
            if ($date === false || $date->format('Y-m-d\TH:i') !== $value) {
                $errors[$key] = 'Date et heure non valides.';
            } else {
                $$field = $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }
        if ($scheduledAt !== null && $end === '') {
            $minutes = (int) (Appointment::durations()[$appointment['motive']] ?? 30);
            $endsAt = (new \DateTimeImmutable($scheduledAt, new \DateTimeZone('UTC')))->modify("+$minutes minutes")->format('Y-m-d H:i:s');
        }
        if ($status === 'confirme' && $scheduledAt === null) {
            $errors[$key] = 'Indiquez la date et l’heure pour confirmer.';
        }
        if ($errors !== []) {
            $this->back($errors, '/gestion/rendez-vous/');
        }

        try {
            $replacement = Appointment::planifier($me, (int) $appointment['id'], $status, $assignee, $scheduledAt, $endsAt);
        } catch (\DomainException $exception) {
            $this->back([$key => $exception->getMessage()], '/gestion/rendez-vous/');
        }
        if ($replacement !== null) {
            Notifier::appointmentScheduleMail($replacement, $appointment);
            Session::flash('_success', 'Rendez-vous reporté. Nouvelle référence ' . $replacement['reference'] . '.');
            redirect_to($this->retourAgenda());
        }
        Notifier::appointmentUpdated($appointment, $status, $scheduledAt);
        Session::flash('_success', 'Rendez-vous ' . $appointment['reference'] . ' mis à jour.');
        redirect_to($this->retourAgenda());
    }

    /** Après un POST réussi : 303 vers la page d'agenda consultée (jour gardé), jamais une adresse externe. */
    /** Formulaire du compte rendu d'un entretien (E5, N3), ouvert depuis l'agenda. */
    public function compteRendu(string $reference): void
    {
        [$rdv] = $this->rendezVousPourCompteRendu($reference);
        $zone = Appointment::zone($rdv['agency_id'] ? (int) $rdv['agency_id'] : null);
        $this->page('compte-rendu', 'rendez-vous', 'Compte rendu · ' . $rdv['first_name'] . ' ' . $rdv['last_name'], [
            'rdv' => $rdv,
            'quand' => $rdv['starts_at_utc'] ? Appointment::local($rdv['starts_at_utc'], $zone, 'd/m/Y à H:i') . ', heure de ' . Appointment::city($rdv['agency_id'] ? (int) $rdv['agency_id'] : null) : '',
            'dejaRedige' => Note::existePourRendezVous((int) $rdv['id']),
        ]);
    }

    public function enregistrerCompteRendu(string $reference): void
    {
        [$rdv, $me, $poste] = $this->rendezVousPourCompteRendu($reference);
        $retour = '/gestion/rendez-vous/' . $rdv['reference'] . '/compte-rendu/';
        [$visibilite, $texte] = PersonneController::saisieNote();
        if ($visibilite === null || $texte === '') {
            $this->back(['note' => $visibilite === null ? 'Choisissez « Interne » ou « Partagé » : aucun choix n’est fait par défaut.' : 'Écrivez le compte rendu.'], $retour);
        }
        try {
            $note = Note::compteRendu($me, $poste, (int) $rdv['id'], $visibilite, $texte);
        } catch (\DomainException $exception) {
            $this->back(['note' => $exception->getMessage()], $retour);
        }
        Audit::log('compte_rendu', 'rendez-vous', $rdv['reference'], ['note' => (int) $note['id'], 'visibilite' => $visibilite]);
        if ($visibilite === 'partagee') {
            Notifier::noteShared((int) $rdv['person_id']);
        }
        Session::flash('_success', 'Compte rendu enregistré' . ($visibilite === 'partagee' ? ' et partagé avec la personne.' : ' (interne).'));
        redirect_to('/gestion/personnes/' . $rdv['person_id'] . '/#notes');
    }

    /**
     * Rendez-vous du périmètre agenda dont ce compte peut rédiger le compte rendu : responsable lui-même ou
     * administrateur, poste d'écriture (E4). 404 s'il n'existe pas, 403 journalisé sinon.
     *
     * @return array{0: array, 1: array, 2: string}
     */
    private function rendezVousPourCompteRendu(string $reference): array
    {
        $me = Auth::user();
        if (!Access::peutTenirRendezVous($me)) {
            $this->refuser('rendez-vous', $reference, 'compte rendu non permis');
        }
        $rdv = Appointment::findByReference($reference) ?? $this->abort(404);
        $poste = Access::posteEcriture($me);
        $admin = in_array('admin', Access::postes($me), true);
        if ($poste === null || (!$admin && (int) $rdv['assigned_to'] !== (int) $me['id'])) {
            $this->refuser('rendez-vous', $reference, 'compte rendu non permis');
        }

        return [$rdv, $me, $poste];
    }

    private function retourAgenda(): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path = (string) parse_url($referer, PHP_URL_PATH);
        $query = (string) parse_url($referer, PHP_URL_QUERY);
        $memeHote = parse_url($referer, PHP_URL_HOST) === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        if (!$memeHote || !str_starts_with($path, $base . '/gestion/rendez-vous/')) {
            return '/gestion/rendez-vous/';
        }

        return substr($path, strlen($base)) . ($query !== '' ? '?' . $query : '');
    }
}
