<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Destination;
use DateTimeImmutable;

/** Lecture et contrôle des saisies de formulaire (demandes, rendez-vous). */
final class FormInput
{
    /** Pays proposés en suggestion : les destinations Taysir d'abord, puis les plus demandés. */
    private const COUNTRIES = [
        'Sénégal', 'Allemagne', 'Algérie', 'Bénin', 'Burkina Faso', 'Cameroun', 'Chypre', 'Corée du Sud', 'Côte d’Ivoire',
        'Égypte', 'Émirats arabes unis', 'Espagne', 'États-Unis', 'Gabon', 'Gambie', 'Ghana', 'Guinée', 'Guinée-Bissau',
        'Hongrie', 'Inde', 'Irlande', 'Italie', 'Malaisie', 'Mali', 'Mauritanie', 'Niger', 'Nigeria', 'Pays-Bas', 'Pologne',
        'Qatar', 'Roumanie', 'Royaume-Uni', 'Rwanda', 'Suisse', 'Togo', 'Ukraine',
    ];

    public static function text(string $field, int $max = 190): string
    {
        $value = $_POST[$field] ?? '';
        $value = is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : '';

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    /** Texte sur plusieurs lignes (message). */
    public static function multiline(string $field, int $max = 2000): string
    {
        $value = $_POST[$field] ?? '';
        $value = is_string($value) ? trim(str_replace("\r\n", "\n", $value)) : '';
        $value = (string) preg_replace("/\n{3,}/", "\n\n", $value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    public static function phone(string $field): string
    {
        return (string) preg_replace('/[^\d+ ]/', '', self::text($field, 30));
    }

    public static function validPhone(string $phone): bool
    {
        return strlen((string) preg_replace('/\D/', '', $phone)) >= 8;
    }

    /** Date AAAA-MM-JJ valide, ou null. */
    public static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }

    /** @return list<string> */
    public static function countries(): array
    {
        $names = array_column(Destination::all(), 'name');
        $names[] = 'France';

        return array_values(array_unique([...$names, ...self::COUNTRIES]));
    }

    /** Champ caché anti-robots : rempli seulement par les robots. */
    public static function isBot(): bool
    {
        return trim((string) ($_POST['site_web'] ?? '')) !== '';
    }
}
