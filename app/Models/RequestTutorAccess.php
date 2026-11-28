<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DomainException;
use OutOfBoundsException;

/** Autorisations explicites par dossier, distinctes de la propriété du dossier. */
final class RequestTutorAccess extends Model
{
    protected static string $table = 'request_tutor_access';

    private const PARENT_ACCOUNT = "u.active = 1 AND u.role = 'client' AND u.profile = 'parent'";

    public static function parentAccount(int $userId, bool $lock = false): ?array
    {
        $row = static::query(
            'SELECT u.id, u.first_name, u.last_name FROM users u WHERE u.id = ? AND ' . self::PARENT_ACCOUNT
            . ($lock ? ' FOR UPDATE' : ''),
            [$userId]
        )->fetch();

        return $row === false ? null : $row;
    }

    public static function forParent(int $userId): array
    {
        return static::query(
            'SELECT r.reference, r.service, r.first_name, r.last_name, r.status, a.can_deposit
             FROM request_tutor_access a
             JOIN users u ON u.id = a.active_parent_id
             JOIN service_requests r ON r.id = a.request_id
             WHERE a.active_parent_id = ? AND ' . self::PARENT_ACCOUNT . ' ORDER BY r.updated_at DESC, r.id DESC',
            [$userId]
        )->fetchAll();
    }

    public static function activeForParent(int $requestId, int $userId, bool $lock = false): ?array
    {
        $row = static::query(
            'SELECT a.id, a.can_deposit FROM request_tutor_access a JOIN users u ON u.id = a.active_parent_id
             WHERE a.request_id = ? AND a.active_parent_id = ? AND ' . self::PARENT_ACCOUNT
            . ($lock ? ' FOR UPDATE' : ''),
            [$requestId, $userId]
        )->fetch();

        return $row === false ? null : $row;
    }

    /** Inclut les autorisations suspendues par un changement de compte, afin de pouvoir les révoquer. */
    public static function forRequest(int $requestId): array
    {
        return static::query(
            'SELECT a.id, a.parent_user_id, a.can_deposit, a.granted_at, u.first_name, u.last_name,
                    (' . self::PARENT_ACCOUNT . ') AS account_eligible
             FROM request_tutor_access a JOIN users u ON u.id = a.active_parent_id
             WHERE a.request_id = ? AND a.active_parent_id IS NOT NULL ORDER BY a.id DESC',
            [$requestId]
        )->fetchAll();
    }

    public static function grant(int $requestId, int $parentId, int $adminId, bool $canDeposit, string $verificationReference): int
    {
        return RequestRequirement::withLockedRequest($requestId, static function (array $request) use ($requestId, $parentId, $adminId, $canDeposit, $verificationReference): int {
            self::requireActor($request, $adminId, true);
            if (self::parentAccount($parentId, true) === null) {
                throw new DomainException('Choisissez un compte client actif de profil parent.');
            }
            if (ServiceRequest::appartientAuClient($request, $parentId)) {
                throw new DomainException('Ce compte est déjà titulaire du dossier.');
            }
            if (self::activeForParent($requestId, $parentId, true) !== null) {
                throw new DomainException('Ce compte dispose déjà d’un accès au dossier.');
            }

            static::query(
                'INSERT INTO request_tutor_access (request_id, parent_user_id, can_deposit, granted_by, verification_reference) VALUES (?, ?, ?, ?, ?)',
                [$requestId, $parentId, (int) $canDeposit, $adminId, $verificationReference]
            );

            return (int) static::db()->lastInsertId();
        });
    }

    public static function revoke(int $requestId, int $accessId, int $actorId, bool $asAdmin = false): bool
    {
        return RequestRequirement::withLockedRequest($requestId, static function (array $request) use ($requestId, $accessId, $actorId, $asAdmin): bool {
            self::requireActor($request, $actorId, $asAdmin);

            return static::query(
                'UPDATE request_tutor_access SET revoked_at = CURRENT_TIMESTAMP, revoked_by = ?
                 WHERE request_id = ? AND id = ? AND revoked_at IS NULL',
                [$actorId, $requestId, $accessId]
            )->rowCount() === 1;
        });
    }

    private static function requireActor(array $request, int $actorId, bool $asAdmin): void
    {
        $role = static::query('SELECT role FROM users WHERE id = ? AND active = 1 FOR UPDATE', [$actorId])->fetchColumn();
        if ($role === false || ($asAdmin ? $role !== 'admin' : !ServiceRequest::appartientAuClient($request, $actorId))) {
            throw new OutOfBoundsException('Dossier introuvable.');
        }
    }
}
