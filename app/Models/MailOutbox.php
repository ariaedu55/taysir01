<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Mail\Mailer;

/**
 * File d'attente des e-mails (table mail_outbox). Les e-mails ajoutés pendant une requête sont envoyés
 * après la réponse au navigateur ; ceux en échec sont relancés par bin/send-mails.php.
 */
final class MailOutbox extends Model
{
    protected static string $table = 'mail_outbox';

    /** Délai avant chaque nouvelle tentative, en minutes (1re relance, 2e…). */
    private const RETRY_DELAYS = [5, 15, 60, 240];

    /** E-mails dont le contenu est effacé après l'envoi (lien de réinitialisation : le jeton ne doit pas rester en base). */
    private const SENSITIVE = ['password-reset', 'staff-invitation', 'appointment-created', 'appointment-confirmed', 'appointment-reminder', 'appointment-moved'];

    public const STATUSES = ['en-attente' => ['En attente', 'amber'], 'envoye' => ['Envoyé', 'green'], 'echec' => ['Échec', 'grey']];

    /** @var list<int> E-mails à envoyer à la fin de la requête en cours */
    private static array $pending = [];

    public static function queue(string $kind, string $recipient, ?string $recipientName, string $subject, string $html, string $text, ?string $replyTo = null, ?string $related = null, ?array $reminder = null): void
    {
        static::query(
            'INSERT INTO mail_outbox (kind, related, recipient, recipient_name, reply_to, subject, html, text, appointment_start_utc, expires_at_utc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$kind, $related, $recipient, $recipientName, $replyTo, self::cut($subject, 200), $html, $text, $reminder['start'] ?? null, $reminder['expires'] ?? null]
        );
        if ($reminder !== null || !config('mail.send_immediately', true)) {
            return;
        }
        if (self::$pending === []) {
            register_shutdown_function([self::class, 'flushAfterResponse']);
        }
        self::$pending[] = (int) static::db()->lastInsertId();
    }

    /** Fin de requête : la réponse part d'abord au navigateur (PHP-FPM), puis les e-mails sont envoyés. */
    public static function flushAfterResponse(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        foreach (self::$pending as $id) {
            self::deliver($id);
        }
        self::$pending = [];
    }

