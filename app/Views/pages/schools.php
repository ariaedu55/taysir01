<?php
/**
 * Écoles de formation professionnelle.
 *
 * @var list<array> $schools
 */
?>
<main id="contenu" class="institution-page">
    <?php partial('institution-hero', [
        'title' => 'Écoles supérieures',
        'visual' => 'pratique',
        'context' => 'Après le bac',
        'lead' => 'Explorez ' . count($schools) . ' établissements et leurs filières professionnelles, de la santé à la gestion en passant par les sciences et techniques.',
        'trail' => [['Après le bac', url('/apres-le-bac/')]],
        'current' => 'Écoles supérieures',
        'target' => 'etablissements',
        'action' => 'Voir les écoles',
        'catalogue' => true,
    ]); ?>

    <section class="wrap destinations-list institution-catalogue" id="etablissements" aria-labelledby="liste-ecoles" data-filter>
        <div class="section-head">
            <h2 id="liste-ecoles">Trouver une école</h2>
            <a href="<?= e(url('/universites/')) ?>">Voir les universités</a>
        </div>
        <div class="content-filter" hidden data-filter-bar>
            <label class="content-search">
                <span class="sr-only">Rechercher une école ou une filière</span>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" placeholder="Rechercher une école ou une filière" data-filter-search>
            </label>
        </div>
        <p class="sr-only" aria-live="polite" data-filter-status></p>
        <?php partial('article-cards', ['articles' => $schools, 'filterable' => true]); ?>
        <p class="content-empty" hidden data-filter-empty>Aucune école ne correspond à votre recherche.</p>
    </section>

    <section class="wrap country-cta" aria-labelledby="inscrire" data-reveal="scale">
        <div>
            <h2 id="inscrire">Préparer votre inscription</h2>
            <p>Créez votre espace : un conseiller vous aide à choisir l’école et la filière, puis à constituer votre dossier.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
            <a class="button outline-light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
        </div>
    </section>
</main>
