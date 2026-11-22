<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Messages de la page Contact (table contact_messages). */
final class ContactMessage extends Model
{
    protected static string $table = 'contact_messages';

    public const STATUSES = ['nouveau' => ['Nouveau', 'blue'], 'traite' => ['Traité', 'green']];

    public static function create(array $data, ?int $userId): void
    {
        static::query(
            'INSERT INTO contact_messages (user_id, name, email, phone, message) VALUES (?, ?, ?, ?, ?)',
            [$userId, $data['name'], $data['email'], $data['phone'], $data['message']]
        );
    }

    public static function search(?string $status): array
    {
        $params = [];
        $sql = 'SELECT m.*, u.first_name AS handler_first_name FROM contact_messages m LEFT JOIN users u ON u.id = m.handled_by';
        if ($status !== null && isset(self::STATUSES[$status])) {
            $sql .= ' WHERE m.status = ?';
            $params[] = $status;
        }

        return static::query($sql . " ORDER BY m.status = 'nouveau' DESC, m.created_at DESC LIMIT 200", $params)->fetchAll();
    }

    /** @return array<string, int> */
    public static function countsByStatus(): array
    {
        return static::query('SELECT status, COUNT(*) FROM contact_messages GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function setStatus(int $id, string $status, int $staffId): void
    {
        static::query(
            'UPDATE contact_messages SET status = ?, handled_by = ?, handled_at = ? WHERE id = ?',
            $status === 'traite' ? [$status, $staffId, date('Y-m-d H:i:s'), $id] : [$status, null, null, $id]
        );
    }
}
