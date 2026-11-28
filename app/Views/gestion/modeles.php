<?php
/**
 * Démarches et pièces (/gestion/modeles/), administrateur (phase 12 ; versions, vérification et publication de la 2C).
 * Une ligne par démarche (sa version la plus récente) : portée (pays · motif), état, pièces, vérification.
 *
 * @var list<array>           $modeles   Toutes les versions, la plus récente d'abord pour chaque clé
 * @var array<string, string> $services
 * @var int                   $fraicheur Mois
 * @var list<array>           $pays      Pays couverts (actifs et retirés)
 */
use App\Models\ProcedureTemplate;
use App\Services\Demarches;

$errors = errors();
// Par service : une démarche (clé) = sa version la plus récente, et sa version publiée s'il y en a une
$parService = [];
foreach ($modeles as $m) {
    $cle = $m['template_key'];
    $parService[$m['service']][$cle]['derniere'] ??= $m;
    if ($m['status'] === 'publie') {
        $parService[$m['service']][$cle]['publiee'] = $m;
    }
}
$portee = static function (array $m): string {
    $motifs = Demarches::motifs($m['service']);

    return ($m['destination'] ?: 'Tous les pays') . ' · ' . ($m['purpose'] ? ($motifs[$m['purpose']] ?? $m['purpose']) : ($motifs !== [] ? 'tous les motifs' : 'sans motif'));
};
?>
<div class="g-toolbar">
    <p class="g-muted g-toolbar-count">Une démarche fixe les pièces et les étapes d’un dossier selon le service, le pays et le motif. La plus précise publiée s’applique ; sinon le modèle général du service. Une vérification de plus de <?= (int) $fraicheur ?> mois est signalée.</p>
    <a class="g-btn g-btn-primary g-toolbar-end" href="<?= e(url('/gestion/modeles/nouveau/')) ?>"><?= gestion_icone('plus', 16) ?> Nouvelle démarche</a>
</div>

<div class="g-grid">
    <?php foreach ($services as $cleService => $titreService): ?>
        <?php $demarches = $parService[$cleService] ?? []; ?>
        <?php uasort($demarches, static fn (array $a, array $b): int => [(int) ($a['derniere']['destination'] !== null), $a['derniere']['destination'], (int) ($a['derniere']['purpose'] !== null)]
            <=> [(int) ($b['derniere']['destination'] !== null), $b['derniere']['destination'], (int) ($b['derniere']['purpose'] !== null)]); ?>
        <section class="g-card is-neutre" aria-labelledby="service-<?= e($cleService) ?>">
            <div class="g-card-head">
                <h3 id="service-<?= e($cleService) ?>"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span><?= e($titreService) ?> <span class="g-count"><?= count($demarches) ?></span></h3>
                <a class="g-btn g-btn-sm" href="<?= e(url('/gestion/modeles/nouveau/?service=' . rawurlencode($cleService))) ?>"><?= gestion_icone('plus', 15) ?> Ajouter</a>
            </div>
            <?php if ($demarches === []): ?>
                <p class="g-empty">Aucune démarche : les dossiers de ce service s’ouvrent sans pièces ; le conseiller les demande une par une.</p>
            <?php else: ?>
                <ul class="g-demarches">
                    <?php foreach ($demarches as $cle => $d): ?>
                        <?php $m = $d['derniere']; $publiee = $d['publiee'] ?? null; ?>
                        <li class="g-demarche">
                            <a href="<?= e(url('/gestion/modeles/' . $m['id'] . '/')) ?>">
                                <strong><?= e($m['title']) ?></strong>
                                <small><?= e($portee($m)) ?> · <?= count(Demarches::pieces($m)) ?> pièce<?= count(Demarches::pieces($m)) > 1 ? 's' : '' ?> · <?= count($m['steps']) ?> étape<?= count($m['steps']) > 1 ? 's' : '' ?> · v<?= (int) $m['version'] ?>
                                    · <?= $m['verified_at'] ? 'vérifiée le ' . e(g_date($m['verified_at'])) : 'jamais vérifiée' ?></small>
                            </a>
                            <span class="g-demarche-etat">
                                <?php if ($publiee !== null): ?>
                                    <?= g_badge($publiee['id'] === $m['id'] ? 'Publiée' : 'v' . (int) $publiee['version'] . ' publiée', 'green') ?>
                                    <?php if (ProcedureTemplate::aRevoir($publiee)): ?><?= g_badge('À revérifier', 'amber') ?><?php endif; ?>
                                <?php endif; ?>
                                <?php if ($publiee === null || $publiee['id'] !== $m['id']): ?><?= g_badge(ProcedureTemplate::STATUTS[$m['status']][0], ProcedureTemplate::STATUTS[$m['status']][1]) ?><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="g-card is-rdv" aria-labelledby="pays-titre" id="pays">
        <div class="g-card-head"><h3 id="pays-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('flag', 18) ?></span>Pays couverts</h3></div>
        <p class="g-muted g-form-note">Proposés dans les formulaires du site et à l’ouverture d’un dossier, avec « Autre pays » en plus. Un pays retiré reste sur les dossiers qui le citent.</p>
        <ul class="g-demarches">
            <?php foreach ($pays as $p): ?>
                <li class="g-demarche">
                    <span><strong><?= e($p['name']) ?></strong><?php if (!$p['active']): ?> <?= g_badge('Retiré', 'grey') ?><?php endif; ?></span>
                    <form method="post" action="<?= e(url('/gestion/modeles/pays/')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <?php if ($p['active']): ?>
                            <button class="g-btn g-btn-sm g-btn-warning" name="action" value="desactiver" type="submit">Retirer</button>
                        <?php else: ?>
                            <button class="g-btn g-btn-sm" name="action" value="reactiver" type="submit">Réactiver</button>
                        <?php endif; ?>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form class="g-pays-form" method="post" action="<?= e(url('/gestion/modeles/pays/')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="ajouter">
            <label class="g-form-row">Ajouter un pays <input class="g-input" name="name" maxlength="120" placeholder="Allemagne"></label>
            <button class="g-btn g-btn-success" type="submit"><?= gestion_icone('check', 16) ?> Ajouter</button>
        </form>
        <?php if (isset($errors['pays'])): ?><p class="g-field-error"><?= e($errors['pays']) ?></p><?php endif; ?>
    </section>
</div>
