<?php
/**
 * Page d'un secteur d'avenir.
 *
 * @var array       $sector
 * @var list<array> $careers       Fiches métiers du secteur
 * @var list<array> $opportunities Dernières offres (archives 2024)
 * @var list<array> $sectors
 */
use App\Models\Sector;
?>
<main id="contenu" class="destinations-page sector-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li><a href="<?= e(url('/metiers/')) ?>">Métiers</a></li>
                    <li aria-current="page"><?= e($sector['name']) ?></li>
                </ol>
            </nav>
            <p class="chip sector-chip" data-reveal="up"><?= Sector::icon($sector['key'], 18) ?>Secteur d’avenir</p>
            <h1 data-split><?= e($sector['name']) ?></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms"><?= e($sector['description']) ?> Découvrez <?= count($careers) ?> métiers du secteur : missions, compétences et formations pour y accéder.</p>
        </div>
    </section>

    <section class="wrap destinations-list" aria-label="Métiers du secteur">
        <?php partial('article-cards', ['articles' => $careers]); ?>
    </section>

    <nav class="wrap sector-switch" aria-label="Autres secteurs">
        <p class="kicker">Autres secteurs</p>
        <ul>
            <?php foreach ($sectors as $other): ?>
                <li><a class="chip sector-link<?= $other['key'] === $sector['key'] ? ' is-current' : '' ?>" href="<?= e(url('/' . $other['slug'] . '/')) ?>"<?= $other['key'] === $sector['key'] ? ' aria-current="page"' : '' ?>><?= Sector::icon($other['key'], 16) ?><?= e($other['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <?php if ($opportunities !== []): ?>
        <section class="section sector-opportunities" aria-labelledby="opportunites">
            <div class="wrap">
                <div class="section-head">
                    <div>
                        <h2 id="opportunites">Offres d’emploi, de stage et formations</h2>
                        <p>Dernières opportunités publiées par l’agence (archives de 2024).</p>
                    </div>
                </div>
                <?php partial('article-cards', ['articles' => $opportunities]); ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="wrap country-cta" aria-labelledby="orienter" data-reveal="scale">
        <div>
            <h2 id="orienter">Un métier de ce secteur vous attire ?</h2>
            <p>Un conseiller vous aide à choisir la formation qui y mène, au Sénégal ou à l’étranger.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
            <a class="button outline-light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
        </div>
    </section>
</main>
