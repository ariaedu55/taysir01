<?php
/**
 * /temoignage/ (témoignages et photos de clients) et /avis-etudiants/ (témoignages d'étudiants).
 *
 * @var list<array>  $testimonials
 * @var list<string> $photos       Chemins de médias WordPress
 * @var bool         $studentsOnly
 */
?>
<main id="contenu" class="destinations-page agency-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li><?= $studentsOnly ? 'Après le bac' : 'L’agence' ?></li>
                    <li aria-current="page"><?= $studentsOnly ? 'Conseils des anciens étudiants' : 'Témoignages' ?></li>
                </ol>
            </nav>
            <?php if ($studentsOnly): ?>
                <h1 data-split>Ils étudient <span class="accent">à l’étranger</span></h1>
                <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">Au Canada ou en France, des étudiants accompagnés par Taysir racontent leur parcours, de l’admission au visa.</p>
            <?php else: ?>
                <h1 data-split>Chaque voyageur a <span class="accent">son histoire</span></h1>
                <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">Études, affaires, tourisme : découvrez l’aventure de celles et ceux qui ont préparé leur départ avec Taysir.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="wrap about-block" aria-label="Témoignages">
        <?php partial('testimonial-cards', ['testimonials' => $testimonials]); ?>
    </section>

    <?php if ($photos !== []): ?>
        <section class="wrap about-block" aria-labelledby="photos-titre">
            <p class="kicker" data-reveal="up">Visas obtenus</p>
            <h2 id="photos-titre" data-reveal="up">Ils sont partis avec Taysir</h2>
            <ul class="photo-wall" data-stagger>
                <?php foreach ($photos as $photo): ?>
                    <li data-reveal="up"><img src="<?= e(media_url($photo)) ?>" alt="" loading="lazy" decoding="async"></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="wrap country-cta" aria-labelledby="cta-titre" data-reveal="scale">
        <div>
            <h2 id="cta-titre">À votre tour</h2>
            <p>Créez votre espace et faites votre demande en ligne, ou commencez par un rendez-vous.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
            <a class="button outline-light" href="<?= e(url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
        </div>
    </section>
</main>
