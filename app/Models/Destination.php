<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Fiches pays. Registre dans config/destinations.php, contenu dans database/content/destinations/<slug>.php.
 * Lecture de fichiers pour l'instant ; l'interface restera la même si le contenu passe en base de données.
 */
final class Destination
{
    private const DEFAULTS = ['aliases' => [], 'photo' => null, 'city' => null, 'flag' => null, 'airport' => null];

    /** @return list<array> Pays du registre, sans leur contenu. */
    public static function all(): array
    {
        return array_map(static fn (array $country): array => $country + self::DEFAULTS, config('destinations'));
    }

    /** Pays complet (registre + contenu), ou null s'il n'existe pas. */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $country) {
            if ($country['slug'] !== $slug) {
                continue;
            }
            $file = BASE_PATH . '/database/content/destinations/' . $slug . '.php';
            $content = is_file($file) ? require $file : [];

            return $country + $content + ['intro' => '', 'sections' => [], 'pending' => null, 'image' => null];
        }

        return null;
    }

    /** Autres pays à suggérer : ceux de la même région d'abord. */
    public static function others(array $current, int $limit = 4): array
    {
        $others = array_values(array_filter(self::all(), static fn (array $c): bool => $c['slug'] !== $current['slug']));
        usort($others, static fn (array $a, array $b): int => ($b['region'] === $current['region']) <=> ($a['region'] === $current['region']));

        return array_slice($others, 0, $limit);
    }

    /** Aéroport de départ : Dakar Blaise-Diagne (DSS). [code, ville, latitude, longitude] */
    public const ORIGIN = ['DSS', 'Dakar', 14.67, -17.07];

    /** Distance à vol d'oiseau depuis Dakar, en km (formule de haversine), ou null sans aéroport. */
    public static function distanceKm(array $country): ?int
    {
        if (empty($country['airport'])) {
            return null;
        }
        [$lat1, $lon1, $lat2, $lon2] = array_map('deg2rad', [self::ORIGIN[2], self::ORIGIN[3], $country['airport'][2], $country['airport'][3]]);
        $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return (int) round(6371 * 2 * asin(min(1, sqrt($a))));
    }

    /** Cap initial depuis Dakar, en degrés (0 = nord, sens horaire), ou null sans aéroport. */
    public static function bearing(array $country): ?float
    {
        if (empty($country['airport'])) {
            return null;
        }
        [$lat1, $lon1, $lat2, $lon2] = array_map('deg2rad', [self::ORIGIN[2], self::ORIGIN[3], $country['airport'][2], $country['airport'][3]]);
        $y = sin($lon2 - $lon1) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($lon2 - $lon1);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    /** « 4 210 km », arrondi à la dizaine. */
    public static function distanceLabel(array $country): ?string
    {
        $km = self::distanceKm($country);

        return $km === null ? null : number_format((int) round($km, -1), 0, ',', ' ') . ' km';
    }

    /** Pays regroupés par région, dans l'ordre du registre. */
    public static function byRegion(): array
    {
        $regions = [];
        foreach (self::all() as $country) {
            $regions[$country['region']][] = $country;
        }

        return $regions;
    }

    /** Nombre de rubriques d'un pays, sans charger toute la page. */
    public static function sectionCount(string $slug): int
    {
        return count(self::find($slug)['sections'] ?? []);
    }

    /**
     * Image du pays : photo locale, sinon image de la page WordPress.
     * $portrait : version recadrée en hauteur (<photo>-portrait.webp) quand elle existe, pour les formats hauts.
     */
    public static function imageUrl(array $country, bool $portrait = false): ?string
    {
        if ($country['photo'] !== null) {
            $photo = $country['photo'];
            if ($portrait && is_file(BASE_PATH . '/public/assets/images/destinations/' . $photo . '-portrait.webp')) {
                $photo .= '-portrait';
            }

            return asset('images/destinations/' . $photo . '.webp');
        }

        return isset($country['image']) ? media_url($country['image']) : null;
    }

    /** Image du guide, en conservant les visuels de l'accueil et le média importé. */
    public static function guideImageUrl(array $country, bool $portrait = false): ?string
    {
        $guidePhoto = $country['guidePhoto'] ?? null;
        if ($guidePhoto && is_file(BASE_PATH . '/public/assets/images/destinations/' . $guidePhoto . '.webp')) {
            $country['photo'] = $guidePhoto;
        }

        return self::imageUrl($country, $portrait);
    }

    /** Crédit de la photo locale, repris de config/home.php ; liste vide si l'image vient du site WordPress. */
    public static function photoCredits(array $countries): array
    {
        $cities = array_filter(array_column($countries, 'city'));

        return array_values(array_filter(config('home.photoCredits', []), static fn (array $credit): bool => in_array($credit[0], $cities, true)));
    }

    /** Texte brut de l'introduction, coupé proprement pour la méta-description. */
    public static function summary(array $country, int $length = 155): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($country['intro']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') {
            return "Études, visa, logement : préparez votre départ {$country['in']} avec Taysir Orientation Voyage.";
        }
        if (preg_match('/^.{0,' . ($length - 1) . '}(?=\s)/su', $text, $m) && strlen($m[0]) < strlen($text)) {
            return rtrim($m[0], ' ,;:.') . '…';
        }

        return $text;
    }
}
