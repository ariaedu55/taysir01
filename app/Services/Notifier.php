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
                    'Téléphone' => $request['phone'] ?? '—',
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

    public static function clientReplied(array $request, string $message, int $documentCount): void
    {
        $name = $request['first_name'] . ' ' . $request['last_name'];
        self::toAgency('reply-agency', 'Réponse du client sur ' . $request['reference'] . ' · ' . $name, [
            'preheader' => match ($documentCount) {
                0 => 'Nouveau message du client',
                1 => '1 document ajouté',
                default => $documentCount . ' documents ajoutés',
            },
            'heading' => 'Le client a répondu',
            'paragraphs' => [$name . ' a complété sa demande ' . $request['reference'] . '.'],
            'quote' => $message !== '' ? $message : null,
            'quoteBy' => 'Message du client',
            'details' => $documentCount > 0 ? ['Documents ajoutés' => (string) $documentCount] : [],
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
            'button' => $appointment['user_id'] ? ['Voir mes rendez-vous', absolute_url('/mon-espace/demandes/')]
                : ['Annuler mon rendez-vous', Appointment::guestCancellationLink($appointment)],
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
        if ($confirmed) {
            $current = Appointment::find((int) $appointment['id']) ?? $appointment;
            self::appointmentScheduleMail($current, $appointment['status'] === 'confirme' ? $appointment : null);
            return;
        }
        self::appointmentScheduleMail($appointment, cancelled: true);
    }

    public static function appointmentScheduleMail(array $appointment, ?array $previous = null, ?string $expires = null, bool $cancelled = false): void
    {
        if (!filter_var($appointment['email'], FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $kind = match (true) {
            $cancelled => 'appointment-cancelled',
            $expires !== null => 'appointment-reminder',
            $previous !== null => 'appointment-moved',
            default => 'appointment-confirmed',
        };
        $heading = match ($kind) {
            'appointment-reminder' => 'Rappel de votre rendez-vous',
            'appointment-moved' => 'Votre rendez-vous est déplacé',
            'appointment-cancelled' => 'Votre rendez-vous est annulé',
            default => 'Votre rendez-vous est confirmé',
        };
        $format = static function (array $entry): string {
            $agency = isset($entry['agency_id']) ? (int) $entry['agency_id'] : null;
            $scheduled = $entry['starts_at_utc'] ?? $entry['scheduled_at'];
            return $scheduled
                ? Appointment::local($scheduled, Appointment::zone($agency), 'd/m/Y à H:i') . ' · ' . Appointment::city($agency)
                : self::day($entry['preferred_date']) . ' · ' . (Appointment::SLOTS[$entry['preferred_slot']] ?? '');
        };
        $details = ['Référence' => $appointment['reference'], 'Date et heure' => $format($appointment),
            'Échange' => Appointment::MODES[$appointment['mode']] ?? '', 'Objet' => Appointment::MOTIVES[$appointment['motive']] ?? ''];
        if ($previous !== null) {
            $details['Ancien créneau'] = $format($previous);
        }
        $city = Appointment::city(isset($appointment['agency_id']) ? (int) $appointment['agency_id'] : null);
        if (str_starts_with($appointment['mode'], 'agence-')) {
            $details['Agence'] = $city;
            foreach (config('site.offices', []) as $office) {
                if ($office['city'] === $city && !empty($office['address'])) {
                    $details['Adresse'] = $office['address'];
                    break;
                }
            }
        }
        $paragraphs = ['Bonjour ' . $appointment['first_name'] . ','];
        if ($appointment['mode'] === 'visio') {
            $person = isset($appointment['person_id']) ? \App\Models\Person::find((int) $appointment['person_id']) : null;
            if ($appointment['starts_at_utc'] && in_array($person['timezone'] ?? '', \DateTimeZone::listIdentifiers(), true)) {
                $details['Votre heure locale'] = Appointment::local($appointment['starts_at_utc'], new \DateTimeZone($person['timezone']), 'd/m/Y à H:i T');
            }
            if (!$cancelled) $paragraphs[] = 'Votre conseiller vous communiquera le lien de visioconférence.';
        }
        $button = ['Prendre un autre rendez-vous', absolute_url('/rendez_vous_orientation/')];
        if (!$cancelled) {
            $link = Appointment::guestCancellationLink($appointment);
            $button = $link === null ? ['Voir mes rendez-vous', absolute_url('/mon-espace/demandes/')] : ['Annuler mon rendez-vous', $link];
        }
        $data = ['audience' => 'client', 'heading' => $heading, 'details' => $details, 'preheader' => $format($appointment),
            'paragraphs' => $paragraphs, 'quote' => null, 'quoteBy' => null, 'after' => [],
            'button' => $button];
        MailOutbox::queue($kind, $appointment['email'], $appointment['first_name'] . ' ' . $appointment['last_name'],
            $heading . ' · ' . $appointment['reference'], self::render('emails/layout', $data), self::render('emails/text', $data),
            (string) config('mail.agency'), $appointment['reference'],
            $expires === null ? null : ['start' => $appointment['starts_at_utc'], 'expires' => $expires]);
    }

    /**
     * Compte rendu ou note PARTAGÉ disponible (E3, N12) : seulement si la personne a un compte client ACTIF, vers
     * l'adresse de ce compte (jamais celle du rendez-vous ou de la fiche, qu'un tiers a pu saisir), sans le texte ni
     * l'auteur de la note. Rien pour une personne sans compte.
     */
    public static function noteShared(int $personId): void
    {
        $compte = \App\Core\Database::connection()->prepare("SELECT id, first_name, last_name, email FROM users WHERE person_id = ? AND role = 'client' AND active = 1 LIMIT 1");
        $compte->execute([$personId]);
        $user = $compte->fetch();
        if ($user === false) {
            return;
        }
        self::toClient('note-shared', $user['email'], $user['first_name'] . ' ' . $user['last_name'], 'Un compte rendu est disponible dans votre espace', [
            'preheader' => 'Connectez-vous à votre espace pour le lire.',
            'heading' => 'Un compte rendu vous attend',
            'paragraphs' => [
                'Bonjour ' . $user['first_name'] . ',',
                'Votre conseiller Taysir a partagé un compte rendu avec vous. Pour le lire, connectez-vous à votre espace.',
            ],
            'button' => ['Ouvrir mon espace', absolute_url('/mon-espace/')],
        ]);
    }

    public static function requestDeadlineReminder(int $id, string $reference, string $date, string $expires, array $recipient): void
    {
        if (!filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $heading = 'Une échéance approche';
        $data = ['audience' => 'client', 'heading' => $heading, 'details' => ['Dossier' => $reference, 'Échéance' => self::day($date)],
            'preheader' => 'Une échéance de votre dossier est prévue demain.',
            'paragraphs' => ['Consultez le dossier dans votre espace Taysir pour voir l’action attendue.'],
            'quote' => null, 'quoteBy' => null, 'after' => [], 'button' => ['Se connecter', absolute_url('/connexion/')]];
        MailOutbox::queue('request-deadline-reminder', $recipient['email'], $recipient['first_name'], $heading . ' · ' . $reference,
            self::render('emails/layout', $data), self::render('emails/text', $data), (string) config('mail.agency'),
            $id . ':' . $recipient['id'], ['expires' => $expires]);
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

    private static function toClient(string $kind, ?string $email, string $name, string $subject, array $content, ?string $related = null): void
    {
        // N9 : un dossier ouvert par l'équipe peut n'avoir aucune adresse : rien n'est mis en file, aucune erreur
        if ($email === null || trim($email) === '') {
            return;
        }
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
