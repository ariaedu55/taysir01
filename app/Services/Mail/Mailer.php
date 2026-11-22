<?php

declare(strict_types=1);

namespace App\Services\Mail;

use RuntimeException;

/**
 * Construit un e-mail MIME (texte + HTML) et l'envoie selon config('mail.driver') : log, mail ou smtp.
 * Voir docs/notifications-email.md.
 */
final class Mailer
{
    /**
     * @param array{recipient: string, recipient_name: ?string, reply_to: ?string, subject: string, html: string, text: string, kind?: string, id?: int} $mail
     * @throws RuntimeException si l'envoi échoue
     */
    public static function send(array $mail): void
    {
        $config = config('mail');
        if (!filter_var($mail['recipient'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresse du destinataire invalide.');
        }
        [$headers, $body] = self::build($mail, $config['from']);

        match ($config['driver']) {
            'log' => self::toLog($mail, $headers, $body),
            'mail' => self::toMail($mail, $headers, $body, $config['from']['address']),
            'smtp' => (new SmtpClient($config['smtp']))->send($config['from']['address'], $mail['recipient'], self::raw($headers, $body)),
            default => throw new RuntimeException('Mode d’envoi inconnu : ' . $config['driver']),
        };
    }

    /** @return array{0: array<string, string>, 1: string} En-têtes et corps MIME */
    public static function build(array $mail, array $from): array
    {
        $boundary = 'taysir-' . bin2hex(random_bytes(12));
        $domain = substr((string) strrchr($from['address'], '@'), 1) ?: 'localhost';
        $headers = [
            'Date' => date('r'),
            'From' => self::address($from['address'], $from['name']),
            'To' => self::address($mail['recipient'], $mail['recipient_name'] ?? null),
            'Subject' => self::encode($mail['subject']),
            'Message-ID' => '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        ];
        if (!empty($mail['reply_to']) && filter_var($mail['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $headers['Reply-To'] = self::clean($mail['reply_to']);
        }

        $part = static fn (string $type, string $content): string => "--{$boundary}\r\n"
            . "Content-Type: {$type}; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            // Fins de ligne CRLF avant l'encodage : un « \n » seul serait encodé « =0A » au lieu d'un vrai saut de ligne.
            . quoted_printable_encode(str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $content))) . "\r\n";
        $body = "Ce message est au format MIME.\r\n\r\n"
            . $part('text/plain', $mail['text'])
            . $part('text/html', $mail['html'])
            . "--{$boundary}--\r\n";

        return [$headers, str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body))];
    }

    public static function raw(array $headers, string $body): string
    {
        $lines = '';
        foreach ($headers as $name => $value) {
            $lines .= $name . ': ' . $value . "\r\n";
        }

        return $lines . "\r\n" . $body;
    }

    /** Mode développement : l'e-mail complet est écrit dans storage/mails/. */
    private static function toLog(array $mail, array $headers, string $body): void
    {
        $directory = BASE_PATH . '/storage/mails';
        if (!is_dir($directory) && !mkdir($directory, 0770, true)) {
            throw new RuntimeException('Dossier storage/mails indisponible.');
        }
        $name = date('Ymd-His') . '-' . ($mail['id'] ?? 0) . '-' . preg_replace('/[^a-z0-9-]/', '', $mail['kind'] ?? 'mail') . '.eml';
        if (file_put_contents($directory . '/' . $name, self::raw($headers, $body)) === false) {
            throw new RuntimeException('Écriture de l’e-mail impossible.');
        }
    }

    private static function toMail(array $mail, array $headers, string $body, string $from): void
    {
        $to = $headers['To'];
        $subject = $headers['Subject'];
        unset($headers['To'], $headers['Subject']);
        if (!mail($to, $subject, $body, $headers, '-f' . $from)) {
            throw new RuntimeException('La fonction mail() a refusé l’envoi.');
        }
    }

    private static function address(string $email, ?string $name): string
    {
        $email = self::clean($email);

        return $name ? self::encode($name) . ' <' . $email . '>' : $email;
    }

    /** Encode un en-tête non ASCII (RFC 2047) ; retire les retours à la ligne (injection d'en-têtes). */
    private static function encode(string $text): string
    {
        $text = self::clean($text);
        if (!preg_match('/[^\x20-\x7e]/', $text)) {
            return $text;
        }

        return implode("\r\n ", array_map(static fn (string $chunk) => '=?UTF-8?B?' . base64_encode($chunk) . '?=', self::chunks($text, 45)));
    }

    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], ' ', $value));
    }

    /** Découpe en morceaux de $bytes octets au plus sans couper un caractère UTF-8. */
    private static function chunks(string $text, int $bytes): array
    {
        $chunks = [];
        $current = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (strlen($current . $char) > $bytes) {
                $chunks[] = $current;
                $current = '';
            }
            $current .= $char;
        }

        return [...$chunks, $current];
    }
}
