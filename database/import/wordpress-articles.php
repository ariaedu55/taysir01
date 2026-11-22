<?php

/*
 * Importe les articles WordPress (fiches métiers, écoles, offres, bourses, salons) dans la table `articles`.
 *
 * Usage, depuis la racine du projet (base migrée au préalable avec database/migrate.php) :
 *   php -d extension=mbstring -d extension=pdo_mysql database/import/wordpress-articles.php [export.xml]
 *
 * Relançable : un article déjà importé (même adresse) est mis à jour. Voir docs/metiers-orientation.md.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

const BASE_PATH = __DIR__ . '/../..';
require __DIR__ . '/WordpressExport.php';
require __DIR__ . '/extract-functions.php';

$export = $argv[1] ?? BASE_PATH . '/storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml';
$sectors = array_column(require BASE_PATH . '/config/sectors.php', 'key', 'category');

/** Catégorie WordPress => type d'article. La première trouvée l'emporte. */
$types = [
    'metier' => 'metier',
    'formations-professionnelles' => 'ecole',
    'offre_emploie' => 'offre-emploi',
    'offre_stage' => 'offre-stage',
    'offre-bourses' => 'bourse',
    'offre-formation' => 'formation',
    'salon-collegien' => 'salon',
];
/** Types publiés en 2024 et datés : importés comme archives (consultables, mais plus mis en avant). */
$archivedTypes = ['offre-emploi', 'offre-stage', 'bourse', 'formation', 'salon'];
/** Articles écartés : test et partenaire sans contenu. */
$skip = ['jjj', 'edvantis'];

$titleFixes = [
    '/^Taches$/' => 'Tâches',
    '/^Competences$/' => 'Compétences',
    '/^Vie étudiant$/' => 'Vie étudiante',
    '/^Diplomes$/' => 'Diplômes',
    '/^Filieres$/' => 'Filières',
    '/\b(?i:bts|dut|bac|lmd|mba|rh|qhse|sig|tic|ia|ui|ux)\b/' => fn (array $m): string => strtoupper($m[0]),
    '/\b(sénégal|senegal|dakar|afrique)\b/' => fn (array $m): string => ucfirst($m[1]),
];

