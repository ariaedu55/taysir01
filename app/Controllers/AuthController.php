<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Services\Notifier;

/** Inscription, connexion et déconnexion des clients. Voir docs/authentification.md. */
final class AuthController extends Controller
{
    public function showRegister(): void
    {
        $this->rememberReturn();
        $this->render('auth/register', [
            'title' => 'Créer mon compte',
            'description' => 'Créez votre espace Taysir pour suivre vos demandes d’orientation, d’admission et de visa, et prendre rendez-vous avec un conseiller.',
            'canonical' => url('/inscription/'),
            'styles' => ['css/auth.css'],
            'scripts' => ['js/auth.js'],
            'profiles' => User::PROFILES,
        ]);
    }

    public function register(): void
    {
        $data = [
            'first_name' => $this->text('first_name', 80),
            'last_name' => $this->text('last_name', 80),
            'email' => User::normalizeEmail($this->text('email', 190)),
            'phone' => preg_replace('/[^\d+ ]/', '', $this->text('phone', 30)),
            'profile' => $this->text('profile', 20),
            'password' => (string) ($_POST['password'] ?? ''),
        ];

        $errors = [];
        if ($data['first_name'] === '') {
            $errors['first_name'] = 'Indiquez votre prénom.';
        }
        if ($data['last_name'] === '') {
            $errors['last_name'] = 'Indiquez votre nom.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        } elseif (User::emailExists($data['email'])) {
            $errors['email'] = 'Un compte existe déjà avec cette adresse. Connectez-vous ou réinitialisez votre mot de passe.';
        }
        if ($data['phone'] !== '' && strlen(preg_replace('/\D/', '', $data['phone'])) < 8) {
            $errors['phone'] = 'Ce numéro semble incomplet.';
        }
        if (!array_key_exists($data['profile'], User::PROFILES)) {
            $errors['profile'] = 'Choisissez votre profil.';
        }
        $errors += User::passwordErrors($data['password'], (string) ($_POST['password_confirmation'] ?? ''));

        if (empty($_POST['consent'])) {
            $errors['consent'] = 'Votre accord est nécessaire pour créer votre compte.';
        }

        if ($errors !== []) {
            $this->back($errors, '/inscription/');
        }

        $user = User::create($data);
        Auth::login($user);
        Notifier::welcome($user);
        Session::flash('_success', 'Bienvenue ' . $user['first_name'] . ', votre espace Taysir est prêt.');
        $this->redirectIntended();
    }

    public function showLogin(): void
    {
        $this->rememberReturn();
        $this->render('auth/login', [
            'title' => 'Connexion',
            'description' => 'Connectez-vous à votre espace Taysir Orientation Voyage.',
            'canonical' => url('/connexion/'),
            'styles' => ['css/auth.css'],
            'scripts' => ['js/auth.js'],
            'lockedFor' => Auth::lockedFor(),
        ]);
    }

    public function login(): void
    {
        $locked = Auth::lockedFor();
        if ($locked > 0) {
            $this->back(['email' => 'Trop de tentatives. Réessayez dans ' . (int) ceil($locked / 60) . ' minute(s).'], '/connexion/');
        }

        $email = $this->text('email', 190);
        $user = Auth::attempt($email, (string) ($_POST['password'] ?? ''));
        if ($user === null) {
            Auth::recordFailure();
            // Même message que l'adresse existe ou non, pour ne pas révéler les comptes enregistrés.
            $this->back(['email' => 'Adresse e-mail ou mot de passe incorrect.'], '/connexion/');
        }

        Auth::clearFailures();
        Auth::login($user);
        \App\Services\Audit::log('connexion', 'compte', (string) $user['id'], [], (int) $user['id']);

        $this->redirectIntended();
    }

    /** « ?retour=/demande-de-visa/ » : page où revenir après la connexion ou l'inscription. */
    private function rememberReturn(): void
    {
        $return = $_GET['retour'] ?? null;
        if (is_string($return) && self::isInternal($return)) {
            Session::set('_intended', $return);
        }
    }

    /**
     * Règle unique après connexion ou inscription (docs/gestion-plan.md §2.4) : la page demandée avant la connexion
     * si elle est interne ET permise à ce compte, sinon /gestion/ pour l'équipe et /mon-espace/ pour un client.
     */
    private function redirectIntended(): never
    {
        $user = Auth::user();
        $intended = \App\Services\Access::retourPermis($user, Session::get('_intended'));
        Session::forget('_intended');
        if ($intended !== null) {
            header('Location: ' . $intended, true, 303);
            exit;
        }
        redirect_to(\App\Services\Access::accueil($user));
    }

    private static function isInternal(string $path): bool
    {
        return str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, '\\') && !preg_match('/[\r\n]/', $path);
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('_success', 'Vous êtes déconnecté(e). À bientôt !');
        redirect_to('/connexion/');
    }

    private function text(string $field, int $max): string
    {
        $value = trim((string) ($_POST[$field] ?? ''));
        $value = preg_replace('/\s+/u', ' ', $value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}
