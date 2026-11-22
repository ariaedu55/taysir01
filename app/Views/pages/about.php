<?php
/**
 * « Qui sommes-nous ? » : contenu repris de WordPress (database/content/agency/about.php).
 *
 * @var array       $about
 * @var list<array> $testimonials
 */
[$welcome, $lead, $intro] = $about['welcome'] + ['', '', ''];
$highlights = array_slice(array_values(array_filter($testimonials, static fn (array $t) => $t['photo'] !== null)), 0, 2);
$highlights = array_merge($highlights, array_slice(array_values(array_filter($testimonials, static fn (array $t) => $t['photo'] === null)), 0, 3 - count($highlights)));
$pillarIcons = [
    '<path d="M12 21s-7-4.5-7-11a7 7 0 0 1 14 0c0 6.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
    '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
    '<circle cx="12" cy="12" r="3"/><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>',
];
?>
<main id="contenu" class="destinations-page agency-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li>L’agence</li>
                    <li aria-current="page">Qui sommes-nous ?</li>
                </ol>
            </nav>
            <h1 data-split>Qui sommes-<span class="accent">nous ?</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms"><?= e($welcome) ?></p>
        </div>
    </section>

    <section class="wrap about-intro" aria-label="Présentation">
        <p class="about-lead" data-reveal="up"><?= e($lead) ?></p>
        <p data-reveal="up" style="--delay: 120ms"><?= e($intro) ?></p>
    </section>

    <section class="about-stats" aria-label="Taysir en chiffres">
        <ul class="wrap" data-stagger>
            <?php foreach ($about['stats'] as [$value, $label]): ?>
                <li data-reveal="up"><strong><?= e($value) ?></strong><span><?= e($label) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wrap about-block" aria-labelledby="piliers-titre">
        <h2 class="sr-only" id="piliers-titre">Mission, engagement et vision</h2>
        <ul class="pillar-grid" data-stagger>
            <?php foreach ($about['pillars'] as $i => [$name, $text]): ?>
                <li class="pillar glass" data-reveal="up">
                    <span class="pillar-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $pillarIcons[$i] ?? $pillarIcons[0] ?></svg></span>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($text) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wrap about-block about-columns">
        <div aria-labelledby="services-titre">
            <p class="kicker" data-reveal="up">Ce que nous faisons</p>
            <h2 id="services-titre" data-reveal="up">Nos services</h2>
            <dl class="about-list" data-stagger>
                <?php foreach ($about['services'] as [$name, $text]): ?>
                    <div data-reveal="up"><dt><?= e($name) ?></dt><dd><?= e($text) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div aria-labelledby="raisons-titre">
            <p class="kicker" data-reveal="up">Notre différence</p>
            <h2 id="raisons-titre" data-reveal="up">Pourquoi nous choisir ?</h2>
            <dl class="about-list" data-stagger>
                <?php foreach ($about['reasons'] as [$name, $text]): ?>
                    <div data-reveal="up"><dt><?= e($name) ?></dt><dd><?= e($text) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <section class="wrap about-block" aria-labelledby="atouts-titre">
        <p class="kicker" data-reveal="up">Un accompagnement pratique et simple</p>
        <h2 id="atouts-titre" data-reveal="up">Nos atouts</h2>
        <ol class="advantage-grid" data-stagger>
            <?php foreach ($about['advantages'] as $i => [$name, $text]): ?>
                <li data-reveal="up"><span class="advantage-number"><?= sprintf('%02d', $i + 1) ?></span><h3><?= e($name) ?></h3><p><?= e($text) ?></p></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="wrap about-block" aria-labelledby="equipe-titre">
        <p class="kicker" data-reveal="up">Les visages de Taysir</p>
        <h2 id="equipe-titre" data-reveal="up">Rencontrez l’équipe</h2>
        <ul class="team-grid" data-stagger>
            <?php foreach ($about['team'] as $member): ?>
                <li class="team-card" data-reveal="up">
                    <span class="team-photo"><?php if ($member['photo']): ?><img src="<?= e(media_url($member['photo'])) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?></span>
                    <strong><?= e($member['name']) ?></strong>
                    <span><?= e($member['role']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wrap about-block" aria-labelledby="avis-titre">
        <div class="section-head">
            <div>
                <p class="kicker" data-reveal="up">Ils nous ont fait confiance</p>
                <h2 id="avis-titre" data-reveal="up">Ce que disent nos clients</h2>
            </div>
            <a class="button light" href="<?= e(url('/temoignage/')) ?>" data-reveal="up">Tous les témoignages</a>
        </div>
        <?php partial('testimonial-cards', ['testimonials' => $highlights]); ?>
    </section>

    <section class="wrap country-cta" aria-labelledby="cta-titre" data-reveal="scale">
        <div>
            <h2 id="cta-titre">Parlons de votre projet</h2>
            <p>Un premier échange avec un conseiller, en agence ou à distance.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
            <a class="button outline-light" href="<?= e(url('/contact/')) ?>">Nous contacter</a>
        </div>
    </section>
</main>