    /** Envoie un e-mail de la file. Ne lève jamais d'exception : l'échec est enregistré pour une relance. */
    public static function deliver(int $id): bool
    {
        $lock = null;
        $transaction = false;
        try {
            $name = 'taysir-mail-' . hash('sha1', static::query('SELECT DATABASE()')->fetchColumn() . ':' . $id);
            if ((int) static::query('SELECT GET_LOCK(?, 0)', [$name])->fetchColumn() !== 1) {
                return false;
            }
            $lock = $name;
            $mail = static::find($id);
            if ($mail === null || $mail['status'] !== 'en-attente') {
                return false;
            }
            if ($mail['kind'] === 'request-deadline-reminder') {
                [$deadline, $user] = array_pad(explode(':', (string) $mail['related'], 2), 2, '0');
                $target = static::query('SELECT request_id FROM request_deadlines WHERE id = ?', [(int) $deadline])->fetchColumn();
                static::db()->beginTransaction();
                $transaction = true;
                $request = static::query('SELECT * FROM service_requests WHERE id = ? FOR UPDATE', [(int) $target])->fetch();
                $open = $request && RequestRequirement::isOpen($request) && static::query(
                    'SELECT 1 FROM request_deadlines WHERE id = ? AND request_id = ? AND completed_at IS NULL',
                    [(int) $deadline, (int) $target]
                )->fetchColumn();
                $recipient = User::find((int) $user);
                $allowed = $open && $recipient && $recipient['active'] && $recipient['email'] === $mail['recipient'] &&
                    ($recipient['role'] === 'client' ? ServiceRequest::appartientAuClient($request, (int) $user)
                        : \App\Services\Access::isStaff($recipient) && \App\Services\Access::estResponsable($recipient, Assignment::forRequest((int) $target)));
                if (!$allowed || !$mail['expires_at_utc'] || $mail['expires_at_utc'] <= gmdate('Y-m-d H:i:s')) {
                    static::query("UPDATE mail_outbox SET status = 'echec', last_error = 'Rappel périmé', html = '', text = '' WHERE id = ?", [$id]);
                    static::db()->commit();
                    $transaction = false;
                    return false;
                }
            }
            if ($mail['kind'] === 'appointment-reminder') {
                static::db()->beginTransaction();
                $transaction = true;
                $appointment = static::query('SELECT status, starts_at_utc FROM appointments WHERE reference = ? FOR UPDATE', [$mail['related']])->fetch();
                $mail = static::query('SELECT * FROM mail_outbox WHERE id = ? FOR UPDATE', [$id])->fetch();
                if (!$mail || !$appointment || $appointment['status'] !== 'confirme'
                    || $appointment['starts_at_utc'] !== $mail['appointment_start_utc'] || $mail['expires_at_utc'] <= gmdate('Y-m-d H:i:s')) {
                    static::query("UPDATE mail_outbox SET status = 'echec', last_error = 'Rappel périmé', html = '', text = '' WHERE id = ?", [$id]);
                    static::db()->commit();
                    $transaction = false;
                    return false;
                }
            }
            $attempts = (int) $mail['attempts'] + 1;
            try {
                Mailer::send($mail);
                static::query("UPDATE mail_outbox SET status = 'envoye', attempts = ?, sent_at = NOW(), last_error = NULL WHERE id = ?", [$attempts, $id]);
                if (in_array($mail['kind'], self::SENSITIVE, true)) {
                    static::query("UPDATE mail_outbox SET html = '', text = '' WHERE id = ?", [$id]);
                }

                if ($transaction) {
                    static::db()->commit();
                    $transaction = false;
                }
                return true;
            } catch (\Throwable $exception) {
                $final = $attempts >= (int) config('mail.max_attempts', 5);
                $delay = self::RETRY_DELAYS[min($attempts - 1, count(self::RETRY_DELAYS) - 1)];
                static::query(
                    'UPDATE mail_outbox SET status = ?, attempts = ?, last_error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?',
                    [$final ? 'echec' : 'en-attente', $attempts, self::cut($exception->getMessage(), 255), $delay, $id]
                );
                if ($transaction) {
                    static::db()->commit();
                    $transaction = false;
                }
                error_log("E-mail {$id} non envoyé (tentative {$attempts}) : " . $exception->getMessage());

                return false;
            }
        } catch (\Throwable $exception) {
            error_log("E-mail {$id} : " . $exception->getMessage());

            return false;
        } finally {
            if ($transaction && static::db()->inTransaction()) {
                static::db()->rollBack();
            }
            if ($lock !== null) {
                try {
                    static::query('SELECT RELEASE_LOCK(?)', [$lock])->closeCursor();
                } catch (\PDOException) {
                    error_log('Impossible de libérer le verrou d’envoi.');
                }
            }
        }
    }

    /** @return list<int> E-mails en attente dont l'heure de (re)tentative est venue */
    public static function due(int $limit = 50): array
    {
        return array_map('intval', static::query(
            "SELECT id FROM mail_outbox WHERE status = 'en-attente' AND next_attempt_at <= NOW() ORDER BY id LIMIT " . max(1, $limit)
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Remet un e-mail en échec dans la file, avec un nouveau crédit de tentatives. */
    public static function retry(int $id): void
    {
        static::query("UPDATE mail_outbox SET status = 'en-attente', attempts = 0, next_attempt_at = NOW()
            WHERE id = ? AND status <> 'envoye' AND (kind NOT IN ('appointment-reminder', 'request-deadline-reminder') OR expires_at_utc > UTC_TIMESTAMP())", [$id]);
    }

    public static function search(?string $status): array
    {
        $params = [];
        $sql = 'SELECT id, kind, related, recipient, subject, status, attempts, last_error, sent_at, created_at FROM mail_outbox';
        if ($status !== null && isset(self::STATUSES[$status])) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }

        return static::query($sql . ' ORDER BY id DESC LIMIT 200', $params)->fetchAll();
    }

    /** @return array<string, int> */
    public static function countsByStatus(): array
    {
        return static::query('SELECT status, COUNT(*) FROM mail_outbox GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Coupe sans casser un caractère UTF-8 (sans dépendre de mbstring). */
    private static function cut(string $text, int $length): string
    {
        return preg_match('/^.{0,' . $length . '}/us', $text, $match) ? $match[0] : substr($text, 0, $length);
    }
}
