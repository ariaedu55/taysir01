<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Appointment;
use App\Services\FormInput;
use App\Services\Notifier;
use App\Services\Audit;
use App\Services\BookingLimiter;
use App\Services\LimiteAtteinte;

/** Prise de rendez-vous, ouverte à tous, à l'adresse WordPress /rendez_vous_orientation/. */
final class AppointmentController extends Controller
{
    public function create(): void
    {
        $motive = (string) ($_GET['motif'] ?? '');
        $direct = Appointment::directEnabled();
        $bookingToken = '';
        if ($direct) {
            $tokens = Session::get('_booking_forms', []);
            $bookingToken = bin2hex(random_bytes(16));
            $tokens[$bookingToken] = '';
            Session::set('_booking_forms', array_slice($tokens, -20, null, true));
        }

        $this->render('pages/appointment', [
            'user' => Auth::user(),
            'motive' => array_key_exists($motive, Appointment::MOTIVES) ? $motive : '',
            'direct' => $direct,
            'bookingToken' => $bookingToken,
            'minDate' => FormInput::today()->modify('+1 day')->format('Y-m-d'),
            'maxDate' => FormInput::today()->modify('+6 months')->format('Y-m-d'),
            'title' => 'Prendre rendez-vous',
            'description' => 'Rencontrez un conseiller Taysir en agence à Dakar ou à Ziguinchor, par téléphone, sur WhatsApp ou en visio : orientation, admission, visa, logement, voyage.',
            'canonical' => url('/rendez_vous_orientation/'),
            'styles' => ['css/destination.css', 'css/content.css', 'css/auth.css', 'css/portal.css'],
            'scripts' => ['js/portal.js'],
        ]);
    }

    public function slots(): void
    {
        $query = static fn (string $key): string => is_string($_GET[$key] ?? null) ? $_GET[$key] : '';
        $slots = Appointment::directEnabled() ? Appointment::slots($query('date'), $query('motif'), $query('mode')) : [];
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(array_keys($slots), JSON_THROW_ON_ERROR);
    }

