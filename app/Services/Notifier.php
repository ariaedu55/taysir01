<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use App\Models\Appointment;
use App\Models\MailOutbox;
use App\Models\ServiceRequest;

/**
 * E-mails envoyés au client et à l'agence pour chaque événement du portail (docs/notifications-email.md).
 * Chaque méthode met les e-mails en file d'attente : un problème d'envoi n'interrompt jamais l'action.
 * Les e-mails ne contiennent ni document ni numéro de pièce d'identité.
 */
final class Notifier
{
    public static function welcome(array $user): void
    {
        self::toClient('welcome', $user['email'], $user['first_name'] . ' ' . $user['last_name'], 'Bienvenue dans votre espace Taysir', [
            'preheader' => 'Votre compte est prêt : demandes, documents et rendez-vous au même endroit.',
            'heading' => 'Bienvenue ' . $user['first_name'] . ' !',
            'paragraphs' => [
                'Votre espace Taysir Orientation Voyage est prêt. Vous pouvez y faire vos demandes (admission, visa, logement, prise en charge, billet d’avion, hôtel), joindre vos documents et suivre l’avancement de chaque dossier.',
            ],
            'button' => ['Ouvrir mon espace', absolute_url('/mon-espace/')],
            'after' => ['Besoin d’un premier conseil ? Prenez rendez-vous avec un conseiller, en agence, au téléphone ou en visio.'],
        ]);
    }

    /** Lien de réinitialisation : le jeton n'est jamais enregistré en clair, il n'existe que dans cet e-mail. */
    public static function passwordReset(array $user, string $token): void
    {
        self::toClient('password-reset', $user['email'], $user['first_name'] . ' ' . $user['last_name'], 'Réinitialiser votre mot de passe Taysir', [
            'preheader' => 'Ce lien est valable une heure.',
            'heading' => 'Nouveau mot de passe',
            'paragraphs' => [
                'Bonjour ' . $user['first_name'] . ',',
                'Vous avez demandé à réinitialiser le mot de passe de votre espace Taysir. Choisissez-en un nouveau avec le bouton ci-dessous.',
            ],
            'button' => ['Choisir un nouveau mot de passe', absolute_url('/reinitialiser-mot-de-passe/' . $token . '/')],
            'after' => [
                'Ce lien est valable une heure et ne fonctionne qu’une fois.',
                'Vous n’êtes pas à l’origine de cette demande ? Ignorez cet e-mail : votre mot de passe reste inchangé.',
            ],
        ]);
    }

    /** Invitation d'un membre de l'équipe : lien pour choisir son mot de passe (valable 72 heures). */
    public static function staffInvitation(array $user, string $token, ?string $invitedBy): void
    {
        $role = $user['role'] === 'admin' ? 'administrateur' : 'conseiller';
        self::toClient('staff-invitation', $user['email'], $user['first_name'] . ' ' . $user['last_name'], 'Votre accès au back-office Taysir', [
            'preheader' => 'Choisissez votre mot de passe pour accéder au back-office.',
            'heading' => 'Bienvenue dans l’équipe, ' . $user['first_name'],
            'paragraphs' => [
                ($invitedBy ? $invitedBy . ' vous a créé' : 'Un compte vous a été créé') . ' un accès ' . $role . ' au back-office de Taysir Orientation Voyage : demandes des clients, rendez-vous et messages.',
                'Pour l’activer, choisissez votre mot de passe.',
            ],
            'details' => ['Identifiant' => $user['email'], 'Rôle' => ucfirst($role)],
            'button' => ['Choisir mon mot de passe', absolute_url('/reinitialiser-mot-de-passe/' . $token . '/')],
            'after' => [
                'Ce lien est valable 72 heures et ne fonctionne qu’une fois. Passé ce délai, demandez à un administrateur de vous renvoyer l’invitation.',
                'Ensuite, connectez-vous sur ' . absolute_url('/connexion/') . '.',
            ],
        ]);
    }

