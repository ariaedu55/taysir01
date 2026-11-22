<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Jetons de réinitialisation du mot de passe (table password_resets). Seule l'empreinte SHA-256
 * du jeton est enregistrée. Voir docs/mot-de-passe-oublie.md.
 */
final class PasswordReset extends Model
{
    protected static string $table = 'password_resets';

    public const LIFETIME_MINUTES = 60;
    /** Invitation d'un membre de l'équipe : le lien reste valable plus longtemps. */
    public const INVITATION_MINUTES = 72 * 60;
    /** Demandes de réinitialisation autorisées par compte et par heure. */
    public const HOURLY_LIMIT = 3;

    /** Crée un jeton (les précédents du compte sont annulés) et retourne sa valeur en clair, à envoyer par e-mail. */
    public static function issue(int $userId, int $minutes = self::LIFETIME_MINUTES): string
    {
        $token = bin2hex(random_bytes(32));
        static::query('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        static::query(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$userId, hash('sha256', $token), $minutes]
        );

        return $token;
    }

    public static function recentCount(int $userId): int
    {
        return (int) static::query('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$userId])->fetchColumn();
    }

    /** Jeton valide (non utilisé, non expiré) ou null. */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = static::query(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        )->fetch();

        return $row === false ? null : $row;
    }

    /** Après le changement de mot de passe : ce jeton est utilisé, tous les autres du compte disparaissent. */
    public static function consume(array $reset): void
    {
        static::query('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$reset['id']]);
        static::query('DELETE FROM password_resets WHERE user_id = ? AND id <> ?', [$reset['user_id'], $reset['id']]);
    }
}
