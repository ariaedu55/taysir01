<?php

/*
 * Extrait le texte des pages d'orientation (« Quel secteur ? », « Quel métier ? », « Quelle filière ? »)
 * vers database/content/orientation/<slug>.php. Les tests d'orientation de ces pages reposaient sur
 * l'extension QSM, dont les questions ne sont pas dans l'export : ils ne sont pas repris.
 *
 * Usage : php -d extension=mbstring database/import/wordpress-orientation.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

const BASE_PATH = __DIR__ . '/../..';
require __DIR__ . '/WordpressExport.php';

$export = $argv[1] ?? BASE_PATH . '/storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml';
$outputDir = BASE_PATH . '/database/content/orientation';
$wp = new WordpressExport($export);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

foreach (['quel-secteur', 'quel-metier', 'quelle-filiere'] as $slug) {
    $page = $wp->page($slug);
    $html = '';
    foreach (WordpressExport::widgets($page['elementor'] ?? []) as $widget) {
        if (($widget['widgetType'] ?? '') === 'text-editor') {
            $html .= WordpressExport::cleanHtml($widget['settings']['editor'] ?? '');
        }
    }
    file_put_contents("{$outputDir}/{$slug}.php", WordpressExport::toPhpFile(
        ['source' => "https://taysir-orientation.com/{$slug}/", 'wordpressTitle' => $page['title'] ?? '', 'intro' => $html],
        "Texte de la page {$slug}, extrait de l'export WordPress. Regénéré par database/import/wordpress-orientation.php."
    ));
    printf("%-15s %d caractères\n", $slug, strlen(strip_tags($html)));
}
