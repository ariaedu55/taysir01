<?php

declare(strict_types=1);

/*
 * Envoi des e-mails (docs/notifications-email.md).
 * En production, copier ce fichier en config/mail.local.php (chargé à sa place) et y mettre
 * le mode « smtp » ou « mail » et les identifiants. Ne jamais mettre de mot de passe dans ce fichier.
 */
return [
    // log : écrit les e-mails dans storage/mails/ (développement) ; mail : fonction mail() de PHP ; smtp : serveur SMTP.
    'driver' => 'log',
    'from' => ['address' => 'contact@taysir-orientation.com', 'name' => 'Taysir Orientation Voyage'],
    // Boîte qui reçoit les alertes (nouvelles demandes, rendez-vous, messages).
    'agency' => 'contact@taysir-orientation.com',
    'smtp' => [
        'host' => '',
        'port' => 465,
        // ssl (port 465), tls (STARTTLS, port 587) ou none (serveur de test local uniquement)
        'encryption' => 'ssl',
        'username' => '',
        'password' => '',
        'timeout' => 15,
    ],
    // true : envoi à la fin de chaque requête, après la réponse au navigateur (PHP-FPM, fastcgi_finish_request).
    // false : tout est envoyé par la tâche planifiée bin/send-mails.php (hébergement sans PHP-FPM, ou serveur SMTP lent).
    'send_immediately' => true,
    // Tentatives avant d'abandonner un e-mail (relances par bin/send-mails.php).
    'max_attempts' => 5,
];