    /** Une demande est attribuée à un conseiller par un autre membre de l'équipe. */
    public static function requestAssigned(array $request, array $assignee, string $assignedBy): void
    {
        $service = ServiceRequest::service($request['service']);
        $name = $request['first_name'] . ' ' . $request['last_name'];
        self::queue('request-assigned', $assignee['email'], $assignee['first_name'] . ' ' . $assignee['last_name'],
            'Demande attribuée : ' . ($service['short'] ?? 'Demande') . ' · ' . $name . ' (' . $request['reference'] . ')', [
                'preheader' => $assignedBy . ' vous a attribué cette demande.',
                'heading' => 'Une demande vous est attribuée',
                'paragraphs' => [$assignedBy . ' vous a attribué la demande ' . $request['reference'] . '.'],
                'details' => array_filter([
                    'Service' => $service['title'] ?? '',
                    'Client' => $name,
                    'Téléphone' => $request['phone'],
                    'Destination' => (string) $request['destination'],
                    'Statut' => ServiceRequest::statusLabel($request['status']),
                ], static fn ($value) => $value !== ''),
                'button' => ['Ouvrir la demande', absolute_url('/gestion/dossiers/' . $request['reference'] . '/')],
                'audience' => 'agence',
            ], $request['email'], $request['reference']);
    }

    public static function passwordChanged(array $user): void
    {
        self::toClient('password-changed', $user['email'], $user['first_name'] . ' ' . $user['last_name'], 'Votre mot de passe a été modifié', [
            'preheader' => 'Le mot de passe de votre espace Taysir vient de changer.',
            'heading' => 'Mot de passe modifié',
            'paragraphs' => [
                'Bonjour ' . $user['first_name'] . ',',
                'Le mot de passe de votre espace Taysir a été modifié le ' . date('d/m/Y à H\hi') . '.',
            ],
            'button' => ['Ouvrir mon espace', absolute_url('/connexion/')],
            'after' => ['Ce n’était pas vous ? Contactez immédiatement l’agence au ' . config('site.contact.phone') . ' ou sur WhatsApp.'],
        ]);
    }

    public static function requestCreated(array $request, int $documentCount): void
    {
        $service = ServiceRequest::service($request['service']);
        $title = $service['title'] ?? 'Demande';
        $name = $request['first_name'] . ' ' . $request['last_name'];

        self::toClient('request-created', $request['email'], $name, 'Votre demande ' . $request['reference'] . ' est bien reçue', [
            'preheader' => $title . ' : un conseiller revient vers vous rapidement.',
            'heading' => 'Votre demande est bien reçue',
            'paragraphs' => [
                'Bonjour ' . $request['first_name'] . ',',
                'Merci pour votre confiance. Votre demande « ' . $title . ' » est enregistrée : un conseiller Taysir l’étudie et revient vers vous rapidement.',
            ],
            'details' => array_filter([
                'Référence' => $request['reference'],
                'Service' => $title,
                'Destination' => (string) $request['destination'],
                'Documents joints' => (string) $documentCount,
            ], static fn ($value) => $value !== ''),
            'button' => ['Suivre ma demande', absolute_url('/mon-espace/demandes/' . $request['reference'] . '/')],
            'after' => ['Vous serez prévenu(e) par e-mail à chaque étape importante.'],
        ], $request['reference']);

        self::toAgency('request-agency', 'Nouvelle demande : ' . $title . ' · ' . $name . ' (' . $request['reference'] . ')', [
            'preheader' => $name . ' · ' . $request['phone'],
            'heading' => 'Nouvelle demande : ' . $title,
            'paragraphs' => [$name . ' vient d’envoyer une demande depuis son espace client.'],
            'details' => array_filter([
                'Référence' => $request['reference'],
                'Client' => $name,
                'Téléphone' => $request['phone'],
                'E-mail' => $request['email'],
                'Destination' => (string) $request['destination'],
                'Documents joints' => (string) $documentCount,
            ], static fn ($value) => $value !== ''),
            'quote' => $request['message'] ?: null,
            'quoteBy' => 'Précisions du client',
            'button' => ['Ouvrir la demande', absolute_url('/gestion/dossiers/' . $request['reference'] . '/')],
        ], $request['email'], $request['reference']);
    }

    /** Mise à jour par un conseiller : seulement si le statut change ou si une note est visible du client. */
    public static function requestUpdated(array $request, string $newStatus, string $note, bool $visible): void
    {
        $statusChanged = $newStatus !== $request['status'];
        $message = $visible ? $note : '';
        if (!$statusChanged && $message === '') {
            return;
        }
        $label = ServiceRequest::statusLabel($newStatus);
        $service = ServiceRequest::service($request['service']);

        self::toClient('request-updated', $request['email'], $request['first_name'] . ' ' . $request['last_name'],
            $statusChanged ? 'Votre demande ' . $request['reference'] . ' : ' . $label : 'Nouveau message sur votre demande ' . $request['reference'], [
                'preheader' => $statusChanged ? 'Nouveau statut : ' . $label : 'Votre conseiller vous a écrit.',
                'heading' => $statusChanged ? $label : 'Un message de votre conseiller',
                'paragraphs' => array_values(array_filter([
                    'Bonjour ' . $request['first_name'] . ',',
                    $statusChanged ? 'Votre demande « ' . ($service['title'] ?? 'Demande') . ' » (' . $request['reference'] . ') a avancé.' : null,
                    $newStatus === 'attente-client' ? 'Votre conseiller attend des éléments de votre part : répondez-lui et joignez vos documents depuis votre espace.' : null,
                ])),
                'quote' => $message !== '' ? $message : null,
                'quoteBy' => 'Votre conseiller',
                'button' => ['Voir ma demande', absolute_url('/mon-espace/demandes/' . $request['reference'] . '/')],
            ], $request['reference']);
    }

