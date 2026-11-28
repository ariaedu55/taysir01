<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Session;
use App\Services\Access;
use DomainException;
use PDOException;
use Throwable;

/** Tâches de l'équipe (2D, F4 et G8 à G10). */
final class Task
{
    public const TITLE_MAX = 190;
    private const FORM_SECRET = '_task_form_secret';

    /** @return list<array{title: string, id: int, due_date: ?string, done_at: ?string, link: ?string, can_toggle: bool}> */
    public static function forStaff(array $me): array
    {
        if (!self::isActiveStaff($me)) {
            return [];
        }

        $id = (int) $me['id'];
        $admin = $me['role'] === 'admin';
        [$people, $peopleParams] = Access::personnesSql($me, 'p');
        [$requests, $requestParams] = Access::perimetre($me, 'r');
        $visibility = $admin ? '' : 'WHERE (t.assigned_to = ? OR t.created_by = ?)';
        $statement = Database::connection()->prepare(
            "SELECT t.title, t.id, t.due_date, t.done_at, t.assigned_to, p.id AS person_id, r.reference
             FROM tasks t
             LEFT JOIN people p ON p.id = t.person_id AND t.request_id IS NULL AND {$people}
             LEFT JOIN service_requests r ON r.id = t.request_id AND t.person_id IS NULL AND {$requests}
             {$visibility}
             ORDER BY t.done_at IS NOT NULL, t.due_date IS NULL, t.due_date, t.id DESC"
        );
        $statement->execute([...$peopleParams, ...$requestParams, ...($admin ? [] : [$id, $id])]);

        $tasks = [];
        foreach ($statement->fetchAll() as $row) {
            $link = match (true) {
                $row['person_id'] !== null => '/gestion/personnes/' . $row['person_id'] . '/',
                $row['reference'] !== null => '/gestion/dossiers/' . rawurlencode($row['reference']) . '/',
                default => null,
            };
            $tasks[] = [
                'title' => $row['title'],
                'id' => (int) $row['id'],
                'due_date' => $row['due_date'],
                'done_at' => $row['done_at'],
                'link' => $link,
                'can_toggle' => $admin || (int) $row['assigned_to'] === $id,
            ];
        }

        return $tasks;
    }

    public static function formToken(array $me): string
    {
        $secret = Session::get(self::FORM_SECRET);
        if (!is_string($secret)) {
            $secret = bin2hex(random_bytes(32));
            Session::set(self::FORM_SECRET, $secret);
        }

        return self::signToken((int) $me['id'], bin2hex(random_bytes(16)), $secret);
    }

    private static function signToken(int $id, string $nonce, string $secret): string
    {
        // Le nonce distingue les formulaires. La rotation du CSRF à la déconnexion invalide les anciens jetons.
        $message = "tasks:{$id}:{$nonce}:" . Session::csrfToken();

        return $nonce . substr(hash_hmac('sha256', $message, $secret), 0, 32);
    }

