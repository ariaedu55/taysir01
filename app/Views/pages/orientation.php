<?php
/**
 * Pages « Quelle filière ? », « Quel secteur ? », « Quel métier ? ».
 *
 * @var string      $slug
 * @var string      $pageTitle
 * @var string      $lead
 * @var string      $intro   Texte repris de WordPress
 * @var list<array> $sectors
 */
use App\Controllers\OrientationController;
use App\Models\Sector;

$steps = [
    ['Rendez-vous', 'En agence, par téléphone ou en visio, avec un conseiller Taysir.'],
    ['Bilan', 'Vos résultats, vos centres d’intérêt et vos contraintes : budget, mobilité, calendrier.'],
    ['Recommandations', 'Des filières, des établissements et des métiers adaptés, puis l’accompagnement jusqu’à l’admission.'],
];
?>
<main id="contenu" class="destinations-page orientation-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li><a href="<?= e(url('/apres-le-bac/')) ?>">S’orienter</a></li>
                    <li aria-current="page"><?= e($pageTitle) ?></li>
                </ol>
            </nav>
            <h1 data-split><?= e($pageTitle) ?></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms"><?= e($lead) ?></p>
        </div>
    </section>

    <div class="wrap orientation-layout">
        <div class="orientation-main">
            <?php if ($intro !== ''): ?>
                <div class="prose orientation-intro" data-reveal="up"><?= $intro ?></div>
            <?php endif; ?>
            <p class="flash" role="note" data-reveal="up">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                Le test en ligne arrive bientôt dans votre espace Taysir. En attendant, nos conseillers vous le font passer lors d’un bilan d’orientation.
            </p>

            <h2 class="orientation-title" data-reveal="up">Explorer par vous-même</h2>
            <ul class="hub-cards hub-cards-compact" data-stagger>
                <?php if ($slug !== 'quel-secteur'): ?>
                    <li data-reveal="up"><a class="hub-card glass" href="<?= e(url('/quel-secteur/')) ?>"><strong>Quel secteur ?</strong><span>Les secteurs qui recrutent</span></a></li>
                <?php endif; ?>
                <?php if ($slug !== 'quel-metier'): ?>
                    <li data-reveal="up"><a class="hub-card glass" href="<?= e(url('/metiers/')) ?>"><strong>Fiches métiers</strong><span>Missions, compétences, formations</span></a></li>
                <?php endif; ?>
                <li data-reveal="up"><a class="hub-card glass" href="<?= e(url('/universites/')) ?>"><strong>Universités</strong><span>Les universités publiques du Sénégal</span></a></li>
                <li data-reveal="up"><a class="hub-card glass" href="<?= e(url('/formations-profesionnelles/')) ?>"><strong>Écoles professionnelles</strong><span>BTS, licences et masters</span></a></li>
                <li data-reveal="up"><a class="hub-card glass" href="<?= e(url('/destination/')) ?>"><strong>Étudier à l’étranger</strong><span>12 destinations</span></a></li>
            </ul>

            <?php if ($slug === 'quel-secteur' || $slug === 'quel-metier'): ?>
                <h2 class="orientation-title" data-reveal="up">Les secteurs d’avenir</h2>
                <ul class="sector-grid" data-stagger>
                    <?php foreach ($sectors as $sector): ?>
                        <li data-reveal="up">
                            <a class="sector-tile glass" data-glow href="<?= e(url('/' . $sector['slug'] . '/')) ?>">
                                <span class="sector-tile-icon"><?= Sector::icon($sector['key']) ?></span>
                                <strong><?= e($sector['name']) ?></strong>
                                <span><?= e($sector['description']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <aside class="orientation-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">Bilan d’orientation</p>
                <h2>Comment ça se passe ?</h2>
                <ol class="orientation-steps">
                    <?php foreach ($steps as $i => [$name, $text]): ?>
                        <li><span><?= $i + 1 ?></span><div><strong><?= e($name) ?></strong><?= e($text) ?></div></li>
                    <?php endforeach; ?>
                </ol>
                <a class="button primary" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
                <a class="button light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
            </div>
        </aside>
    </div>

    <section class="wrap country-cta" aria-labelledby="bilan" data-reveal="scale">
        <div>
            <h2 id="bilan">Faites le point avec un conseiller</h2>
            <p>Un premier rendez-vous suffit pour clarifier votre projet et vos prochaines étapes.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
            <a class="button outline-light" href="<?= e(config('site.contact.whatsapp')) ?>">Écrire sur WhatsApp</a>
        </div>
    </section>
</main>
