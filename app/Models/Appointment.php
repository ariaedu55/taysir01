<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Rendez-vous (table appointments). */
final class Appointment extends Model
{
    protected static string $table = 'appointments';

    public const MOTIVES = [
        'orientation-filieres' => 'Orientation : choisir une filière',
        'orientation-metiers' => 'Orientation : choisir un métier',
        'admission' => 'Admission dans un établissement',
        'visa' => 'Visa',
        'logement' => 'Logement',
        'prise-en-charge' => 'Prise en charge',
        'voyage' => 'Billet d’avion ou hôtel',
        'renseignement' => 'Demande de renseignement',
    ];

    public const MODES = [
        'agence-dakar' => 'À l’agence de Dakar',
        'agence-ziguinchor' => 'À l’agence de Ziguinchor',
        'telephone' => 'Par téléphone',
        'whatsapp' => 'Par WhatsApp',
        'visio' => 'En visio',
    ];

    public const SLOTS = ['matin' => 'Matin (9 h – 13 h)', 'apres-midi' => 'Après-midi (14 h – 18 h)'];

    public const STATUSES = [
        'demande' => ['Demandé', 'blue'],
        'confirme' => ['Confirmé', 'green'],
        'termine' => ['Terminé', 'grey'],
        'annule' => ['Annulé', 'grey'],
    ];

    public static function create(array $data, ?int $userId): array
    {
        static::query(
            'INSERT INTO appointments (reference, user_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['TMP-' . bin2hex(random_bytes(6)), $userId, $data['motive'], $data['mode'], $data['preferred_date'], $data['preferred_slot'],
                $data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['message']]
        );
        $id = (int) static::db()->lastInsertId();
        $reference = sprintf('RDV-%s-%06d', date('Y'), $id);
        static::query('UPDATE appointments SET reference = ? WHERE id = ?', [$reference, $id]);

        return static::find($id);
    }

    public static function findByReference(string $reference): ?array
    {
        $row = static::query('SELECT * FROM appointments WHERE reference = ? LIMIT 1', [$reference])->fetch();

        return $row === false ? null : $row;
    }

    public static function forUser(int $userId): array
    {
        return static::query('SELECT * FROM appointments WHERE user_id = ? ORDER BY preferred_date DESC, id DESC', [$userId])->fetchAll();
    }

    public static function search(?string $status): array
    {
        $params = [];
        $sql = 'SELECT * FROM appointments';
        if ($status !== null && isset(self::STATUSES[$status])) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }

        return static::query($sql . " ORDER BY status = 'demande' DESC, preferred_date, id LIMIT 200", $params)->fetchAll();
    }

    public static function countsByStatus(): array
    {
        return static::query('SELECT status, COUNT(*) FROM appointments GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function update(int $id, string $status, ?string $scheduledAt): void
    {
        static::query('UPDATE appointments SET status = ?, scheduled_at = ? WHERE id = ?', [$status, $scheduledAt, $id]);
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status][0] ?? $status;
    }

    public static function statusTone(string $status): string
    {
        return self::STATUSES[$status][1] ?? 'grey';
    }
}
