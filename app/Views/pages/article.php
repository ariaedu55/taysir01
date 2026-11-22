<?php
/**
 * Article : fiche métier, offre, bourse, salon.
 *
 * @var array       $article
 * @var array|null  $sector  Secteur d'une fiche métier
 * @var list<array> $related
 */
use App\Models\Article;
use App\Models\Sector;

$a = $article;
$image = Article::imageUrl($a);
$isArchive = $a['status'] === 'archive';
$whatsapp = config('site.contact.whatsapp');

// Fil d'Ariane et appel à l'action selon le type.
$trail = match (true) {
    $a['type'] === 'metier' => [['Métiers', url('/metiers/')], [$sector['name'] ?? 'Secteur', $sector ? url('/' . $sector['slug'] . '/') : url('/metiers/')]],
    $a['type'] === 'salon' => [['Salon collégien', legacy_url('/salon-collegien/')]],
    in_array($a['type'], ['offre-stage'], true) => [['Offres de stage', legacy_url('/offre_stage/')]],
    default => [['Offres et opportunités', legacy_url('/offre-demploie/')]],
};
[$ctaTitle, $ctaText, $ctaPrimary, $ctaPrimaryUrl] = match ($a['type']) {
    'metier' => ['Ce métier vous attire ?', 'Un conseiller vous aide à choisir la formation qui y mène, au Sénégal ou à l’étranger.', 'Trouver ma formation', legacy_url('/rendez_vous_orientation/')],
    default => ['Ne manquez plus aucune opportunité', 'Créez votre espace Taysir pour être accompagné(e) dans vos candidatures.', 'Créer mon compte', url('/inscription/')],
};
?>
<main id="contenu" class="article-page">
    <header class="wrap article-hero">
        <nav class="breadcrumb" aria-label="Fil d’Ariane">
            <ol>
                <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                <?php foreach ($trail as [$label, $href]): ?>
                    <li><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
                <li aria-current="page"><?= e($a['title']) ?></li>
            </ol>
        </nav>
        <p class="article-chips" data-reveal="up">
            <span class="chip article-chip"><?php if ($sector): ?><?= Sector::icon($sector['key'], 16) ?><?php endif; ?><?= e($sector['name'] ?? Article::typeLabel($a)) ?></span>
            <?php if ($a['type'] === 'metier'): ?><span class="chip article-chip is-soft">Fiche métier</span><?php endif; ?>
            <?php if ($isArchive): ?><span class="chip article-chip is-soft">Publié en <?= e(Article::monthYear($a)) ?></span><?php endif; ?>
        </p>
        <h1 data-split><?= e($a['title']) ?></h1>
    </header>

    <div class="wrap article-layout">
        <article class="article-main">
            <?php if ($image): ?>
                <figure class="article-cover" data-reveal="up"><img src="<?= e($image) ?>" alt="" fetchpriority="high"></figure>
            <?php endif; ?>

            <?php if ($isArchive): ?>
                <p class="flash article-archive" role="note">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    Publication de <?= e(Article::monthYear($a)) ?> conservée en archive : cette opportunité n’est sans doute plus d’actualité.
                </p>
            <?php endif; ?>

            <?php if ($a['intro']): ?>
                <div class="prose article-intro" data-reveal="up"><?= $a['intro'] ?></div>
            <?php endif; ?>

            <?php foreach ($a['sections'] as $i => $section): ?>
                <section class="article-section" data-reveal="up" aria-labelledby="partie-<?= $i + 1 ?>">
                    <h2 id="partie-<?= $i + 1 ?>"><?= e($section['title']) ?></h2>
                    <div class="prose"><?= $section['html'] ?></div>
                </section>
            <?php endforeach; ?>

            <?php if ($a['gallery'] !== []): ?>
                <section class="article-section" aria-labelledby="galerie">
                    <h2 id="galerie">En images</h2>
                    <ul class="article-gallery" data-stagger>
                        <?php foreach ($a['gallery'] as $path): ?>
                            <li data-reveal="scale"><img src="<?= e(media_url($path)) ?>" alt="" loading="lazy" decoding="async"></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </article>

        <aside class="article-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">Taysir vous accompagne</p>
                <h2><?= e($ctaTitle) ?></h2>
                <p><?= e($ctaText) ?></p>
                <a class="button primary" href="<?= e($ctaPrimaryUrl) ?>"><?= e($ctaPrimary) ?></a>
                <a class="button light" href="<?= e($whatsapp) ?>">Écrire sur WhatsApp</a>
            </div>
            <?php if ($sector): ?>
                <a class="article-aside-link glass" href="<?= e(url('/' . $sector['slug'] . '/')) ?>" data-reveal="right" style="--delay: 120ms">
                    <span class="article-aside-icon"><?= Sector::icon($sector['key']) ?></span>
                    <span><strong>Tous les métiers : <?= e($sector['name']) ?></strong><?= e($sector['description']) ?></span>
                </a>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($related !== []): ?>
        <section class="section article-related" aria-labelledby="a-lire">
            <div class="wrap">
                <div class="section-head">
                    <h2 id="a-lire"><?= $a['type'] === 'metier' ? 'Métiers du même secteur' : 'À découvrir aussi' ?></h2>
                </div>
                <?php partial('article-cards', ['articles' => $related]); ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="wrap country-cta" aria-labelledby="pret" data-reveal="scale">
        <div>
            <h2 id="pret"><?= e($ctaTitle) ?></h2>
            <p><?= e($ctaText) ?></p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e($ctaPrimaryUrl) ?>"><?= e($ctaPrimary) ?></a>
            <a class="button outline-light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
        </div>
    </section>
</main>
