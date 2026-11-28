<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Limites anti-abus de la prise de rendez-vous publique (docs/gestion-phase-2.md, C6 ; décision 2B, condition B1).
 *
 * - par adresse IP (IPv6 regroupée par /64) : 5 envois par heure et 15 sur 24 heures glissantes ;
 * - par adresse e-mail (casse, espaces et alias « +… » ignorés) : 3 envois par heure ;
 * - par adresse e-mail : 3 rendez-vous actifs au plus (demandés ou confirmés à venir).
 *
 * proteger() fait la vérification, la création et le comptage sous un VERROU EXCLUSIF : des envois simultanés ne
 * passent pas entre la vérification et le comptage (A2 du rapport de vérification des conditions 2B).
 * L'IP et l'e-mail ne sont jamais écrits en clair : seulement un HMAC-SHA256 avec une clé secrète propre au serveur
 * (storage/cache/rendez-vous/.cle, créée au premier usage, jamais versionnée). Le message de refus est le même quelle
 * que soit la limite : il ne révèle pas si une adresse est connue (C5).
 * Les seuils ne se règlent par l'environnement (RDV_LIMITE_*) QU'EN RECETTE (config/recette.php) : une ligne oubliée
 * dans le .env de production n'a aucun effet.
 */
final class BookingLimiter
{
    public const MESSAGE = 'Nous avons déjà reçu plusieurs demandes de rendez-vous récemment. Écrivez-nous sur WhatsApp si c’est urgent.';

    /**
     * Vérifie les limites, exécute $creer puis compte l'envoi, le tout sous verrou exclusif.
     *
     * @template T
     * @param callable(): T $creer
     * @return T
     * @throws LimiteAtteinte si une limite est atteinte (rien n'est créé ni compté)
     */
    public static function proteger(string $ip, string $email, callable $creer): mixed
    {
        $verrou = self::verrouiller();
        // Secours (R3) : sans fichier de verrou, verrou nommé du serveur SQL (MySQL et MariaDB) ; sinon refus prudent
        $verrouSql = false;
        if (!$verrou) {
            $verrouSql = (int) Database::connection()->query("SELECT GET_LOCK('taysir_rendez_vous', 10)")->fetchColumn() === 1;
            if (!$verrouSql) {
                throw new LimiteAtteinte(self::MESSAGE);
            }
        }
        try {
            if (self::check($ip, $email) !== null) {
                throw new LimiteAtteinte(self::MESSAGE);
            }
            $resultat = $creer();
            self::record($ip, $email);

            return $resultat;
        } finally {
            if ($verrou) {
                flock($verrou, LOCK_UN);
                fclose($verrou);
            }
            if ($verrouSql) {
                Database::connection()->query("SELECT RELEASE_LOCK('taysir_rendez_vous')")->fetchColumn();
            }
        }
    }

    /** null si l'envoi est permis, sinon le message à afficher. Ne compte rien. */
    public static function check(string $ip, string $email): ?string
    {
        $now = time();
        $ipHits = self::read(self::file('ip', self::ip($ip)));
        $emailHits = self::read(self::file('email', self::email($email)));
        $heure = static fn (array $hits): int => count(array_filter($hits, static fn (int $t): bool => $t > $now - 3600));
        $refuse = $heure($ipHits) >= self::limit('RDV_LIMITE_IP_HEURE', 5)
            || count($ipHits) >= self::limit('RDV_LIMITE_IP_JOUR', 15)
            || $heure($emailHits) >= self::limit('RDV_LIMITE_EMAIL_HEURE', 3)
            || self::actifs($email) >= self::limit('RDV_LIMITE_ACTIFS', 3);

        return $refuse ? self::MESSAGE : null;
    }

    /** Compte un envoi accepté. */
    public static function record(string $ip, string $email): void
    {
        self::append(self::file('ip', self::ip($ip)));
        self::append(self::file('email', self::email($email)));
        if (random_int(1, 50) === 1) {
            foreach (glob(self::dir() . '/*.json') ?: [] as $file) {
                if (filemtime($file) < time() - 2 * 86400) {
                    @unlink($file);
                }
            }
        }
    }

