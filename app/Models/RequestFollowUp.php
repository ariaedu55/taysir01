<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Notifier;

final class RequestFollowUp extends Model
{
    public const RESULTS = ['attente' => 'En attente', 'admis' => 'Admission obtenue', 'liste-attente' => 'Liste d’attente', 'refuse' => 'Admission refusée'];

    public static function deadlines(int $id): array
    {
        return static::query('SELECT id, title, due_date, completed_at FROM request_deadlines WHERE request_id = ? ORDER BY completed_at IS NOT NULL, due_date, id', [$id])->fetchAll();
    }

    public static function results(int $id): array
    {
        return static::query('SELECT id, institution, status FROM request_admission_results WHERE request_id = ? ORDER BY institution', [$id])->fetchAll();
    }

    public static function addDeadline(int $id, string $title, string $date): bool
    {
        return static::query('INSERT INTO request_deadlines (request_id, title, due_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = id', [$id, $title, $date])->rowCount() === 1;
    }

    public static function completeDeadline(int $requestId, int $id): bool
    {
        return static::query('UPDATE request_deadlines SET completed_at = UTC_TIMESTAMP() WHERE request_id = ? AND id = ? AND completed_at IS NULL', [$requestId, $id])->rowCount() === 1;
    }

    public static function recordResult(int $id, string $institution, string $status): bool
    {
        return static::query('INSERT INTO request_admission_results (request_id, institution, status) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)', [$id, $institution, $status])->rowCount() > 0;
    }

    /** Échéances ouvertes du périmètre de l’équipe pour « Ma journée ». */
    public static function upcoming(array $actor, string $through): array
    {
        if (!\App\Services\Access::peut($actor, 'dossiers')) {
            return [];
        }
        [$scope, $params] = \App\Services\Access::perimetre($actor, 'r');
        if ($actor['role'] !== 'admin') {
            $scope .= ' AND EXISTS (SELECT 1 FROM request_assignments a WHERE a.request_id = r.id AND a.user_id = ?)';
            $params[] = (int) $actor['id'];
        }
        return static::query("SELECT d.title, d.due_date, r.reference FROM request_deadlines d JOIN service_requests r ON r.id = d.request_id
            WHERE {$scope} AND d.completed_at IS NULL AND d.due_date <= ? AND r.status NOT IN ('terminee','annulee')
            ORDER BY d.due_date, d.id LIMIT 12", [...$params, $through])->fetchAll();
    }

    /** Appelé par le cron local et la recette. Les envois sont traités après validation SQL. */
    public static function queueReminders(\DateTimeImmutable $now): array
    {
        $zone = new \DateTimeZone('Africa/Dakar');
        $tomorrow = $now->setTimezone($zone)->setTime(0, 0)->modify('+1 day');
        $date = $tomorrow->format('Y-m-d');
        $expires = $tomorrow->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $candidates = static::query('SELECT id, request_id FROM request_deadlines WHERE due_date = ? AND completed_at IS NULL', [$date])->fetchAll();
        $sent = 0;
        $errors = 0;
        foreach ($candidates as $candidate) {
            $id = (int) $candidate['id'];
            try {
                $sent += RequestRequirement::withLockedRequest((int) $candidate['request_id'], static function (array $request) use ($id, $date, $expires): int {
                    if (!RequestRequirement::isOpen($request)) {
                        return 0;
                    }
                    $row = static::query('SELECT id FROM request_deadlines WHERE id = ? AND due_date = ? AND completed_at IS NULL FOR UPDATE', [$id, $date])->fetch();
                    if (!$row) {
                        return 0;
                    }
                    $owners = static::query("SELECT u.id, u.email, u.first_name FROM users u WHERE u.active = 1 AND u.role = 'client'
                        AND (u.person_id = ? OR (? IS NULL AND u.id = ?))",
                        [$request['person_id'], $request['person_id'], $request['user_id']])->fetchAll();
                    $staff = static::query('SELECT DISTINCT u.id, u.email, u.first_name FROM request_assignments a
                        JOIN users u ON u.id = a.user_id AND u.active = 1 WHERE a.request_id = ?', [$request['id']])->fetchAll();
                    $queued = false;
                    foreach ([...$owners, ...$staff] as $recipient) {
                        $mailKey = $id . ':' . $recipient['id'];
                        if (filter_var($recipient['email'], FILTER_VALIDATE_EMAIL) && !static::query(
                            "SELECT 1 FROM mail_outbox WHERE kind = 'request-deadline-reminder' AND related = ? LIMIT 1", [$mailKey]
                        )->fetchColumn()) {
                            Notifier::requestDeadlineReminder($id, $request['reference'], $date, $expires, $recipient);
                            $queued = true;
                        }
                    }
                    if ($queued) {
                        static::query('UPDATE request_deadlines SET queued_at = COALESCE(queued_at, UTC_TIMESTAMP()) WHERE id = ?', [$id]);
                    }
                    return (int) $queued;
                });
            } catch (\Throwable) {
                $errors++;
            }
        }
        return [$sent, $errors];
    }
}
