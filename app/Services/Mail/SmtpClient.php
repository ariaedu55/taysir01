<?php

declare(strict_types=1);

namespace App\Services\Mail;

use RuntimeException;

/**
 * Client SMTP minimal (RFC 5321) : connexion SSL ou STARTTLS, authentification LOGIN, un destinataire.
 * Utilisé par Mailer en mode « smtp ». Voir docs/notifications-email.md.
 */
final class SmtpClient
{
    /** @var resource|null */
    private $socket = null;

    /** @param array{host: string, port: int, encryption: string, username: string, password: string, timeout: int} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function send(string $from, string $to, string $message): void
    {
        if ($this->config['host'] === '') {
            throw new RuntimeException('Serveur SMTP non configuré (config/mail.local.php).');
        }
        try {
            $this->connect();
            $this->command('MAIL FROM:<' . $from . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);
            // Un point en début de ligne est doublé ; la fin du message est « . » seul sur sa ligne.
            $data = preg_replace('/^\./m', '..', rtrim($message, "\r\n")) . "\r\n.";
            $this->command($data, [250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function connect(): void
    {
        $encryption = $this->config['encryption'];
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->config['host']]]);
        $socket = @stream_socket_client(
            $scheme . '://' . $this->config['host'] . ':' . (int) $this->config['port'],
            $errorCode,
            $errorMessage,
            (float) $this->config['timeout'],
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($socket === false) {
            throw new RuntimeException("Connexion SMTP impossible : {$errorMessage} ({$errorCode})");
        }
        stream_set_timeout($socket, (int) $this->config['timeout']);
        $this->socket = $socket;

        $this->expect([220]);
        $hostname = gethostname() ?: 'localhost';
        $this->command('EHLO ' . $hostname, [250]);
        if ($encryption === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Échec du chiffrement STARTTLS.');
            }
            $this->command('EHLO ' . $hostname, [250]);
        }
        if ($this->config['username'] !== '') {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->config['username']), [334]);
            $this->command(base64_encode($this->config['password']), [235], hidden: true);
        }
    }

    /** @param list<int> $expected */
    private function command(string $line, array $expected, bool $hidden = false): string
    {
        if (fwrite($this->socket, $line . "\r\n") === false) {
            throw new RuntimeException('Écriture SMTP impossible.');
        }

        return $this->expect($expected, $hidden ? '(authentification)' : strtok($line, "\r\n"));
    }

    /** Lit une réponse (éventuellement sur plusieurs lignes « 250-… ») et vérifie son code. */
    private function expect(array $expected, string $after = 'connexion'): string
    {
        $response = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            $detail = trim($response) !== '' ? trim($response) : 'pas de réponse (délai dépassé)';
            throw new RuntimeException('SMTP, après ' . substr($after, 0, 40) . ' : ' . substr($detail, 0, 160));
        }

        return $response;
    }
}
