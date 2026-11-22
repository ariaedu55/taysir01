<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Personne connectée, stockée en session par son identifiant.
 * Voir docs/authentification.md.
 */
final class Auth
{
    private const SESSION_KEY = '_auth_user_id';

    /** Tentatives de connexion échouées autorisées avant blocage temporaire. */
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : null;
    }

    /** Compte connecté, sans le mot de passe, ou null. Lu une seule fois par requête. */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = self::id();
            self::$user = $id !== null ? User::find($id) : null;
            if (self::$user !== null && empty(self::$user['active'])) {
                // Compte désactivé depuis la connexion : la session est fermée dès la requête suivante.
                self::$user = null;
            }
            if ($id !== null && self::$user === null) {
                // Compte supprimé ou désactivé entre-temps : on ferme la session.
                unset($_SESSION[self::SESSION_KEY]);
            }
            if (self::$user !== null) {
                unset(self::$user['password_hash']);
            }
        }

        return self::$user;
    }

    public static function hasRole(string ...$roles): bool
    {
        return self::check() && in_array(self::$user['role'], $roles, true);
    }

    /** Ouvre la session d'un compte : nouvel identifiant de session pour éviter la fixation de session. */
    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION[self::SESSION_KEY] = (int) $user['id'];
        unset($user['password_hash']);
        self::$user = $user;
        self::$loaded = true;
        User::touchLogin((int) $user['id']);
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::$user = null;
        Session::regenerate();
        Session::forget('_csrf');
    }

    /** Vérifie un couple e-mail / mot de passe. Retourne le compte ou null. */
    public static function attempt(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);
        // password_verify est appelé même si le compte n'existe pas, pour un temps de réponse comparable.
        $hash = $user['password_hash'] ?? password_hash('compte-inexistant', PASSWORD_DEFAULT);
        // Compte désactivé : même réponse qu'un mauvais mot de passe.
        if (!password_verify($password, $hash) || $user === null || empty($user['active'])) {
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePassword((int) $user['id'], $password);
        }

        return $user;
    }

    /** Secondes restantes de blocage après trop d'échecs (0 si la connexion est permise). */
    public static function lockedFor(): int
    {
        $until = (int) ($_SESSION['_login_locked_until'] ?? 0);

        return max(0, $until - time());
    }

    public static function recordFailure(): void
    {
        $_SESSION['_login_failures'] = (int) ($_SESSION['_login_failures'] ?? 0) + 1;
        if ($_SESSION['_login_failures'] >= self::MAX_ATTEMPTS) {
            $_SESSION['_login_locked_until'] = time() + self::LOCK_SECONDS;
            $_SESSION['_login_failures'] = 0;
        }
    }

    public static function clearFailures(): void
    {
        unset($_SESSION['_login_failures'], $_SESSION['_login_locked_until']);
    }
}