    /** Rendez-vous actifs de cette adresse (alias « +… » compris) : demandés, ou confirmés à venir. */
    public static function actifs(string $email): int
    {
        // Colonne générée et indexée (014, N8), interrogée telle quelle : l'index sert, même sous le verrou global
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM appointments WHERE email_normalized = ?
               AND (status = 'demande' OR (status = 'confirme' AND (starts_at_utc IS NULL OR starts_at_utc > UTC_TIMESTAMP())))"
        );
        $statement->execute([self::email($email)]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Adresse e-mail normalisée : minuscules, sans espaces autour, sans alias « +… » dans la partie locale, domaine après
     * le PREMIER « @ ». Exactement la règle de la colonne générée appointments.email_normalized (014, N8) : TRIM() de SQL
     * n'enlève que les espaces, d'où trim(…, ' ').
     */
    public static function email(string $email): string
    {
        $email = mb_strtolower(trim($email, ' '));
        if (str_contains($email, '@')) {
            [$local, $domaine] = explode('@', $email, 2);
            $email = explode('+', $local, 2)[0] . '@' . $domaine;
        }

        return $email;
    }

    /** IPv4 telle quelle ; IPv6 réduite à son préfixe /64 (une connexion en reçoit souvent tout un bloc). */
    public static function ip(string $ip): string
    {
        $binaire = @inet_pton($ip);
        // IPv4 encapsulée (::ffff:a.b.c.d) : c'est l'IPv4 qui compte, jamais un /64 commun à tous les visiteurs
        if ($binaire !== false && strlen($binaire) === 16 && str_starts_with($binaire, hex2bin('00000000000000000000ffff'))) {
            return (string) inet_ntop(substr($binaire, 12));
        }
        if ($binaire !== false && strlen($binaire) === 16) {
            return bin2hex(substr($binaire, 0, 8)) . '::/64';
        }

        return $ip;
    }

    private static function limit(string $name, int $default): int
    {
        if ((require BASE_PATH . '/config/recette.php') === null) {
            return $default; // jamais réglable hors recette
        }
        $value = getenv($name);

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private static function dir(): string
    {
        $recette = require BASE_PATH . '/config/recette.php';
        $dir = $recette !== null ? $recette['storage'] . '/rendez-vous' : BASE_PATH . '/storage/cache/rendez-vous';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /** Clé secrète du serveur pour le HMAC, créée au premier usage (fichier en 0600). */
    private static function cle(): string
    {
        $fichier = self::dir() . '/.cle';
        $cle = is_file($fichier) ? trim((string) file_get_contents($fichier)) : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $cle)) {
            $cle = bin2hex(random_bytes(32));
            $handle = @fopen($fichier, 'x');
            if ($handle) {
                fwrite($handle, $cle);
                fclose($handle);
                @chmod($fichier, 0600);
            } else {
                $cle = trim((string) @file_get_contents($fichier)) ?: $cle; // créée entre-temps par une autre requête
            }
        }

        return $cle;
    }

    /** @return resource|false */
    private static function verrouiller()
    {
        $handle = @fopen(self::dir() . '/.verrou', 'c');
        if ($handle) {
            flock($handle, LOCK_EX);
        }

        return $handle;
    }

    private static function file(string $kind, string $value): string
    {
        return self::dir() . '/' . $kind . '-' . substr(hash_hmac('sha256', $kind . '|' . $value, self::cle()), 0, 40) . '.json';
    }

    /** @return list<int> Horodatages des dernières 24 heures */
    private static function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $hits = json_decode((string) file_get_contents($file), true);
        $limite = time() - 86400;

        return is_array($hits) ? array_values(array_filter($hits, static fn ($t): bool => is_int($t) && $t > $limite)) : [];
    }

    private static function append(string $file): void
    {
        $handle = @fopen($file, 'c+');
        if (!$handle) {
            return; // un souci de disque ne bloque pas la prise de rendez-vous
        }
        flock($handle, LOCK_EX);
        $hits = json_decode(stream_get_contents($handle) ?: '[]', true) ?: [];
        $limite = time() - 86400;
        $hits = array_values(array_filter($hits, static fn ($t): bool => is_int($t) && $t > $limite));
        $hits[] = time();
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($hits));
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