    public static function clientReplied(array $request, string $message, array $documentNames): void
    {
        $name = $request['first_name'] . ' ' . $request['last_name'];
        self::toAgency('reply-agency', 'Réponse du client sur ' . $request['reference'] . ' · ' . $name, [
            'preheader' => $documentNames !== [] ? count($documentNames) . ' document(s) ajouté(s)' : 'Nouveau message du client',
            'heading' => 'Le client a répondu',
            'paragraphs' => [$name . ' a complété sa demande ' . $request['reference'] . '.'],
            'quote' => $message !== '' ? $message : null,
            'quoteBy' => 'Message du client',
            'details' => $documentNames !== [] ? ['Documents ajoutés' => implode("\n", $documentNames)] : [],
            'button' => ['Ouvrir la demande', absolute_url('/gestion/dossiers/' . $request['reference'] . '/')],
        ], $request['email'], $request['reference']);
    }

    public static function appointmentRequested(array $appointment): void
    {
        $name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        $details = [
            'Référence' => $appointment['reference'],
            'Objet' => Appointment::MOTIVES[$appointment['motive']] ?? '',
            'Échange' => Appointment::MODES[$appointment['mode']] ?? '',
            'Jour souhaité' => self::day($appointment['preferred_date']) . ', ' . (Appointment::SLOTS[$appointment['preferred_slot']] ?? ''),
        ];

        self::toClient('appointment-created', $appointment['email'], $name, 'Votre demande de rendez-vous ' . $appointment['reference'], [
            'preheader' => 'Un conseiller vous contacte pour confirmer l’heure.',
            'heading' => 'Rendez-vous demandé',
            'paragraphs' => [
                'Bonjour ' . $appointment['first_name'] . ',',
                'Votre demande de rendez-vous est enregistrée. Un conseiller vous contacte au ' . $appointment['phone'] . ' pour confirmer l’heure exacte.',
            ],
            'details' => $details,
            'button' => $appointment['user_id'] ? ['Voir mes rendez-vous', absolute_url('/mon-espace/demandes/')] : null,
        ], $appointment['reference']);

        self::toAgency('appointment-agency', 'Rendez-vous à confirmer : ' . $name . ' (' . $appointment['reference'] . ')', [
            'preheader' => $details['Objet'] . ' · ' . $details['Jour souhaité'],
            'heading' => 'Nouveau rendez-vous à confirmer',
            'paragraphs' => [$name . ($appointment['user_id'] ? ' (compte client)' : ' (sans compte)') . ' souhaite rencontrer un conseiller.'],
            'details' => $details + ['Téléphone' => $appointment['phone'], 'E-mail' => $appointment['email']],
            'quote' => $appointment['message'] ?: null,
            'quoteBy' => 'Message',
            'button' => ['Confirmer le rendez-vous', absolute_url('/admin/rendez-vous/?statut=demande')],
        ], $appointment['email'], $appointment['reference']);
    }

