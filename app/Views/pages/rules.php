<?php
/**
 * « Nos règles et valeurs » : texte repris de WordPress (database/content/agency/rules.php).
 *
 * @var string $html
 */
$values = ['Transparence', 'Confidentialité', 'Professionnalisme', 'Qualité de service', 'Éthique'];
?>
<main id="contenu" class="destinations-page agency-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li>L’agence</li>
                    <li aria-current="page">Nos règles et valeurs</li>
                </ol>
            </nav>
            <h1 data-split>Nos règles <span class="accent">et valeurs</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">Les principes qui guident chaque dossier, de la préinscription jusqu’au voyage.</p>
            <ul class="value-chips" data-stagger aria-label="Nos règles">
                <?php foreach ($values as $value): ?><li data-reveal="up"><?= e($value) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </section>

    <div class="wrap service-layout">
        <article class="prose legal-text" data-reveal="up"><?= $html ?></article>
        <aside class="service-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">Vos données</p>
                <h2>Confidentialité</h2>
                <p>Comment nous protégeons les informations et les documents que vous nous confiez.</p>
                <a class="button light" href="<?= e(url('/terms-conditions/')) ?>">Lire la politique</a>
                <a class="button light" href="<?= e(url('/about-us/')) ?>">Qui sommes-nous ?</a>
            </div>
        </aside>
    </div>
</main>
