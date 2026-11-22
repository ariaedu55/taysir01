<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Appointment;
use App\Services\FormInput;
use App\Services\Notifier;

/** Prise de rendez-vous, ouverte à tous, à l'adresse WordPress /rendez_vous_orientation/. */
final class AppointmentController extends Controller
{
    /** Rendez-vous envoyés par session et par heure, contre les envois en rafale. */
    private const HOURLY_LIMIT = 5;

    public function create(): void
    {
        $motive = (string) ($_GET['motif'] ?? '');

        $this->render('pages/appointment', [
            'user' => Auth::user(),
            'motive' => array_key_exists($motive, Appointment::MOTIVES) ? $motive : '',
            'minDate' => FormInput::today()->modify('+1 day')->format('Y-m-d'),
            'maxDate' => FormInput::today()->modify('+6 months')->format('Y-m-d'),
            'title' => 'Prendre rendez-vous',
            'description' => 'Rencontrez un conseiller Taysir en agence à Dakar ou à Ziguinchor, par téléphone, sur WhatsApp ou en visio : orientation, admission, visa, logement, voyage.',
            'canonical' => url('/rendez_vous_orientation/'),
            'styles' => ['css/destination.css', 'css/content.css', 'css/auth.css', 'css/portal.css'],
            'scripts' => ['js/portal.js'],
        ]);
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
        } elseif ($date->format('N') === '7') {
            $errors['preferred_date'] = 'L’agence est fermée le dimanche : choisissez un autre jour.';
        }
        if (!array_key_exists($data['preferred_slot'], Appointment::SLOTS)) {
            $errors['preferred_slot'] = 'Choisissez un moment de la journée.';
        } elseif ($date !== null && $date->format('N') === '6' && $data['preferred_slot'] === 'apres-midi' && !isset($errors['preferred_date'])) {
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

        $sent = array_filter((array) Session::get('_appointments_sent', []), static fn ($time) => $time > time() - 3600);
        if ($errors === [] && count($sent) >= self::HOURLY_LIMIT) {
            $errors['motive'] = 'Vous avez déjà envoyé plusieurs demandes de rendez-vous. Écrivez-nous sur WhatsApp si c’est urgent.';
        }
        if ($errors !== []) {
            Session::flash('_notice', 'Certains champs sont à compléter ou à corriger.');
            $this->back($errors, '/rendez_vous_orientation/#formulaire');
        }

        $appointment = Appointment::create($data, Auth::id());
        Notifier::appointmentRequested($appointment);
        $sent[] = time();
        Session::set('_appointments_sent', array_values($sent));
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
            'title' => 'Rendez-vous demandé',
            'description' => 'Votre demande de rendez-vous est enregistrée.',
            'robots' => 'noindex',
            'styles' => ['css/destination.css', 'css/content.css', 'css/auth.css', 'css/portal.css'],
        ]);
    }
}
