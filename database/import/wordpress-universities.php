<?php

/*
 * Extrait les fiches universités de l'export WordPress vers database/content/universities/<slug>.php.
 *
 * Usage, depuis la racine du projet :
 *   php -d extension=mbstring database/import/wordpress-universities.php [chemin/vers/export.xml]
 *
 * Relançable. Les corrections éditoriales sont décrites ci-dessous (voir docs/universites.md) ;
 * une correction faite à la main dans un fichier de données serait perdue à la prochaine extraction.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

const BASE_PATH = __DIR__ . '/../..';
require __DIR__ . '/WordpressExport.php';
require __DIR__ . '/extract-functions.php';

$export = $argv[1] ?? BASE_PATH . '/storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml';
$outputDir = BASE_PATH . '/database/content/universities';
$registry = require BASE_PATH . '/config/universities.php';

/** Corrections par page : liens erronés à retirer (par type). */
$fixes = [
    // Le lien « site officiel » de l'UASZ pointe vers ucad.sn.
    'uas' => ['dropLinks' => ['site']],
];

/** Titres : casse française, accents rétablis, sigles en majuscules. */
$titleFixes = [
    '/\b(?i:ucad|ugb|uadb|uam|uasz|uidt|ussein|uvs|ufr|lmd|bac|dut|bts|cesti|esp|iut|isep|ird|cnrs)\b/' => fn (array $m): string => strtoupper($m[0]),
    '/\b(?i:un-chk)\b/' => 'UN-CHK',
    '/\b(dakar|thiès|thies|bambey|ziguinchor|kaolack|diamniadio|sénégal|senegal|parcoursup)\b/' => fn (array $m): string => ucfirst($m[1]),
    '/\bcampusen\b/' => 'Campusen',
    '/\bsaint-louis\b/' => 'Saint-Louis',
    '/^Etape\b/' => 'Étape',
    '/\b(\d+)\s?f cfa\b/' => '$1 F CFA',
    '/^Facultes$/' => 'Facultés',
    '/^Centres, ecoles, instituts$/' => 'Centres, écoles et instituts',
    '/^Ecoles, instituts$/' => 'Écoles et instituts',
    '/^Unites de formation (?:det|et) de recherche(?: \(UFR\))?$/' => 'Unités de formation et de recherche (UFR)',
    '/^UFR, instituts$/' => 'UFR et instituts',
    '/^Scolarite$/' => 'Scolarité',
    '/^Admission$/' => 'Admissions',
];

$wp = new WordpressExport($export);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

foreach ($registry as $university) {
    $slug = $university['slug'];
    $page = $wp->page($slug);
    if ($page === null) {
        fwrite(STDERR, "Page introuvable : {$slug}\n");
        continue;
    }

    $data = extractPage($page['elementor'], [], $titleFixes);

    // Rubrique « À propos » : renommée avec le sigle actuel, retirée si elle répète l'introduction.
    $introText = normalizedText($data['intro']);
    $data['sections'] = array_values(array_filter(array_map(static function (array $section) use ($university, $introText): ?array {
        if (preg_match('/^A propos/iu', $section['title'])) {
            if ($introText !== '' && str_starts_with(normalizedText($section['html']), substr($introText, 0, 300))
                && strlen(normalizedText($section['html'])) <= strlen($introText) + 40) {
                return null;
            }
            $section['title'] = 'À propos de l’' . $university['acronym'];
        }

        return $section;
    }, $data['sections'])));

    // Liens de contact : un seul par type, sans ceux signalés comme erronés.
    $links = [];
    foreach ($data['links'] as [$type, $url]) {
        if (!in_array($type, $fixes[$slug]['dropLinks'] ?? [], true)) {
            $links[$type] ??= trim($url);
        }
    }

    $content = [
        'source' => 'https://taysir-orientation.com/' . $slug . '/',
        'wordpressTitle' => $page['title'],
        'intro' => $data['intro'],
        'sections' => $data['sections'],
        'links' => $links,
    ];
    file_put_contents("{$outputDir}/{$slug}.php", WordpressExport::toPhpFile($content, "Fiche {$university['acronym']}, extraite de l'export WordPress. Regénérée par database/import/wordpress-universities.php."));
    printf("%-6s %-7s %d rubriques, liens : %s\n", $slug, $university['acronym'], count($content['sections']), implode(', ', array_keys($links)));
}

function normalizedText(string $html): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}