$config = is_file(BASE_PATH . '/config/database.local.php') ? require BASE_PATH . '/config/database.local.php' : require BASE_PATH . '/config/database.php';
$db = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']),
    $config['user'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$upsert = $db->prepare(
    'INSERT INTO articles (slug, title, type, sector, excerpt, intro, sections, gallery, image, status, published_at, wordpress_id)
     VALUES (:slug, :title, :type, :sector, :excerpt, :intro, :sections, :gallery, :image, :status, :published_at, :wordpress_id)
     ON DUPLICATE KEY UPDATE title = VALUES(title), type = VALUES(type), sector = VALUES(sector), excerpt = VALUES(excerpt),
        intro = VALUES(intro), sections = VALUES(sections), gallery = VALUES(gallery), image = VALUES(image),
        status = VALUES(status), published_at = VALUES(published_at), wordpress_id = VALUES(wordpress_id)'
);

$wp = new WordpressExport($export);
$counts = [];
foreach ($wp->posts() as $slug => $post) {
    if (in_array($slug, $skip, true)) {
        continue;
    }
    $type = null;
    foreach ($post['categories'] as $category) {
        $type ??= $types[$category] ?? null;
    }
    if ($type === null) {
        fwrite(STDERR, "Type inconnu, article ignoré : {$slug} (" . implode(', ', $post['categories']) . ")\n");
        continue;
    }
    $sector = null;
    foreach ($post['categories'] as $category) {
        $sector ??= $sectors[$category] ?? null;
    }

    $content = $post['elementor'] !== [] ? extractArticle($post['elementor'], $titleFixes) : extractClassic($post['content']);
    $image = $wp->attachment($post['thumbnail']) ?? $content['cover'];
    $excerpt = plainText($post['excerpt'] !== '' ? $post['excerpt'] : $content['intro'] . implode(' ', array_column($content['sections'], 'html')));

    $upsert->execute([
        'slug' => $slug,
        'title' => trim(html_entity_decode($post['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'type' => $type,
        'sector' => $type === 'metier' ? $sector : null,
        'excerpt' => shorten($excerpt, 220),
        'intro' => $content['intro'],
        'sections' => json_encode($content['sections'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'gallery' => json_encode($content['gallery'], JSON_UNESCAPED_SLASHES),
        'image' => $image,
        'status' => in_array($type, $archivedTypes, true) ? 'archive' : 'publie',
        'published_at' => $post['date'],
        'wordpress_id' => $post['id'],
    ]);
    $counts[$type . ($sector ? " / {$sector}" : '')] = ($counts[$type . ($sector ? " / {$sector}" : '')] ?? 0) + 1;
}

ksort($counts);
foreach ($counts as $label => $count) {
    printf("  %-28s %d\n", $label, $count);
}
printf("%d articles importés ou mis à jour.\n", array_sum($counts));

/**
 * Article Elementor : ignore l'en-tête et le pied du thème, puis lit les widgets dans l'ordre.
 * Titre → nouvelle rubrique ; texte, liste et accordéon → contenu de la rubrique ; galerie → images.
 */
function extractArticle(array $elementor, array $titleFixes): array
{
    $cover = null;
    $intro = '';
    $sections = [];
    $gallery = [];
    $current = null;

    foreach ($elementor as $section) {
        $tag = $section['settings']['html_tag'] ?? '';
        $widgets = WordpressExport::widgets([$section]);
        $isThemePart = in_array($tag, ['header', 'footer'], true)
            || array_filter($widgets, static fn (array $w): bool => str_starts_with($w['widgetType'] ?? '', 'zourney-')) !== [];
        if ($isThemePart) {
            continue;
        }
        foreach ($widgets as $widget) {
            $settings = $widget['settings'] ?? [];
            $html = '';
            switch ($widget['widgetType'] ?? '') {
                case 'image':
                    $cover ??= WordpressExport::uploadPath($settings['image']['url'] ?? '');
                    continue 2;
                case 'gallery':
                    foreach ($settings['gallery'] ?? [] as $image) {
                        if ($path = WordpressExport::uploadPath($image['url'] ?? '')) {
                            $gallery[] = $path;
                        }
                    }
                    continue 2;
                case 'heading':
                    $title = cleanTitle($settings['title'] ?? '', $titleFixes);
                    // Pied de page copié dans certains articles (newsletter, services, téléphone) : fin du contenu.
                    if (preg_match('/abonnez-vous|newsletter|nos services|top destinations|^taysi?r orientation|^\(?\+?221/iu', $title)) {
                        break 3;
                    }
                    if ($title !== '') {
                        if ($current !== null && trim(strip_tags($current['html'])) !== '') {
                            $sections[] = $current;
                        }
                        $current = ['title' => $title, 'html' => ''];
                    }
                    continue 2;
                case 'text-editor':
                    $html = WordpressExport::cleanHtml($settings['editor'] ?? '');
                    break;
                case 'icon-list':
                    $items = array_filter(array_map(static fn (array $i): string => trim(strip_tags($i['text'] ?? '')), $settings['icon_list'] ?? []));
                    $html = $items === [] ? '' : '<ul>' . implode('', array_map(static fn (string $i): string => '<li>' . htmlspecialchars($i, ENT_QUOTES, 'UTF-8') . '</li>', $items)) . '</ul>';
                    break;
                case 'toggle':
                case 'accordion':
                    foreach ($settings['tabs'] ?? [] as $tab) {
                        $html .= '<h3>' . htmlspecialchars(cleanTitle($tab['tab_title'] ?? '', $titleFixes), ENT_QUOTES, 'UTF-8') . '</h3>'
                            . WordpressExport::cleanHtml($tab['tab_content'] ?? '');
                    }
                    break;
                default:
                    continue 2;
            }
            if ($current === null) {
                $intro .= $html;
            } else {
                $current['html'] .= $html;
            }
        }
    }
    if ($current !== null && trim(strip_tags($current['html'])) !== '') {
        $sections[] = $current;
    }

    return ['cover' => $cover, 'intro' => $intro, 'sections' => $sections, 'gallery' => array_values(array_unique($gallery))];
}

/** Article classique (éditeur de blocs) : tout le contenu en introduction. */
function extractClassic(string $content): array
{
    $content = preg_replace('/<!--.*?-->/s', '', $content);
    $cover = preg_match('#wp-content/uploads/([^"\s]+?\.(?:jpe?g|png|webp))#i', $content, $m) ? preg_replace('/-\d+x\d+(\.[a-z]+)$/i', '$1', rawurldecode($m[1])) : null;

    return ['cover' => $cover, 'intro' => WordpressExport::cleanHtml($content), 'sections' => [], 'gallery' => []];
}

function plainText(string $html): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function shorten(string $text, int $length): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    $cut = mb_substr($text, 0, $length);

    return rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' ')), ' ,;:.') . '…';
}
