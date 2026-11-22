<?php

/*
 * Crée un compte administrateur (le premier, ou en secours) et envoie son invitation.
 * Les comptes suivants se créent depuis le back-office : /admin/equipe/. Voir docs/comptes-equipe.md.
 *
 * Usage :
 *   php bin/create-admin.php --email=prenom@taysir-orientation.com --prenom=Mamadou --nom=Cisse [--telephone="+221 77 000 00 00"]
 * En local (WAMP) :
 *   php -n -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext -d extension=pdo_mysql -d extension=openssl bin/create-admin.php …
 *
 * Le lien pour choisir le mot de passe (72 heures) est envoyé par e-mail et affiché ici.
 * Si l'adresse appartient déjà à un compte, rien n'est modifié.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Notifier;

$options = getopt('', ['email:', 'prenom:', 'nom:', 'telephone::']);
$data = [
    'email' => User::normalizeEmail((string) ($options['email'] ?? '')),
    'first_name' => trim((string) ($options['prenom'] ?? '')),
    'last_name' => trim((string) ($options['nom'] ?? '')),
    'phone' => trim((string) ($options['telephone'] ?? '')),
];
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || $data['first_name'] === '' || $data['last_name'] === '') {
    fwrite(STDERR, "Usage : php bin/create-admin.php --email=… --prenom=… --nom=… [--telephone=…]\n");
    exit(1);
}
if (User::emailExists($data['email'])) {
    fwrite(STDERR, "Un compte existe déjà avec {$data['email']} : rien n'a été modifié.\n");
    exit(1);
}

$user = User::createStaff($data, 'admin', null);
$token = PasswordReset::issue((int) $user['id'], PasswordReset::INVITATION_MINUTES);
Notifier::staffInvitation($user, $token, null);

echo "Administrateur créé : {$user['first_name']} {$user['last_name']} <{$user['email']}>\n";
echo "Invitation envoyée (mode d'envoi : " . config('mail.driver') . ").\n";
echo 'Lien pour choisir le mot de passe (72 heures) : ' . absolute_url('/reinitialiser-mot-de-passe/' . $token . '/') . "\n";
