<?php

/*
 * Envoie les e-mails en attente de la file (mail_outbox) dont l'heure de tentative est venue :
 * relances après un échec, ou e-mails non partis à la fin d'une requête. Voir docs/notifications-email.md.
 *
 * À lancer par une tâche planifiée, par exemple toutes les 5 minutes chez LWS :
 *   php /chemin/du/site/bin/send-mails.php
 * En local (WAMP) :
 *   php -n -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext -d extension=pdo_mysql -d extension=openssl bin/send-mails.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\MailOutbox;

if (in_array('--planifie', $argv, true) && !in_array(config('mail.driver', 'log'), ['smtp', 'mail'], true)) {
    exit(0);
}

// Une seule exécution à la fois, même si la tâche précédente n'est pas finie.
$lock = fopen(BASE_PATH . '/storage/cache/send-mails.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Envoi déjà en cours.\n");
}

$sent = 0;
$failed = 0;
foreach (MailOutbox::due(100) as $id) {
    MailOutbox::deliver($id) ? $sent++ : $failed++;
}
printf("%s : %d envoyé(s), %d en échec.\n", date('Y-m-d H:i:s'), $sent, $failed);

flock($lock, LOCK_UN);
fclose($lock);
