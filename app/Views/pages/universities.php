<?php
/**
 * Liste des universités publiques.
 *
 * @var list<array> $universities
 */
?>
<main id="contenu" class="institution-page">
    <?php partial('institution-hero', [
        'title' => 'Les universités du Sénégal',
        'visual' => 'ugb',
        'context' => 'Après le bac',
        'lead' => 'Explorez ' . count($universities) . ' universités publiques pour repérer les campus et les formations qui correspondent à votre projet.',
        'trail' => [],
        'current' => 'Universités',
        'target' => 'etablissements',
        'action' => 'Voir les universités',
        'catalogue' => true,
    ]); ?>

    <section class="wrap destinations-list institution-catalogue" id="etablissements" aria-labelledby="liste-universites">
        <div class="section-head">
            <h2 id="liste-universites">Explorer les universités</h2>
            <a href="<?= e(url('/formations-profesionnelles/')) ?>">Voir les écoles supérieures</a>
        </div>
        <?php partial('university-cards', ['universities' => $universities]); ?>
    </section>

    <section class="wrap country-cta" aria-labelledby="choisir" data-reveal="scale">
        <div>
            <h2 id="choisir">Quelle université pour votre projet ?</h2>
            <p>Un conseiller vous aide à choisir la filière et l’établissement selon votre bac, vos résultats et votre projet professionnel.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
            <a class="button outline-light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
        </div>
    </section>
</main>