    /**
     * @param array{title: string, due_date: ?string, person_id: ?int, request_id: ?int, assigned_to: int, form_token: mixed} $data
     * @return bool true si la tâche a été créée, false si le jeton a déjà servi
     * @throws DomainException refus journalisé
     */
    public static function create(array $me, array $data): bool
    {
        $id = (int) $me['id'];

        return self::mutate($id, 'nouvelle', static function () use ($id, $data): bool {
            $token = $data['form_token'];
            $secret = Session::get(self::FORM_SECRET);
            if (!is_string($secret) || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)
                || !hash_equals(self::signToken($id, substr($token, 0, 32), $secret), $token)) {
                throw new DomainException('Jeton de création invalide.');
            }

            $assignedTo = $data['assigned_to'];
            $members = self::lockMembers($id, $assignedTo);
            $actor = $members[$id] ?? null;
            $recipient = $members[$assignedTo] ?? null;
            if (!self::isActiveStaff($actor)) {
                throw new DomainException('Compte auteur non habilité.');
            }
            if ($assignedTo !== $id && !in_array($actor['role'], ['admin', 'secretaire'], true)) {
                throw new DomainException('Désignation d’un autre responsable non permise.');
            }
            if (!self::isActiveStaff($recipient)) {
                throw new DomainException('Responsable absent, inactif ou extérieur à l’équipe.');
            }

            $personId = $data['person_id'];
            $requestId = $data['request_id'];
            if ($personId !== null && $requestId !== null) {
                throw new DomainException('Deux liens soumis pour la même tâche.');
            }
            // Le middleware a pu mettre en cache les missions avant le début de la transaction.
            Access::oublier();
            foreach ($members as $member) {
                if (!self::canOpenLink($member, $personId, $requestId)) {
                    throw new DomainException('Lien hors du périmètre de l’auteur ou du responsable.');
                }
            }

            $db = Database::connection();
            try {
                $db->prepare(
                    'INSERT INTO tasks (title, due_date, person_id, request_id, assigned_to, created_by, form_token)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$data['title'], $data['due_date'], $personId, $requestId, $assignedTo, $id, $token]);
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062
                    && str_contains($exception->errorInfo[2] ?? '', 'tasks_form_token_unique')) {
                    return false;
                }
                throw $exception;
            }
            self::journal('tache_creee', $id, $db->lastInsertId());

            return true;
        });
    }

    /** @return bool true si l'état a changé */
    public static function setDone(array $me, int $taskId, bool $done): bool
    {
        $id = (int) $me['id'];

        return self::mutate($id, (string) $taskId, static function () use ($id, $taskId, $done): bool {
            $actor = self::lockMembers($id, $id)[$id] ?? null;
            if (!self::isActiveStaff($actor)) {
                throw new DomainException('Compte non habilité.');
            }
            $db = Database::connection();
            $task = $db->prepare('SELECT assigned_to FROM tasks WHERE id = ? FOR UPDATE');
            $task->execute([$taskId]);
            $assignedTo = $task->fetchColumn();
            if ($assignedTo === false || ($actor['role'] !== 'admin' && (int) $assignedTo !== $id)) {
                throw new DomainException('Changement d’état non permis.');
            }

            $update = $db->prepare($done
                ? 'UPDATE tasks SET done_at = UTC_TIMESTAMP(), done_by = ? WHERE id = ? AND done_at IS NULL'
                : 'UPDATE tasks SET done_at = NULL, done_by = NULL WHERE id = ? AND done_at IS NOT NULL');
            $update->execute($done ? [$id, $taskId] : [$taskId]);
            if ($update->rowCount() !== 1) {
                return false;
            }
            self::journal($done ? 'tache_cloturee' : 'tache_rouverte', $id, (string) $taskId);

            return true;
        });
    }

    private static function isActiveStaff(?array $user): bool
    {
        return Access::isStaff($user) && !empty($user['active']);
    }

    /** Fige le poste et l'état des comptes pendant l'écriture, dans l'ordre des identifiants. */
    private static function lockMembers(int $actorId, int $recipientId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, role, active FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE'
        );
        $statement->execute([$actorId, $recipientId]);

        return array_column($statement->fetchAll(), null, 'id');
    }

    private static function canOpenLink(array $member, ?int $personId, ?int $requestId): bool
    {
        if ($personId !== null) {
            return Person::findScoped($member, $personId) !== null;
        }

        return $requestId === null || Access::peutOuvrir($member, ['id' => $requestId]);
    }

    private static function mutate(int $actorId, string $ref, callable $change): bool
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $changed = $change();
            $db->commit();

            return $changed;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($exception instanceof DomainException) {
                self::journal('acces_refuse', $actorId, $ref, $exception->getMessage());
            }
            throw $exception;
        }
    }

    /** Écriture stricte dans le journal existant : un échec entraîne le rollback de la tâche. */
    private static function journal(string $action, int $actorId, string $ref, ?string $reason = null): void
    {
        Database::connection()->prepare(
            "INSERT INTO audit_log (user_id, action, subject_type, subject_ref, detail, ip)
             VALUES (?, ?, 'tache', ?, ?, ?)"
        )->execute([
            $actorId,
            $action,
            $ref,
            $reason === null ? null : json_encode(['motif' => $reason], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        ]);
    }
}
