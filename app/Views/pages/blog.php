<?php
/**
 * /blog/ : toutes les publications, filtrables par famille et par recherche (content.js).
 *
 * @var list<array>                                                  $articles
 * @var array<string, array{label: string, count: int, page: ?string}> $families
 */
?>
<main id="contenu" class="destinations-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li aria-current="page">Publications</li>
                </ol>
            </nav>
            <h1 data-split>Toutes nos <span class="accent">publications</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms"><?= count($articles) ?> publications pour préparer votre avenir : fiches métiers, écoles, offres, bourses et salons d’orientation.</p>
        </div>
    </section>

    <section class="wrap" aria-label="Rubriques">
        <ul class="hub-cards hub-cards-compact blog-families" data-stagger>
            <?php foreach ($families as $key => $family): ?>
                <?php if ($family['page']): ?>
                    <li data-reveal="up"><a class="hub-card glass" data-glow href="<?= e(url($family['page'])) ?>"><strong><?= e($family['label']) ?></strong><span><?= $family['count'] ?> publication<?= $family['count'] > 1 ? 's' : '' ?></span></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wrap destinations-list" aria-label="Publications" data-filter>
        <div class="content-filter" hidden data-filter-bar>
            <label class="content-search">
                <span class="sr-only">Rechercher une publication</span>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" placeholder="Rechercher (infirmier, bourse, Canada, BTS…)" data-filter-search>
            </label>
        </div>
        <p class="sr-only" aria-live="polite" data-filter-status></p>
        <?php partial('article-cards', ['articles' => $articles, 'filterable' => true, 'filterBy' => 'family']); ?>
        <p class="content-empty" hidden data-filter-empty>Aucune publication ne correspond à votre recherche. Un conseiller peut vous aider : prenez rendez-vous.</p>
    </section>

    <section class="wrap country-cta" aria-labelledby="blog-cta" data-reveal="scale">
        <div>
            <h2 id="blog-cta">Un projet en tête ?</h2>
            <p>Créez votre espace pour faire vos demandes en ligne, ou commencez par un rendez-vous avec un conseiller.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
            <a class="button outline-light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
        </div>
    </section>
</main>
