<?php
/**
 * Page des menus « Offres » et « Activités » (config/activities.php).
 *
 * @var array       $page
 * @var string      $intro     Texte repris de WordPress (HTML nettoyé), ou ''
 * @var list<array> $sections  [title, text, articles]
 * @var list<array> $siblings  Autres pages du même menu
 */
use App\Controllers\ActivityController;

[$visual, $action] = $page['hero'];
$cover = asset('images/activities/' . $visual);
[$asideKicker, $asideTitle, $asideText, $asideActions] = $page['aside'];
?>
<main id="contenu" class="activity-page">
    <?php partial('institution-hero', [
        'title' => $page['title'],
        'image' => $cover . '.webp',
        'portrait' => $cover . '-portrait.webp',
        'context' => $page['group'],
        'lead' => $page['lead'],
        'trail' => [],
        'current' => $page['title'],
        'target' => 'decouvrir',
        'action' => $action,
        'catalogue' => true,
    ]); ?>

    <div class="wrap service-layout" id="decouvrir" tabindex="-1">
        <div class="service-main">
            <?php if (!empty($page['notice'])): ?>
                <p class="flash" role="note" data-reveal="up">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    <?= e($page['notice']) ?>
                </p>
            <?php endif; ?>

            <?php if ($intro !== ''): ?>
                <div class="prose activity-intro" data-reveal="up"><?= $intro ?></div>
            <?php endif; ?>

            <?php if (!empty($page['skills'])): ?>
                <ul class="activity-skills" data-stagger aria-label="Compétences développées">
                    <?php foreach ($page['skills'] as $i => $skill): ?>
                        <li class="glass" data-reveal="up"><span><?= $i + 1 ?></span><?= e($skill) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($page['video'])): ?>
                <?php [$videoId, $videoTitle, $videoSource] = $page['video']; ?>
                <figure class="video-card" data-reveal="up">
                    <div class="video-frame" data-video="<?= e($videoId) ?>" data-video-title="<?= e($videoTitle) ?>">
                        <button class="video-play" type="button">
                            <span class="video-play-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg></span>
                            <span class="video-play-text">
                                <strong><?= e($videoTitle) ?></strong>
                                <span>Lire la vidéo (YouTube)</span>
                            </span>
                        </button>
                    </div>
                    <figcaption><?= e($videoSource) ?>. La vidéo se charge depuis YouTube quand vous la lancez.</figcaption>
                </figure>
            <?php endif; ?>

            <?php foreach ($sections as $i => $section): ?>
                <section class="activity-section" aria-labelledby="liste-<?= $i ?>">
                    <h2 class="orientation-title" id="liste-<?= $i ?>" data-reveal="up"><?= e($section['title']) ?> <span class="activity-count"><?= count($section['articles']) ?></span></h2>
                    <?php if ($section['text']): ?><p class="activity-section-text" data-reveal="up"><?= e($section['text']) ?></p><?php endif; ?>
                    <?php partial('article-cards', ['articles' => $section['articles']]); ?>
                </section>
            <?php endforeach; ?>

            <?php if (!empty($page['links'])): ?>
                <section aria-labelledby="liens-titre">
                    <h2 class="orientation-title" id="liens-titre" data-reveal="up"><?= empty($page['notice']) ? 'Par où commencer ?' : 'En attendant' ?></h2>
                    <ul class="hub-cards hub-cards-compact" data-stagger>
                        <?php foreach ($page['links'] as [$name, $text, $href]): ?>
                            <li data-reveal="up"><a class="hub-card glass" data-glow href="<?= e(url($href)) ?>"><strong><?= e($name) ?></strong><span><?= e($text) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>

        <aside class="service-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker"><?= e($asideKicker) ?></p>
                <h2><?= e($asideTitle) ?></h2>
                <p><?= e($asideText) ?></p>
                <?php foreach ($asideActions as [$label, $href, $style]): ?>
                    <a class="button <?= e($style) ?><?= str_contains($label, '@') ? ' button-email' : '' ?>" href="<?= e(ActivityController::href($href)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <nav class="article-aside-card glass service-others" aria-label="<?= e($page['group']) ?>" data-reveal="right">
                <p class="kicker"><?= e($page['group']) ?></p>
                <ul>
                    <?php foreach ($siblings as $sibling): ?>
                        <li><a href="<?= e(url('/' . $sibling['slug'] . '/')) ?>"><?= e($sibling['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>
    </div>
</main>
