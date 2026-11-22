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
    private const SENSITIVE = ['password-reset', 'staff-invitation'];

    public const STATUSES = ['en-attente' => ['En attente', 'amber'], 'envoye' => ['Envoyé', 'green'], 'echec' => ['Échec', 'grey']];

    /** @var list<int> E-mails à envoyer à la fin de la requête en cours */
    private static array $pending = [];

    public static function queue(string $kind, string $recipient, ?string $recipientName, string $subject, string $html, string $text, ?string $replyTo = null, ?string $related = null): void
    {
        static::query(
            'INSERT INTO mail_outbox (kind, related, recipient, recipient_name, reply_to, subject, html, text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$kind, $related, $recipient, $recipientName, $replyTo, self::cut($subject, 200), $html, $text]
        );
        if (!config('mail.send_immediately', true)) {
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
        try {
            $mail = static::find($id);
            if ($mail === null || $mail['status'] === 'envoye') {
                return false;
            }
            $attempts = (int) $mail['attempts'] + 1;
            try {
                Mailer::send($mail);
                static::query("UPDATE mail_outbox SET status = 'envoye', attempts = ?, sent_at = NOW(), last_error = NULL WHERE id = ?", [$attempts, $id]);
                if (in_array($mail['kind'], self::SENSITIVE, true)) {
                    static::query("UPDATE mail_outbox SET html = '', text = '' WHERE id = ?", [$id]);
                }

                return true;
            } catch (\Throwable $exception) {
                $final = $attempts >= (int) config('mail.max_attempts', 5);
                $delay = self::RETRY_DELAYS[min($attempts - 1, count(self::RETRY_DELAYS) - 1)];
                static::query(
                    'UPDATE mail_outbox SET status = ?, attempts = ?, last_error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?',
                    [$final ? 'echec' : 'en-attente', $attempts, self::cut($exception->getMessage(), 255), $delay, $id]
                );
                error_log("E-mail {$id} non envoyé (tentative {$attempts}) : " . $exception->getMessage());

                return false;
            }
        } catch (\Throwable $exception) {
            error_log("E-mail {$id} : " . $exception->getMessage());

            return false;
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
        static::query("UPDATE mail_outbox SET status = 'en-attente', attempts = 0, next_attempt_at = NOW() WHERE id = ? AND status <> 'envoye'", [$id]);
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
