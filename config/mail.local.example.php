<?php

/*
 * Paramètres SMTP propres au serveur : copier ce fichier en config/mail.local.php (jamais versionné),
 * puis remplir les valeurs. Sur le VPS : /home/jomas/taysir/config/mail.local.php (droits 640, utilisateur jomas).
 * Les valeurs absentes reprennent celles de config/mail.php.
 */

declare(strict_types=1);

$mail = require __DIR__ . '/mail.php';

// « smtp » pour envoyer réellement ; laisser « log » tant que les identifiants ne sont pas fournis
$mail['driver'] = 'smtp';

// Adresse d'expédition : la plupart des fournisseurs exigent qu'elle corresponde au compte SMTP
$mail['from'] = ['address' => 'contact@taysir-orientation.com', 'name' => 'Taysir Orientation Voyage'];
// Boîte de l'agence qui reçoit les notifications internes (nouvelle demande, message de contact…)
$mail['agency'] = 'contact@taysir-orientation.com';

$mail['smtp'] = array_replace($mail['smtp'], [
    'host' => 'smtp.example.com',   // serveur SMTP du fournisseur
    'port' => 587,                  // 587 avec « tls », ou 465 avec « ssl »
    'encryption' => 'tls',          // « tls » ou « ssl »
    'username' => '',               // identifiant du compte SMTP (souvent l'adresse e-mail)
    'password' => '',               // mot de passe du compte SMTP ou mot de passe d'application
]);

// false : les e-mails partent par la tâche planifiée send-mails (activée après le premier envoi réussi)
$mail['send_immediately'] = false;

return $mail;
