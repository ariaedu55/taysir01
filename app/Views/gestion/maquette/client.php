<?php
/**
 * Suivi côté client (maquette, docs/gestion-plan.md §12.2) : accès Client commun, parcours études ou voyage.
 * Priorité absolue : « Ce qu'il vous reste à faire », visible sans défiler sur téléphone (tâche T3). Données fictives.
 *
 * @var string $parcours
 * @var array  $client, $etapes
 */
require BASE_PATH . '/app/Views/gestion/icones.php';
$c = $client[$parcours];
[$intitule, $ref, $etapeActuelle, $interlocuteur] = $c['dossier'];
$base = '/gestion/maquette/client/';
$icones = ['piece' => 'file', 'piece-refusee' => 'alert', 'paiement' => 'wallet'];
?>
<main id="contenu" class="c-space">
    <div class="c-wrap">
        <p class="c-mock"><?= gestion_icone('alert', 15) ?> Maquette, données fictives ·
            <a href="<?= e(url($base)) ?>?parcours=etudes"<?= $parcours === 'etudes' ? ' aria-current="page"' : '' ?>>Parcours études</a> ·
            <a href="<?= e(url($base)) ?>?parcours=voyage"<?= $parcours === 'voyage' ? ' aria-current="page"' : '' ?>>Parcours voyage</a> ·
            <a href="<?= e(url('/gestion/maquette/')) ?>">Espace équipe</a>
        </p>

        <header class="c-hello">
            <h1>Bonjour <?= e($c['prenom']) ?></h1>
            <p><?= e($intitule) ?> · <?= e($interlocuteur) ?></p>
        </header>

        <?php
        // Ce qui demande une action d'abord (à refaire, puis à faire) ; « en vérification » à la fin
        $rang = static fn (array $a): int => $a[3] === null ? 2 : ($a[0] === 'piece-refusee' ? 0 : 1);
        usort($c['afaire'], static fn (array $a, array $b): int => $rang($a) <=> $rang($b));
        ?>
        <section class="c-todo" aria-labelledby="todo-titre">
            <h2 id="todo-titre">Ce qu’il vous reste à faire <span class="c-count"><?= count(array_filter($c['afaire'], static fn (array $a): bool => $a[3] !== null)) ?></span></h2>
            <ul>
                <?php $suivante = true; ?>
                <?php foreach ($c['afaire'] as [$type, $titre, $raison, $action]): ?>
                    <?php $premier = $suivante && $action !== null; $suivante = $suivante && !$premier; ?>
                    <li class="c-todo-item<?= $type === 'piece-refusee' ? ' is-refused' : '' ?><?= $type === 'paiement' ? ' is-payment' : '' ?><?= $action === null ? ' is-waiting' : '' ?><?= $premier ? ' is-next' : '' ?>" data-g-item="<?= e($titre) ?>">
                        <span class="c-todo-icon" aria-hidden="true"><?= gestion_icone($icones[$type] ?? 'file', 19) ?></span>
                        <div>
                            <?php if ($premier): ?><span class="c-next">À faire en premier</span><?php endif; ?>
                            <strong><?= e($titre) ?></strong>
                            <p><?= e($raison) ?></p>
                        </div>
                        <?php if ($action !== null): ?>
                            <?php if (str_starts_with($type, 'piece')): ?>
                                <button class="c-btn<?= $premier ? '' : ' c-btn-outline' ?>" type="button" data-g-demo="deposer"><?= gestion_icone('upload', 17) ?> <?= e($action) ?></button>
                            <?php else: ?>
                                <button class="c-btn<?= $premier ? '' : ' c-btn-light' ?>" type="button" data-g-demo="paiement" data-g-message="Moyens de paiement : Wave, Orange Money, virement ou en agence. Votre reçu arrive dans votre espace."><?= e($action) ?></button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="c-status"><?= gestion_icone('clock', 15) ?> En vérification</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <div class="c-grid">
            <section class="c-card" aria-labelledby="dossier-titre">
                <div class="c-card-head"><h2 id="dossier-titre"><?= e($intitule) ?></h2><span class="c-ref"><?= e($ref) ?></span></div>
                <ol class="c-steps">
                    <?php foreach ($etapes[$parcours] as $i => $etape): ?>
                        <li class="<?= $i < $etapeActuelle ? 'is-done' : ($i === $etapeActuelle ? 'is-current' : '') ?>"<?= $i === $etapeActuelle ? ' aria-current="step"' : '' ?>>
                            <span aria-hidden="true"><?= $i < $etapeActuelle ? '✓' : '' ?></span>
                            <strong><?= e($etape) ?></strong>
                            <?php if ($i === $etapeActuelle): ?><small>Étape en cours</small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <aside class="c-side">
                <section class="c-card">
                    <h2 class="c-small-title">Prochain rendez-vous</h2>
                    <p class="c-rdv"><strong><?= e($c['rdv'][0]) ?></strong><span><?= e($c['rdv'][1]) ?></span><span class="c-muted"><?= e($c['rdv'][2]) ?></span></p>
                    <button class="c-btn c-btn-light" type="button" data-g-demo="deplacer" data-g-message="Choisissez un autre créneau : votre conseiller est prévenu.">Déplacer</button>
                </section>
                <section class="c-card">
                    <h2 class="c-small-title">Votre interlocuteur</h2>
                    <p class="c-muted" style="margin:.2rem 0 .8rem"><?= e($interlocuteur) ?> répond en général sous 24 h ouvrées.</p>
                    <div class="c-actions">
                    <button class="c-btn" type="button" data-g-demo="ecrire" data-g-message="Message envoyé. Vous recevrez la réponse ici et par e-mail."><?= gestion_icone('chat', 17) ?> Écrire</button>
                    <button class="c-btn c-btn-light c-ask" type="button" data-c-assistant>Poser une question à l’assistant</button>
                    </div>
                </section>
            </aside>
        </div>
    </div>
    <div class="g-toasts" aria-live="polite" data-g-toasts></div>
</main>
<script>
// Sur téléphone et tablette, l'assistant ne flotte pas au-dessus des actions : on l'ouvre depuis la carte « Votre interlocuteur »
document.addEventListener('DOMContentLoaded', () => {
    // L'assistant est rendu par le gabarit après cette vue : on attend la page complète
    const ask = document.querySelector('[data-c-assistant]');
    const launcher = document.querySelector('[data-assistant-open]');
    if (!launcher) { ask?.remove(); return; } // assistant désactivé
    ask?.addEventListener('click', () => launcher.click());
});
</script>
