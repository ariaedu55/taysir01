<?php
/**
 * Durées de conservation (/gestion/conservation/), administrateur (phase 7, lot 7C).
 * Liste seulement : chaque fiche s'anonymise depuis sa page « Données personnelles », après décision de l'agence.
 *
 * @var array{prospects: list<array>, dossiers: list<array>, pieces: list<array>} $listes
 * @var array<string, int> $durees
 */
$blocs = [
    'prospects' => ['Prospects sans dossier, inactifs depuis ' . $durees['prospect'] . ' ans', 'target'],
    'dossiers' => ['Personnes aux dossiers clos depuis ' . $durees['dossier'] . ' ans', 'folder'],
    'pieces' => ['Pièces de dossiers clos depuis ' . $durees['pieces'] . ' an', 'lock'],
];
?>
<p class="g-notice"><?= gestion_icone('alert', 16) ?> Durées proposées, à valider par l’agence : prospect inactif <?= (int) $durees['prospect'] ?> ans, dossier clos <?= (int) $durees['dossier'] ?> ans, pièces d’identité <?= (int) $durees['pieces'] ?> an, comptabilité <?= (int) $durees['comptabilite'] ?> ans (les règlements ne sont jamais anonymisés). Rien n’est supprimé automatiquement.</p>

<div class="g-grid g-grid-2">
    <?php foreach ($blocs as $cle => [$titre, $icone]): ?>
        <section class="g-card is-neutre" aria-labelledby="cons-<?= e($cle) ?>">
            <div class="g-card-head"><h3 id="cons-<?= e($cle) ?>"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone($icone, 18) ?></span><?= e($titre) ?> <span class="g-count"><?= count($listes[$cle]) ?></span></h3></div>
            <?php if ($listes[$cle] === []): ?><p class="g-empty">Aucune fiche concernée.</p><?php else: ?>
                <ul class="g-etapes">
                    <?php foreach ($listes[$cle] as $l): ?>
                        <?php $id = (int) ($l['person_id'] ?? $l['id']); ?>
                        <li class="g-etape"><span><?= isset($l['nombre']) ? g_badge((int) $l['nombre'] . ' pièce' . ($l['nombre'] > 1 ? 's' : ''), 'grey') : '' ?></span>
                            <div><a href="<?= e(url('/gestion/personnes/' . $id . '/donnees/')) ?>"><strong><?= e($l['first_name'] . ' ' . $l['last_name']) ?></strong></a>
                                <small><?= isset($l['reference']) ? e($l['reference']) . ' · ' : '' ?>dernière activité <?= e(g_date($l['derniere'])) ?></small></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
