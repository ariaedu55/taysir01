<?php
/**
 * Fiche école supérieure.
 *
 * @var array $article
 * @var array $related
 */
use App\Models\Article;

$school = $article;
[$visual, $focus, $lead, $source] = array_pad(config('institutions.' . $school['slug'], config('institutions.ecole')), 4, null);
$title = $school['slug'] === 'institut-polytechnique-de-dakar-ipd-thomas-sankara'
    ? 'Institut national du pétrole et du gaz'
    : $school['title'];
$sections = $school['sections'];
?>
<main id="contenu" class="institution-page">
    <?php partial('institution-hero', [
        'title' => $title,
        'visual' => $visual,
        'context' => 'École supérieure · ' . $focus,
        'lead' => $lead,
        'trail' => [['Écoles supérieures', url('/formations-profesionnelles/')]],
        'current' => $title,
        'target' => 'presentation',
        'action' => 'Découvrir l’établissement',
    ]); ?>

    <?php partial('institution-profile', [
        'title' => 'Présentation de l’établissement',
        'focus' => $focus,
        'image' => Article::imageUrl($school),
        'intro' => $school['intro'] ?? '',
        'source' => $source,
        'school' => true,
    ]); ?>

    <div class="wrap country-body">
        <aside class="country-aside">
            <?php partial('institution-outline', ['sections' => $sections, 'prefix' => 'partie-']); ?>
            <div class="country-help">
                <p><strong>Préparer votre inscription</strong> Échangez avec un conseiller sur votre choix de filière et votre dossier.</p>
                <div class="institution-help-links">
                    <a class="button primary" href="<?= e(url('/demande-dadmission/')) ?>">Préparer mon admission</a>
                    <a href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
                </div>
            </div>
        </aside>

        <div class="country-content">
            <?php if ($school['status'] === 'archive'): ?>
                <p class="flash article-archive" role="note">
                    Fiche publiée en <?= e(Article::monthYear($school)) ?> et conservée en archive. Les informations ont pu évoluer.
                </p>
            <?php endif; ?>
            <?php if ($sections !== []): ?>
                <h2 class="institution-section-title">Formations et vie étudiante</h2>
                <?php partial('institution-topics', ['sections' => $sections, 'prefix' => 'partie-']); ?>
            <?php endif; ?>
            <?php if ($school['gallery'] !== []): ?>
                <section class="article-section" aria-labelledby="galerie">
                    <h2 id="galerie">L’établissement en images</h2>
                    <ul class="article-gallery" data-stagger>
                        <?php foreach ($school['gallery'] as $path): ?>
                            <li data-reveal="scale"><img src="<?= e(media_url($path)) ?>" alt="" loading="lazy" decoding="async"></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <section class="section article-related" aria-labelledby="a-lire">
            <div class="wrap">
                <div class="section-head">
                    <h2 id="a-lire">Autres écoles à découvrir</h2>
                    <a href="<?= e(url('/formations-profesionnelles/')) ?>">Toutes les écoles</a>
                </div>
                <?php partial('article-cards', ['articles' => $related]); ?>
            </div>
        </section>
    <?php endif; ?>
</main>
