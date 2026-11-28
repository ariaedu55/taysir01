<?php

declare(strict_types=1);

namespace App\Services\Assistant;

/**
 * Limites d'usage de l'assistant (coût de l'API et abus), docs/assistant-ia.md.
 * Compteurs dans storage/cache/assistant/ : par adresse IP (empreinte, jamais l'adresse en clair) et pour tout le site.
 */
final class RateLimiter
{
    private const DIR = BASE_PATH . '/storage/cache/assistant';

    /** null si la question est acceptée (et comptée), sinon le message à afficher. */
    public static function hit(string $ip, array $limits): ?string
    {
        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0750, true);
        }
        $now = time();

        $refused = null;
        self::update(self::DIR . '/ip-' . substr(hash('sha256', $ip . '|' . date('Y-m-d')), 0, 24) . '.json', static function (array $hits) use ($limits, $now, &$refused): ?array {
            $recent = array_filter($hits, static fn (int $t): bool => $t > $now - 600);
            if (count($recent) >= $limits['per_ip_10min']) {
                $refused = 'Vous avez posé beaucoup de questions en peu de temps. Patientez quelques minutes avant de continuer.';

                return null;
            }
            if (count($hits) >= $limits['per_ip_day']) {
                $refused = 'Vous avez atteint le nombre de questions possibles pour aujourd’hui. Un conseiller peut vous répondre : prenez rendez-vous.';

                return null;
            }

            return $hits;
        }, $now);
        if ($refused !== null) {
            return $refused;
        }

        // Plafond du site, compté seulement pour les questions acceptées par adresse
        $site = self::update(self::DIR . '/site-' . date('Y-m-d') . '.json', static function (array $hits) use ($limits): ?array {
            return count($hits) >= $limits['site_day'] ? null : $hits;
        }, $now);
        if ($site === false) {
            return 'L’assistant a reçu beaucoup de questions aujourd’hui. Réessayez demain ou écrivez-nous depuis la page Contact.';
        }

        // Nettoyage des compteurs des jours précédents
        if (random_int(1, 50) === 1) {
            foreach (glob(self::DIR . '/*.json') ?: [] as $file) {
                if (filemtime($file) < $now - 2 * 86400) {
                    @unlink($file);
                }
            }
        }

        return $refused;
    }

    /**
     * Lit la liste des horodatages sous verrou ; $decide renvoie la liste à compléter, ou null pour refuser.
     * @return array|false
     */
    private static function update(string $file, callable $decide, int $now): array|false
    {
        $handle = fopen($file, 'c+');
        if (!$handle) {
            return []; // en cas de souci de disque, on ne bloque pas l'assistant
        }
        flock($handle, LOCK_EX);
        $hits = json_decode(stream_get_contents($handle) ?: '[]', true) ?: [];
        $hits = $decide($hits);
        if ($hits !== null) {
            $hits[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(array_values($hits)));
        }
        flock($handle, LOCK_UN);
        fclose($handle);

        return $hits ?? false;
    }
}
