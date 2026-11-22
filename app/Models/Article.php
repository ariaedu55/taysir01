<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Articles (table articles) : fiches métiers, écoles, offres, bourses, salons. */
final class Article extends Model
{
    protected static string $table = 'articles';

    public const TYPES = [
        'metier' => 'Fiche métier',
        'ecole' => 'École',
        'offre-emploi' => 'Offre d’emploi',
        'offre-stage' => 'Offre de stage',
        'bourse' => 'Bourse',
        'formation' => 'Formation',
        'salon' => 'Salon',
        'actualite' => 'Actualité',
    ];

    /** Types regroupés sous « Offres et opportunités ». */
    public const OPPORTUNITIES = ['offre-emploi', 'offre-stage', 'bourse', 'formation'];

    /** Familles du blog (/blog/) : clé => [libellé, types, page dédiée]. */
    public const FAMILIES = [
        'metiers' => ['Fiches métiers', ['metier'], '/metiers/'],
        'ecoles' => ['Écoles', ['ecole'], '/formations-profesionnelles/'],
        'opportunites' => ['Offres et bourses', self::OPPORTUNITIES, '/offre_stage/'],
        'salons' => ['Salons', ['salon'], '/salon-collegien/'],
        'actualites' => ['Actualités', ['actualite'], null],
    ];

    public static function family(array $article): ?string
    {
        foreach (self::FAMILIES as $key => [, $types]) {
            if (in_array($article['type'], $types, true)) {
                return $key;
            }
        }

        return null;
    }

    /** Toutes les publications visibles, des plus récentes aux plus anciennes (archives en dernier). */
    public static function published(): array
    {
        return self::byTypes(array_keys(self::TYPES));
    }

    /** @return list<string> Adresses des articles visibles (pour déclarer leurs routes). */
    public static function slugs(): array
    {
        static $slugs = null;

        return $slugs ??= static::query("SELECT slug FROM articles WHERE status <> 'brouillon'")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = static::query("SELECT * FROM articles WHERE slug = ? AND status <> 'brouillon' LIMIT 1", [$slug])->fetch();

        return $row === false ? null : self::decode($row);
    }

    /**
     * Articles d'un ou plusieurs types, éventuellement d'un secteur.
     *
     * @param list<string> $types
     */
    public static function byTypes(array $types, ?string $sector = null, ?int $limit = null): array
    {
        $placeholders = implode(', ', array_fill(0, count($types), '?'));
        $params = $types;
        $sql = "SELECT * FROM articles WHERE type IN ({$placeholders}) AND status <> 'brouillon'";
        if ($sector !== null) {
            $sql .= ' AND sector = ?';
            $params[] = $sector;
        }
        $sql .= " ORDER BY status = 'archive', published_at DESC, title";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        return array_map([self::class, 'decode'], static::query($sql, $params)->fetchAll());
    }

    /** Fiches métiers triées par titre, groupables par secteur. */
    public static function careers(?string $sector = null): array
    {
        $params = [];
        $sql = "SELECT * FROM articles WHERE type = 'metier' AND status <> 'brouillon'";
        if ($sector !== null) {
            $sql .= ' AND sector = ?';
            $params[] = $sector;
        }

        return array_map([self::class, 'decode'], static::query($sql . ' ORDER BY title', $params)->fetchAll());
    }

    /** Articles proches : même secteur pour un métier, même type sinon. */
    public static function related(array $article, int $limit = 3): array
    {
        $sql = $article['type'] === 'metier' && $article['sector'] !== null
            ? "SELECT * FROM articles WHERE type = 'metier' AND sector = ? AND id <> ? AND status <> 'brouillon' ORDER BY RAND() LIMIT {$limit}"
            : "SELECT * FROM articles WHERE type = ? AND id <> ? AND status <> 'brouillon' ORDER BY published_at DESC LIMIT {$limit}";
        $first = $article['type'] === 'metier' && $article['sector'] !== null ? $article['sector'] : $article['type'];

        return array_map([self::class, 'decode'], static::query($sql, [$first, $article['id']])->fetchAll());
    }

    public static function imageUrl(array $article): ?string
    {
        return $article['image'] ? media_url($article['image']) : null;
    }

    public static function typeLabel(array $article): string
    {
        return self::TYPES[$article['type']] ?? 'Article';
    }

    /** Date lisible en français : « juin 2024 ». */
    public static function monthYear(array $article): string
    {
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $time = strtotime($article['published_at']);

        return $months[(int) date('n', $time) - 1] . ' ' . date('Y', $time);
    }

    private static function decode(array $row): array
    {
        $row['sections'] = json_decode($row['sections'] ?? '[]', true) ?: [];
        $row['gallery'] = json_decode($row['gallery'] ?? '[]', true) ?: [];

        return $row;
    }
}
