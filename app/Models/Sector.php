<?php

declare(strict_types=1);

namespace App\Models;

/** Secteurs d'avenir (config/sectors.php). */
final class Sector
{
    /** Icônes (tracés SVG 24 × 24), mêmes que sur l'accueil. */
    public const ICONS = [
        'sante' => '<path d="M20.8 6.6a5 5 0 0 0-8.8-2.2 5 5 0 0 0-8.8 2.2c-.6 4.2 3.6 7.9 8.8 12.4 5.2-4.5 9.4-8.2 8.8-12.4Z"/><path d="M3.5 11.5h4l2-3 3 6 2-3h6"/>',
        'numerique' => '<path d="m8 7-5 5 5 5M16 7l5 5-5 5M14 4l-4 16"/>',
        'petrole-gaz' => '<path d="M12 3s6 6.4 6 11a6 6 0 0 1-12 0c0-4.6 6-11 6-11Z"/><path d="M9.5 15a2.5 2.5 0 0 0 2.5 2.5"/>',
        'immobilier' => '<path d="M4 21V5l8-3v19M12 9h8v12M2 21h20M8 7v.01M8 11v.01M8 15v.01M16 13v.01M16 17v.01"/>',
        'agriculture' => '<path d="M12 21v-9M12 12c0-4-3-7-8-7 0 4 3 7 8 7ZM12 14c0-3.5 2.5-6 7-6 0 3.5-2.5 6-7 6Z"/>',
        'energies' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    ];

    /** @return list<array> */
    public static function all(): array
    {
        return config('sectors');
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (self::all() as $sector) {
            if ($sector['slug'] === $slug) {
                return $sector;
            }
        }

        return null;
    }

    public static function byKey(?string $key): ?array
    {
        foreach (self::all() as $sector) {
            if ($sector['key'] === $key) {
                return $sector;
            }
        }

        return null;
    }

    public static function icon(string $key, int $size = 22): string
    {
        return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . (self::ICONS[$key] ?? '') . '</svg>';
    }
}
