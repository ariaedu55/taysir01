<?php

/*
 * Extrait les fiches pays de l'export WordPress vers database/content/destinations/<slug>.php.
 *
 * Usage, depuis la racine du projet :
 *   php database/import/wordpress-destinations.php [chemin/vers/export.xml]
 *
 * Le script est relançable : il réécrit les fichiers de données. Les corrections éditoriales
 * (rubriques retirées ou renommées, pages non reprises) sont décrites dans $fixes ci-dessous,
 * pour qu'une nouvelle extraction les applique à nouveau. Une correction faite à la main dans un
 * fichier de données est perdue à la prochaine extraction : la reporter ici.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

const BASE_PATH = __DIR__ . '/../..';
require __DIR__ . '/WordpressExport.php';
require __DIR__ . '/extract-functions.php';

$export = $argv[1] ?? BASE_PATH . '/storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml';
$outputDir = BASE_PATH . '/database/content/destinations';
$registry = require BASE_PATH . '/config/destinations.php';

/** Corrections éditoriales par page. */
$fixes = [
    'aller-en-turquie' => ['skip' => 'La page WordPress reprend le texte de la page Canada.'],
    'aller-en-angleterre' => ['skip' => 'La page WordPress reprend le texte de la page Canada.'],
    'japon-2' => ['drop' => ['/^Visiter la Chine/i', '/^Vérification des exigences de visa/i', '/^Demande de visa$/i']],
    'aller-en-chine' => ['rename' => ['/admission au Canada/i' => 'La demande d’admission en Chine']],
];

/**
 * Corrections de titres communes à toutes les pages, appliquées après la mise en casse française
 * (minuscules sauf première lettre et noms propres).
 */
$titleFixes = [
    '/\b(canada|france|belgique|russie|chine|maroc|tunisie|japon|portugal|europe|afrique|québec|sénégal)\b/' => fn (array $m): string => ucfirst($m[1]),
    '/\barabie saoudite\b/' => 'Arabie saoudite',
    '/\b(caq|ircc)\b/' => fn (array $m): string => strtoupper($m[1]),
    '/\barrima\b/' => 'Arrima',
    '/\(visa x\)/' => '(visa X)',
    '/^Pèlerinage à la mecque.*$/' => 'Pèlerinage à La Mecque',
    '/^Preparartion au depart$/i' => 'Préparation au départ',
    '/^Travailler en Begique.*$/i' => 'Travailler en Belgique',
    '/\bau Belgique\b/i' => 'en Belgique',
    '/^Choisir la Francophonie Français$/i' => 'Choisir la francophonie française',
    '/^La demande d’admission France$/i' => 'La demande d’admission en France',
    '/^la demande de visa étudiant\s*:?$/i' => 'La demande de visa étudiant',
    '/^La demande de Visa Etudiant\s*\(visa X\)$/i' => 'La demande de visa étudiant (visa X)',
];

$wp = new WordpressExport($export);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

foreach ($registry as $country) {
    $slug = $country['slug'];
    $fix = $fixes[$slug] ?? [];
    $page = $wp->page($country['wordpress'] ?? $slug);

    if ($page === null) {
        fwrite(STDERR, "Page introuvable : {$slug}\n");
        continue;
    }

    $data = [
        'source' => 'https://taysir-orientation.com/' . ($country['wordpress'] ?? $slug) . '/',
        'wordpressTitle' => $page['title'],
        'image' => null,
        'intro' => '',
        'sections' => [],
        'pending' => $fix['skip'] ?? null,
    ];

    if (!isset($fix['skip'])) {
        $extracted = extractPage($page['elementor'], $fix, $titleFixes);
        unset($extracted['links']);
        $data = array_merge($data, $extracted);
    } else {
        $data['image'] = extractPage($page['elementor'], [], $titleFixes)['image'];
    }

    file_put_contents("{$outputDir}/{$slug}.php", WordpressExport::toPhpFile($data, "Fiche {$country['name']}, extraite de l'export WordPress. Regénérée par database/import/wordpress-destinations.php."));
    printf("%-26s %2d rubriques%s\n", $slug, count($data['sections']), $data['pending'] ? '  (contenu à fournir)' : '');
}
