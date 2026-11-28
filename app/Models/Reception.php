<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Audit;
use DomainException;
use Throwable;

final class Reception extends Model
{
    public const STATES = ['attente' => 'En attente', 'recu' => 'Reçu', 'termine' => 'Terminé'];

    public static function visits(string $day): array
    {
        return static::query('SELECT * FROM reception_visits WHERE created_at >= ? AND created_at < ?
            ORDER BY state, created_at, id', [$day . ' 00:00:00', (new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d 00:00:00')])->fetchAll();
    }

    public static function register(string $first, string $last, string $purpose, int $actor, ?string $phone = null, ?string $email = null): void
    {
        static::query('INSERT INTO reception_visits (first_name,last_name,phone,email,purpose,created_by,created_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())',
            [$first, $last, $phone, $email, $purpose, $actor]);
    }

    /**
     * Fiches existantes qui ressemblent à la visite (revue du 28/11, écart 3) : même nom et prénom, ou même téléphone,
     * ou même e-mail ; fiches non rapprochées seulement ; 5 au plus. L'accueil choisit, rien n'est rattaché d'office.
     */
    public static function suggestions(array $visit): array
    {
        $telephone = preg_replace('/\D/', '', (string) ($visit['phone'] ?? ''));
        $email = mb_strtolower(trim((string) ($visit['email'] ?? '')));

        return static::query(
            "SELECT id, first_name, last_name, phone, email, created_at FROM people
             WHERE merged_into IS NULL AND (
                 (LOWER(TRIM(last_name)) = ? AND LOWER(TRIM(first_name)) = ?)
                 OR (? <> '' AND REPLACE(REPLACE(phone, ' ', ''), '+', '') LIKE CONCAT('%', ?))
                 OR (? <> '' AND LOWER(TRIM(email)) = ?))
             ORDER BY id DESC LIMIT 5",
            [mb_strtolower(trim($visit['last_name'])), mb_strtolower(trim($visit['first_name'])),
                strlen($telephone) >= 8 ? substr($telephone, -9) : '', strlen($telephone) >= 8 ? substr($telephone, -9) : '', $email, $email]
        )->fetchAll();
    }

    public static function handleVisit(int $id, string $action, int $personId, int $actor, ?int $agency): void
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $visit = static::query('SELECT * FROM reception_visits WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$visit) {
                throw new DomainException('Visite introuvable.');
            }
            if ($action === 'convertir' && !$visit['person_id']) {
                if ($personId) {
                    if (!static::query('SELECT id FROM people WHERE id = ? AND merged_into IS NULL FOR UPDATE', [$personId])->fetchColumn()) {
                        throw new DomainException('La fiche sélectionnée est introuvable ou a été rapprochée.');
                    }
                } else {
                    $person = Person::create(['first_name' => $visit['first_name'], 'last_name' => $visit['last_name'], 'phone' => $visit['phone'] ?? '',
                        'email' => $visit['email'] ?? '', 'agency_id' => $agency], 'visite', $actor, Person::emailConnu($visit['email'] ?? null));
                    $personId = (int) $person['id'];
                }
                static::query('UPDATE reception_visits SET person_id = ? WHERE id = ?', [$personId, $id]);
                Audit::log('visite_convertie', 'visite', (string) $id, ['personne' => $personId]);
            } elseif ($action === 'recu' && $visit['state'] === 'attente') {
                static::query("UPDATE reception_visits SET state = 'recu' WHERE id = ?", [$id]);
            } elseif ($action === 'termine' && $visit['state'] !== 'termine') {
                static::query("UPDATE reception_visits SET state = 'termine' WHERE id = ?", [$id]);
            }
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public static function matchingSql(): string
    {
        return "((a.email IS NOT NULL AND a.email <> '' AND a.email = b.email)
            OR (a.first_name = b.first_name AND a.last_name = b.last_name AND a.birth_date = b.birth_date)
            OR (a.last_name = b.last_name AND a.phone IS NOT NULL AND a.phone <> '' AND a.phone = b.phone))";
    }

    public static function candidates(): array
    {
        return static::query('SELECT a.id AS first_id, b.id AS second_id, a.first_name AS first_given, a.last_name AS first_family,
            b.first_name AS second_given, b.last_name AS second_family
            FROM people a JOIN people b ON b.id > a.id AND ' . self::matchingSql() . '
            WHERE a.merged_into IS NULL AND b.merged_into IS NULL ORDER BY a.to_reconcile DESC, a.id DESC, b.id DESC LIMIT 40')->fetchAll();
    }

    public static function compare(int $first, int $second): array
    {
        if ($first === $second || !static::query('SELECT 1 FROM people a JOIN people b ON ' . self::matchingSql()
            . ' WHERE a.id = ? AND b.id = ? AND a.merged_into IS NULL AND b.merged_into IS NULL', [$first, $second])->fetchColumn()) {
            return [];
        }
        return [Person::find($first), Person::find($second)];
    }

    public static function merge(int $source, int $target, int $actor): void
    {
        if (!$source || !$target || $source === $target) {
            throw new DomainException('Choisissez deux fiches distinctes.');
        }
        $db = static::db();
        $db->beginTransaction();
        try {
            $rows = static::query('SELECT * FROM people WHERE id IN (?, ?) ORDER BY id FOR UPDATE', [$source, $target])->fetchAll();
            if (count($rows) !== 2 || self::compare($source, $target) === []) {
                throw new DomainException('Le rapprochement n’est plus disponible.');
            }
            if (static::query('SELECT id FROM users WHERE person_id IN (?, ?) LIMIT 1 FOR UPDATE', [$source, $target])->fetchColumn()
                || static::query('SELECT r.id FROM service_requests r JOIN request_tutor_access a ON a.request_id = r.id
                    WHERE r.person_id IN (?, ?) AND a.revoked_at IS NULL LIMIT 1 FOR UPDATE', [$source, $target])->fetchColumn()) {
                throw new DomainException('Une fiche avec compte ou accès tuteur actif nécessite un traitement dédié.');
            }
            if ((int) static::query('SELECT COUNT(*) FROM prospects WHERE person_id IN (?, ?)', [$source, $target])->fetchColumn() > 1) {
                throw new DomainException('Les deux fiches ont un suivi commercial. Leurs historiques nécessitent un rapprochement dédié.');
            }
            foreach (['service_requests', 'appointments', 'notes', 'tasks', 'reception_visits', 'prospects'] as $table) {
                static::query("UPDATE {$table} SET person_id = ? WHERE person_id = ?", [$target, $source]);
            }
            static::query('UPDATE people SET merged_into = ? WHERE merged_into = ?', [$target, $source]);
            static::query('UPDATE people SET merged_into = ?, to_reconcile = 0 WHERE id = ?', [$target, $source]);
            Audit::log('personnes_fusionnees', 'personne', (string) $target, ['ancienne_fiche' => $source], $actor);
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