    public function store(): void
    {
        $data = [
            'motive' => FormInput::text('motive', 30),
            'mode' => FormInput::text('mode', 30),
            'preferred_date' => FormInput::text('preferred_date', 10),
            'preferred_slot' => FormInput::text('preferred_slot', 20),
            'first_name' => FormInput::text('first_name', 80),
            'last_name' => FormInput::text('last_name', 80),
            'email' => strtolower(FormInput::text('email', 190)),
            'phone' => FormInput::phone('phone'),
            'message' => FormInput::multiline('message'),
        ];

        if (FormInput::isBot()) {
            // Réponse identique à un envoi réussi, sans rien enregistrer.
            redirect_to('/rendez_vous_orientation/');
        }

        $errors = [];
        $time = Appointment::directEnabled() ? FormInput::text('direct_time', 5) : '';
        $token = FormInput::text('booking_token', 32);
        $tokens = Session::get('_booking_forms', []);
        if ($time !== '') {
            if (!array_key_exists($token, $tokens)) {
                $this->back(['direct_time' => 'Rechargez les créneaux avant de confirmer.'], '/rendez_vous_orientation/#formulaire');
            }
            if ($tokens[$token] !== '') {
                Session::flash('_appointment', $tokens[$token]);
                redirect_to('/rendez_vous_orientation/confirmation/');
            }
            $data['preferred_slot'] = $time < '13:00' ? 'matin' : 'apres-midi';
        }
        if (!array_key_exists($data['motive'], Appointment::MOTIVES)) {
            $errors['motive'] = 'Choisissez l’objet du rendez-vous.';
        }
        if (!array_key_exists($data['mode'], Appointment::MODES)) {
            $errors['mode'] = 'Choisissez comment vous souhaitez échanger.';
        }
        $date = FormInput::date($data['preferred_date']);
        $today = FormInput::today();
        if ($date === null) {
            $errors['preferred_date'] = 'Choisissez une date.';
        } elseif ($date <= $today || $date > $today->modify('+6 months')) {
            $errors['preferred_date'] = 'Choisissez une date entre demain et les six prochains mois.';
        } elseif ($time === '' && $date->format('N') === '7') {
            $errors['preferred_date'] = 'L’agence est fermée le dimanche : choisissez un autre jour.';
        }
        if (!array_key_exists($data['preferred_slot'], Appointment::SLOTS)) {
            $errors['preferred_slot'] = 'Choisissez un moment de la journée.';
        } elseif ($time === '' && $date !== null && $date->format('N') === '6' && $data['preferred_slot'] === 'apres-midi' && !isset($errors['preferred_date'])) {
            $errors['preferred_slot'] = 'Le samedi, l’agence ferme à 14 h : choisissez le matin ou un autre jour.';
        }
        if ($data['first_name'] === '') {
            $errors['first_name'] = 'Indiquez votre prénom.';
        }
        if ($data['last_name'] === '') {
            $errors['last_name'] = 'Indiquez votre nom.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        }
        if (!FormInput::validPhone($data['phone'])) {
            $errors['phone'] = 'Indiquez un numéro de téléphone complet.';
        }

        if ($errors !== []) {
            Session::flash('_notice', 'Certains champs sont à compléter ou à corriger.');
            $this->back($errors, '/rendez_vous_orientation/#formulaire');
        }

        // Anti-abus C6 (condition B1) : par IP, par e-mail et plafond de rendez-vous actifs, vérifiés, créés et comptés
        // sous un même verrou (des envois simultanés ne passent pas entre la vérification et le comptage)
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        try {
            $appointment = BookingLimiter::proteger($ip, $data['email'], static fn (): array => Appointment::create($data, Auth::id(), $time !== '' ? $time : null));
        } catch (LimiteAtteinte $exception) {
            Session::flash('_notice', $exception->getMessage());
            $this->back(['motive' => $exception->getMessage()], '/rendez_vous_orientation/#formulaire');
        } catch (\DomainException $exception) {
            $this->back(['direct_time' => $exception->getMessage()], '/rendez_vous_orientation/#formulaire');
        }
        if ($appointment['status'] === 'confirme') {
            $tokens[$token] = $appointment['reference'];
            Session::set('_booking_forms', $tokens);
            Notifier::appointmentUpdated(array_replace($appointment, ['status' => 'demande']), 'confirme', $appointment['starts_at_utc']);
        } else {
            Notifier::appointmentRequested($appointment);
        }
        Session::flash('_appointment', $appointment['reference']);
        redirect_to('/rendez_vous_orientation/confirmation/');
    }

    public function confirmation(): void
    {
        $reference = Session::get('_appointment');
        $appointment = is_string($reference) ? Appointment::findByReference($reference) : null;
        if ($appointment === null) {
            redirect_to('/rendez_vous_orientation/');
        }

        $this->render('pages/appointment-confirmed', [
            'appointment' => $appointment,
            'user' => Auth::user(),
            'title' => $appointment['status'] === 'confirme' ? 'Rendez-vous confirmé' : 'Rendez-vous demandé',
            'description' => 'Votre demande de rendez-vous est enregistrée.',
            'robots' => 'noindex',
            'styles' => ['css/destination.css', 'css/content.css', 'css/auth.css', 'css/portal.css'],
        ]);
    }

    public function cancel(string $reference): void
    {
        $appointment = Appointment::findByReference($reference) ?? $this->abort(404);
        $id = (int) Auth::id();
        if (!Appointment::belongsToUser($reference, $id)) {
            Audit::log('acces_refuse', 'rendez-vous', $reference, ['motif' => 'annulation hors périmètre']);
            $this->abort(403);
        }
        if (!Appointment::cancelForUser($reference, $id)) {
            Session::flash('_notice', 'Ce rendez-vous ne peut plus être annulé en ligne.');
            redirect_to('/mon-espace/demandes/#rdv-titre');
        }
        Notifier::appointmentUpdated($appointment, 'annule', null);
        Session::flash('_success', 'Rendez-vous annulé.');
        redirect_to('/mon-espace/demandes/#rdv-titre');
    }

    public function guestCancellation(): void
    {
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        $token = $_GET['jeton'] ?? '';
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $forms = Session::get('_guest_cancel_forms', []);
            $form = bin2hex(random_bytes(16));
            $forms[$form] = $token;
            Session::set('_guest_cancel_forms', array_slice($forms, -20, null, true));
            redirect_to('/rendez_vous_orientation/annuler/?formulaire=' . $form);
        }
        $form = is_string($_GET['formulaire'] ?? null) ? $_GET['formulaire'] : '';
        $forms = Session::get('_guest_cancel_forms', []);
        $valide = isset($forms[$form]);
        $this->render('pages/appointment-cancellation', [
            'title' => 'Annuler un rendez-vous',
            'robots' => 'noindex',
            'form' => $valide ? $form : null,
            // Rappel du rendez-vous visé, sans consommer le jeton (seule la confirmation annule)
            'appointment' => $valide ? Appointment::findByCancellationToken((string) $forms[$form]) : null,
            'result' => Session::get('_guest_cancel_result'),
            'styles' => ['css/destination.css', 'css/content.css', 'css/auth.css', 'css/portal.css'],
        ]);
    }

    public function confirmGuestCancellation(): void
    {
        $form = FormInput::text('formulaire', 32);
        $forms = Session::get('_guest_cancel_forms', []);
        $token = $forms[$form] ?? null;
        unset($forms[$form]);
        Session::set('_guest_cancel_forms', $forms);
        $appointment = is_string($token) ? Appointment::cancelGuest($token) : null;
        Session::flash('_guest_cancel_result', $appointment !== null);
        if ($appointment !== null) {
            Notifier::appointmentUpdated($appointment, 'annule', null);
        }
        redirect_to('/rendez_vous_orientation/annuler/');
    }
}
