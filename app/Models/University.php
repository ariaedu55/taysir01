<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Universités publiques du Sénégal. Registre dans config/universities.php,
 * contenu dans database/content/universities/<slug>.php.
 */
final class University
{
    /** Libellés des liens de contact, dans l'ordre d'affichage. */
    public const LINK_LABELS = [
        'site' => 'Site officiel',
        'email' => 'E-mail',
        'facebook' => 'Facebook',
        'x' => 'X (Twitter)',
        'youtube' => 'YouTube',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
    ];

    /** @return list<array> */
    public static function all(): array
    {
        return array_map(static fn (array $u): array => $u + ['former' => null], config('universities'));
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $university) {
            if ($university['slug'] === $slug) {
                $file = BASE_PATH . '/database/content/universities/' . $slug . '.php';
                $content = is_file($file) ? require $file : [];

                return $university + $content + ['intro' => '', 'sections' => [], 'links' => []];
            }
        }

        return null;
    }

    /** Les autres universités, dans l'ordre du registre. */
    public static function others(array $current, int $limit = 4): array
    {
        return array_slice(array_values(array_filter(self::all(), static fn (array $u): bool => $u['slug'] !== $current['slug'])), 0, $limit);
    }

    public static function imageUrl(array $university): string
    {
        return media_url($university['image']);
    }

    /** « à l’UCAD » : préposition et article devant le sigle. */
    public static function at(array $university): string
    {
        return 'à l’' . $university['acronym'];
    }

    /** Liens de contact triés et libellés : [libellé, adresse, type]. */
    public static function links(array $university): array
    {
        $links = [];
        foreach (self::LINK_LABELS as $type => $label) {
            if (isset($university['links'][$type])) {
                $links[] = [$label, $university['links'][$type], $type];
            }
        }

        return $links;
    }

    public static function summary(array $university, int $length = 155): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($university['intro']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') {
            return "{$university['name']} ({$university['acronym']}) : formations, admission et accompagnement Taysir Orientation Voyage.";
        }
        if (preg_match('/^.{0,' . ($length - 1) . '}(?=\s)/su', $text, $m) && strlen($m[0]) < strlen($text)) {
            return rtrim($m[0], ' ,;:.') . '…';
        }

        return $text;
    }
}
