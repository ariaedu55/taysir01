<?php

declare(strict_types=1);

// Initialise le catalogue public sur une base déjà migrée. Usage : php database/seed.php
if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$articles = json_decode(file_get_contents(__DIR__ . '/seeds/articles.json'), true, 512, JSON_THROW_ON_ERROR);
$db = Database::connection();
$insert = $db->prepare(
    'INSERT INTO articles (slug, title, type, sector, excerpt, intro, sections, gallery, image, status, published_at, wordpress_id)
     VALUES (:slug, :title, :type, :sector, :excerpt, :intro, :sections, :gallery, :image, :status, :published_at, :wordpress_id)
     ON DUPLICATE KEY UPDATE slug = articles.slug'
);

$db->beginTransaction();
try {
    $added = 0;
    foreach ($articles as $article) {
        $article['sections'] = json_encode($article['sections'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $article['gallery'] = json_encode($article['gallery'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $insert->execute($article);
        $added += $insert->rowCount();
    }
    $db->commit();
} catch (Throwable $exception) {
    $db->rollBack();
    throw $exception;
}

printf("%d article(s) ajouté(s), %d déjà présent(s).\n", $added, count($articles) - $added);
