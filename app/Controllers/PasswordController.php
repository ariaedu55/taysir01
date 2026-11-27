<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\FormInput;
use App\Services\Notifier;

/** Mot de passe oublié : demande du lien, ouverture du lien, nouveau mot de passe. Voir docs/mot-de-passe-oublie.md. */
final class PasswordController extends Controller
{
    /** Demandes de lien par session et par heure. */
    private const SESSION_LIMIT = 3;
    private const SESSION_KEY = '_reset_token';

    public function showForgot(): void
    {
        $this->render('auth/forgot', [
            'title' => 'Mot de passe oublié',
            'description' => 'Recevez un lien pour choisir un nouveau mot de passe pour votre espace Taysir.',
            'robots' => 'noindex',
            'styles' => ['css/auth.css'],
        ]);
    }

    public function sendLink(): void
    {
        $email = User::normalizeEmail(FormInput::text('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->back(['email' => 'Indiquez l’adresse e-mail de votre compte.'], '/mot-de-passe-oublie/');
        }
        $sent = array_filter((array) Session::get('_reset_requests', []), static fn ($time) => $time > time() - 3600);
        if (count($sent) >= self::SESSION_LIMIT) {
            $this->back(['email' => 'Vous avez déjà demandé plusieurs liens. Patientez une heure ou écrivez-nous sur WhatsApp.'], '/mot-de-passe-oublie/');
        }
        $sent[] = time();
        Session::set('_reset_requests', array_values($sent));

        $user = User::findByEmail($email);
        if ($user !== null && !empty($user['active']) && PasswordReset::recentCount((int) $user['id']) < PasswordReset::HOURLY_LIMIT) {
            Notifier::passwordReset($user, PasswordReset::issue((int) $user['id']));
        }

        // Même réponse que le compte existe ou non : la page ne révèle pas les adresses inscrites.
        Session::flash('_success', 'Si un compte existe avec l’adresse ' . $email . ', un e-mail vient de lui être envoyé avec un lien valable une heure. Pensez à vérifier vos courriers indésirables.');
        redirect_to('/mot-de-passe-oublie/');
    }

    /** Lien reçu par e-mail : le jeton passe en session et disparaît de l'adresse (historique, en-tête Referer). */
    public function open(string $token): void
    {
        if (PasswordReset::findValid($token) === null) {
            Session::forget(self::SESSION_KEY);
            Session::flash('_notice', 'Ce lien n’est plus valable : il a expiré ou a déjà servi. Demandez-en un nouveau.');
            redirect_to('/mot-de-passe-oublie/');
        }
        Session::set(self::SESSION_KEY, $token);
        redirect_to('/reinitialiser-mot-de-passe/');
    }

    public function showReset(): void
    {
        [, $user] = $this->current();

        $this->render('auth/reset', [
            'email' => $user['email'],
            // Invitation d'un membre de l'équipe : première connexion.
            'firstTime' => $user['last_login_at'] === null && $user['role'] !== 'client',
            'title' => 'Nouveau mot de passe',
            'robots' => 'noindex',
            'styles' => ['css/auth.css'],
            'scripts' => ['js/auth.js'],
        ]);
    }

    public function reset(): void
    {
        [$reset, $user] = $this->current();
        $password = (string) ($_POST['password'] ?? '');
        $errors = User::passwordErrors($password, (string) ($_POST['password_confirmation'] ?? ''));
        if ($errors !== []) {
            $this->back($errors, '/reinitialiser-mot-de-passe/');
        }

        User::updatePassword((int) $user['id'], $password);
        PasswordReset::consume($reset);
        Session::forget(self::SESSION_KEY);
        Auth::clearFailures();
        Auth::login($user);
        // Premier mot de passe d'un membre de l'équipe invité : pas d'alerte « mot de passe modifié ».
        if ($user['last_login_at'] !== null || $user['role'] === 'client') {
            Notifier::passwordChanged($user);
        }

        Session::flash('_success', 'Votre mot de passe est modifié. Vous êtes connecté(e).');
        // Règle unique (docs/gestion-plan.md §2.4) : /gestion/ pour l'équipe, /mon-espace/ pour un client
        redirect_to(\App\Services\Access::accueil($user));
    }

    /** @return array{0: array, 1: array} Jeton de la session encore valable et compte concerné */
    private function current(): array
    {
        $token = Session::get(self::SESSION_KEY);
        $reset = is_string($token) ? PasswordReset::findValid($token) : null;
        $user = $reset !== null ? User::find((int) $reset['user_id']) : null;
        if ($reset === null || $user === null || empty($user['active'])) {
            Session::forget(self::SESSION_KEY);
            Session::flash('_notice', 'Ce lien n’est plus valable : il a expiré ou a déjà servi. Demandez-en un nouveau.');
            redirect_to('/mot-de-passe-oublie/');
        }

        return [$reset, $user];
    }
}
