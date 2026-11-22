<?php

/*
 * Extrait le texte des pages « Salons internationaux » et « Taysir Club Challenge » vers
 * database/content/activities/<slug>.php. Les autres pages des menus Offres et Activités sont des
 * listes d'articles (déjà en base) ou des pages vides. Voir docs/offres-activites.md.
 *
 * Usage : php -d extension=mbstring database/import/wordpress-activities.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

const BASE_PATH = __DIR__ . '/../..';
require __DIR__ . '/WordpressExport.php';

$export = $argv[1] ?? BASE_PATH . '/storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml';
$outputDir = BASE_PATH . '/database/content/activities';
$wp = new WordpressExport($export);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

/** Corrections de saisie : espaces doubles ou manquants après un point. */
$fixes = [
    '/ {2,}/' => ' ',
    '/\.(\p{Lu})/u' => '. $1',
];

foreach (['salons-internationaux', 'taysir-club-challenge'] as $slug) {
    $page = $wp->page($slug);
    $html = '';
    foreach (WordpressExport::widgets($page['elementor'] ?? []) as $widget) {
        if (($widget['widgetType'] ?? '') === 'text-editor') {
            $html .= WordpressExport::cleanHtml($widget['settings']['editor'] ?? '');
        }
    }
    $html = (string) preg_replace(array_keys($fixes), array_values($fixes), $html);
    file_put_contents("{$outputDir}/{$slug}.php", WordpressExport::toPhpFile(
        ['source' => "https://taysir-orientation.com/{$slug}/", 'wordpressTitle' => $page['title'] ?? '', 'intro' => $html],
        "Texte de la page {$slug}, extrait de l'export WordPress. Regénéré par database/import/wordpress-activities.php."
    ));
    printf("%-22s %d caractères\n", $slug, strlen(strip_tags($html)));
}
