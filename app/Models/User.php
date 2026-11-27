<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Comptes de la plateforme (table users). */
final class User extends Model
{
    protected static string $table = 'users';

    /** Profils proposés à l'inscription : valeur => libellé. */
    public const PROFILES = [
        'eleve' => 'Élève (collège ou lycée)',
        'etudiant' => 'Étudiant(e)',
        'parent' => 'Parent d’élève ou d’étudiant',
        'particulier' => 'Particulier (voyage, travail…)',
    ];

    /** Client, puis les 8 postes de l'équipe (App\Services\Access::POSTES). */
    public const ROLES = ['client', 'admin', 'secretaire', 'prospection', 'commerciale', 'conseiller', 'orientation', 'traitement', 'comptable'];

    public const PASSWORD_MIN = 8;

    public static function findByEmail(string $email): ?array
    {
        $row = static::query('SELECT * FROM users WHERE email = ? LIMIT 1', [self::normalizeEmail($email)])->fetch();

        return $row === false ? null : $row;
    }

    public static function emailExists(string $email): bool
    {
        return (bool) static::query('SELECT 1 FROM users WHERE email = ? LIMIT 1', [self::normalizeEmail($email)])->fetchColumn();
    }

    /**
     * Crée un compte client. $data : first_name, last_name, email, phone, profile, password.
     * Retourne le compte créé.
     */
    public static function create(array $data): array
    {
        static::query(
            'INSERT INTO users (first_name, last_name, email, phone, profile, role, password_hash, consent_at)
             VALUES (?, ?, ?, ?, ?, \'client\', ?, NOW())',
            [
                $data['first_name'],
                $data['last_name'],
                self::normalizeEmail($data['email']),
                $data['phone'] !== '' ? $data['phone'] : null,
                $data['profile'],
                password_hash($data['password'], PASSWORD_DEFAULT),
            ]
        );

        return static::find((int) static::db()->lastInsertId());
    }

    /* ---------- Équipe (docs/comptes-equipe.md) ---------- */

    /** Membres de l'équipe (les 8 postes), actifs d'abord. */
    public static function staff(bool $activeOnly = false): array
    {
        return static::query(
            "SELECT id, first_name, last_name, email, phone, role, agency_id, active, last_login_at, created_at,
                    (SELECT COUNT(*) FROM request_assignments a JOIN service_requests r ON r.id = a.request_id WHERE a.user_id = users.id AND r.status NOT IN ('terminee', 'annulee')) AS open_requests
             FROM users WHERE role <> 'client'" . ($activeOnly ? ' AND active = 1' : '') . "
             ORDER BY active DESC, FIELD(role, 'admin', 'secretaire', 'prospection', 'commerciale', 'conseiller', 'orientation', 'traitement', 'comptable'), first_name"
        )->fetchAll();
    }

    /** Compte de l'équipe sans mot de passe utilisable : la personne le choisit via l'invitation. */
    public static function createStaff(array $data, string $role, ?int $invitedBy): array
    {
        static::query(
            'INSERT INTO users (first_name, last_name, email, phone, profile, role, agency_id, invited_by, password_hash, consent_at)
             VALUES (?, ?, ?, ?, \'particulier\', ?, (SELECT id FROM agencies WHERE is_head = 1 LIMIT 1), ?, ?, NOW())',
            [
                $data['first_name'], $data['last_name'], self::normalizeEmail($data['email']),
                $data['phone'] !== '' ? $data['phone'] : null, $role, $invitedBy,
                password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            ]
        );

        return static::find((int) static::db()->lastInsertId());
    }

    public static function setRole(int $id, string $role): void
    {
        static::query('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        static::query('UPDATE users SET active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function activeAdminCount(): int
    {
        return (int) static::query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn();
    }

    public static function touchLogin(int $id): void
    {
        static::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        static::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /**
     * Règles du mot de passe (inscription et réinitialisation) : 8 à 72 caractères (limite de bcrypt), confirmation identique.
     *
     * @return array<string, string> Erreurs par champ (password ou password_confirmation), vide si tout va bien
     */
    public static function passwordErrors(string $password, string $confirmation): array
    {
        if (strlen($password) < self::PASSWORD_MIN) {
            return ['password' => 'Le mot de passe doit contenir au moins ' . self::PASSWORD_MIN . ' caractères.'];
        }
        if (strlen($password) > 72) {
            return ['password' => 'Le mot de passe ne doit pas dépasser 72 caractères.'];
        }
        if (!hash_equals($password, $confirmation)) {
            return ['password_confirmation' => 'Les deux mots de passe ne correspondent pas.'];
        }

        return [];
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
