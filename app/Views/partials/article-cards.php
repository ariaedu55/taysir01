<?php
/**
 * Grille de cartes d'articles.
 *
 * @var list<array> $articles
 * @var bool|null   $filterable Ajoute les attributs lus par le filtre (content.js)
 * @var string|null $filterBy   Critère du filtre : « sector » (secteur du métier, par défaut) ou « family » (famille du blog)
 */
use App\Models\Article;
use App\Models\Sector;

$filterable ??= false;
$filterBy ??= 'sector';
?>
<ul class="article-cards" data-stagger<?= $filterable ? ' data-filter-items' : '' ?>>
    <?php foreach ($articles as $article): ?>
        <?php
        $sector = $article['type'] === 'metier' ? Sector::byKey($article['sector']) : null;
        $meta = $sector['name'] ?? Article::typeLabel($article);
        $image = Article::imageUrl($article);
        $search = strtolower($article['title'] . ' ' . $meta . ' ' . $article['excerpt']);
        ?>
        <li data-reveal="up"<?= $filterable ? ' data-sector="' . e(($filterBy === 'family' ? Article::family($article) : $article['sector']) ?? '') . '" data-search="' . e($search) . '"' : '' ?>>
            <a class="article-card" href="<?= e(url('/' . $article['slug'] . '/')) ?>">
                <span class="article-card-media">
                    <?php if ($image): ?><img src="<?= e($image) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                </span>
                <span class="article-card-body">
                    <span class="article-card-meta">
                        <?php if ($sector): ?><?= Sector::icon($sector['key'], 15) ?><?php endif; ?>
                        <?= e($meta) ?>
                        <?php if ($article['status'] === 'archive'): ?><span class="article-card-date">· <?= e(Article::monthYear($article)) ?></span><?php endif; ?>
                    </span>
                    <span class="article-card-title"><?= e($article['title']) ?></span>
                    <?php if ($article['excerpt']): ?><span class="article-card-excerpt"><?= e($article['excerpt']) ?></span><?php endif; ?>
                    <span class="article-card-more" aria-hidden="true"><?= $article['type'] === 'metier' ? 'Découvrir le métier' : 'Lire la suite' ?><span class="article-card-arrow"><svg viewBox="0 0 16 16" width="15" height="15"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></span>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
