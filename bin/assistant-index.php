<?php

declare(strict_types=1);

/*
 * Reconstruit l'index de l'assistant IA et affiche ses statistiques (docs/assistant-ia.md).
 * L'index se reconstruit aussi tout seul dès qu'un contenu change ; ce script sert après un déploiement ou pour tester.
 *
 * Usage : php bin/assistant-index.php ["question de test"]
 */
if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Assistant\KnowledgeBase;

KnowledgeBase::rebuild();
foreach (KnowledgeBase::stats() as $label => $value) {
    printf("%-10s %s\n", $label, $value);
}

if (isset($argv[1])) {
    echo "\nQuestion : {$argv[1]}\n";
    foreach (KnowledgeBase::search($argv[1]) as $i => $hit) {
        printf("%d. [%.2f] %s — %s\n   %s…\n", $i + 1, $hit['score'], $hit['title'], $hit['url'], mb_substr($hit['text'], 0, 140));
    }
}
