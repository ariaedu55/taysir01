<?php
/**
 * Toutes les fiches métiers, filtrables par secteur et par recherche (content.js).
 *
 * @var list<array> $careers
 * @var list<array> $sectors
 */
use App\Models\Sector;

?>
<main id="contenu" class="destinations-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li aria-current="page">Métiers</li>
                </ol>
            </nav>
            <h1 data-split>Trouvez le métier <span class="accent">qui vous ressemble</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms"><?= count($careers) ?> fiches métiers dans <?= count($sectors) ?> secteurs d’avenir : missions, compétences attendues et formations pour y accéder.</p>
        </div>
    </section>

    <section class="wrap destinations-list" aria-label="Fiches métiers" data-filter>
        <div class="content-filter" hidden data-filter-bar>
            <label class="content-search">
                <span class="sr-only">Rechercher un métier</span>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" placeholder="Rechercher un métier (infirmier, data, architecte…)" data-filter-search>
            </label>
        </div>
        <p class="sr-only" aria-live="polite" data-filter-status></p>
        <?php partial('article-cards', ['articles' => $careers, 'filterable' => true]); ?>
        <p class="content-empty" hidden data-filter-empty>Aucun métier ne correspond à votre recherche. Un conseiller peut vous aider à explorer d’autres pistes.</p>
    </section>

    <section class="wrap country-cta" aria-labelledby="hesiter" data-reveal="scale">
        <div>
            <h2 id="hesiter">Vous hésitez encore ?</h2>
            <p>Un bilan d’orientation avec un conseiller permet de relier vos goûts, vos résultats et les métiers qui recrutent.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
            <a class="button outline-light" href="<?= e(url('/quel-metier/')) ?>">Quel métier pour moi ?</a>
        </div>
    </section>
</main>