    /** Confirmation (avec date et heure) ou annulation par l'agence. Les autres changements ne sont pas notifiés. */
    public static function appointmentUpdated(array $appointment, string $newStatus, ?string $scheduledAt): void
    {
        $confirmed = $newStatus === 'confirme' && $scheduledAt !== null
            && ($appointment['status'] !== 'confirme' || $appointment['scheduled_at'] !== $scheduledAt);
        $cancelled = $newStatus === 'annule' && $appointment['status'] !== 'annule';
        if (!$confirmed && !$cancelled) {
            return;
        }
        $name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        $when = $scheduledAt !== null ? self::day($scheduledAt) . ' à ' . date('H\hi', strtotime($scheduledAt)) : '';
        $mode = Appointment::MODES[$appointment['mode']] ?? '';

        self::toClient($confirmed ? 'appointment-confirmed' : 'appointment-cancelled', $appointment['email'], $name,
            $confirmed ? 'Rendez-vous confirmé le ' . $when : 'Votre rendez-vous ' . $appointment['reference'] . ' est annulé', [
                'preheader' => $confirmed ? $mode . ', ' . $when : 'Contactez-nous pour fixer une autre date.',
                'heading' => $confirmed ? 'Votre rendez-vous est confirmé' : 'Rendez-vous annulé',
                'paragraphs' => [
                    'Bonjour ' . $appointment['first_name'] . ',',
                    $confirmed
                        ? 'Votre conseiller Taysir vous attend. Merci de prévenir l’agence si vous ne pouvez pas venir.'
                        : 'Votre rendez-vous ' . $appointment['reference'] . ' est annulé. Pour en fixer un autre, prenez rendez-vous en ligne ou écrivez-nous sur WhatsApp.',
                ],
                'details' => $confirmed ? [
                    'Date et heure' => ucfirst($when),
                    'Échange' => $mode,
                    'Objet' => Appointment::MOTIVES[$appointment['motive']] ?? '',
                    'Référence' => $appointment['reference'],
                ] : [],
                'button' => $confirmed ? null : ['Prendre un autre rendez-vous', absolute_url('/rendez_vous_orientation/')],
                'after' => $confirmed && str_starts_with($appointment['mode'], 'agence') ? ['Adresse du siège : ' . config('site.contact.address') . '.'] : [],
            ], $appointment['reference']);
    }

    public static function contactReceived(array $message): void
    {
        self::toClient('contact-ack', $message['email'], $message['name'], 'Nous avons bien reçu votre message', [
            'preheader' => 'Un conseiller vous répond rapidement.',
            'heading' => 'Merci pour votre message',
            'paragraphs' => [
                'Bonjour ' . $message['name'] . ',',
                'Votre message est bien arrivé : un conseiller Taysir vous répond rapidement, par e-mail ou par téléphone.',
            ],
            'quote' => $message['message'],
            'quoteBy' => 'Votre message',
            'after' => ['Pour une réponse plus rapide, écrivez-nous sur WhatsApp : ' . config('site.contact.whatsapp')],
        ]);

        self::toAgency('contact-agency', 'Message de contact : ' . $message['name'], [
            'preheader' => $message['phone'],
            'heading' => 'Nouveau message de contact',
            'paragraphs' => [$message['name'] . ' a écrit depuis la page Contact.'],
            'details' => ['Nom' => $message['name'], 'Téléphone' => $message['phone'], 'E-mail' => $message['email']],
            'quote' => $message['message'],
            'quoteBy' => 'Message',
            'button' => ['Voir les messages', absolute_url('/admin/messages/?statut=nouveau')],
        ], $message['email']);
    }

    /* ---------- Outils ---------- */

    private static function toClient(string $kind, string $email, string $name, string $subject, array $content, ?string $related = null): void
    {
        self::queue($kind, $email, $name, $subject, $content + ['audience' => 'client'], (string) config('mail.agency'), $related);
    }

    private static function toAgency(string $kind, string $subject, array $content, ?string $replyTo = null, ?string $related = null): void
    {
        self::queue($kind, (string) config('mail.agency'), 'Taysir Orientation Voyage', $subject, $content + ['audience' => 'agence'], $replyTo, $related);
    }

    private static function queue(string $kind, string $email, string $name, string $subject, array $content, ?string $replyTo, ?string $related): void
    {
        try {
            $data = $content + ['preheader' => '', 'paragraphs' => [], 'details' => [], 'quote' => null, 'quoteBy' => null, 'button' => null, 'after' => []];
            MailOutbox::queue($kind, $email, $name, $subject, self::render('emails/layout', $data), self::render('emails/text', $data), $replyTo, $related);
        } catch (\Throwable $exception) {
            // Une notification ne doit jamais empêcher l'enregistrement d'une demande ou d'un message.
            error_log("Notification {$kind} non créée : " . $exception->getMessage());
        }
    }

    private static function render(string $view, array $data): string
    {
        ob_start();
        try {
            View::render($view, $data);
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    /** « mardi 24 novembre 2026 » */
    private static function day(string $date): string
    {
        $time = strtotime($date);
        $days = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $days[(int) date('N', $time)] . ' ' . date('j', $time) . ' ' . $months[(int) date('n', $time)] . ' ' . date('Y', $time);
    }
}
