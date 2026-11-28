<?php
/**
 * Modèles de procédure (/gestion/modeles/), administrateur (2C, E8, E9, N5). Toutes les versions, groupées par
 * modèle ; alerte de fraîcheur sur la version publiée.
 *
 * @var list<array>           $modeles
 * @var array<string, string> $services
 * @var int                   $fraicheur Mois
 */
use App\Models\ProcedureTemplate;

$parCle = [];
foreach ($modeles as $m) {
    $parCle[$m['template_key']][] = $m;
}
?>
<div class="g-toolbar">
    <p class="g-muted g-toolbar-count">Un dossier garde la version de son ouverture. Seule une version publiée est proposée ; une vérification de plus de <?= (int) $fraicheur ?> mois est signalée.</p>
    <a class="g-btn g-btn-primary g-toolbar-end" href="<?= e(url('/gestion/modeles/nouveau/')) ?>"><?= gestion_icone('plus', 16) ?> Nouveau modèle</a>
</div>

<?php if ($parCle === []): ?>
    <p class="g-empty">Aucun modèle.</p>
<?php endif; ?>
<div class="g-grid g-grid-2">
    <?php foreach ($parCle as $cle => $versions): ?>
        <?php $courant = $versions[0]; ?>
        <section class="g-card is-<?= $courant['status'] === 'publie' ? 'ok' : 'neutre' ?>" aria-labelledby="m-<?= e($cle) ?>">
            <div class="g-card-head"><h3 id="m-<?= e($cle) ?>"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span><?= e($courant['title']) ?></h3></div>
            <p class="g-muted g-form-note"><?= e($services[$courant['service']] ?? $courant['service']) ?><?= $courant['destination'] ? ' · ' . e($courant['destination']) : '' ?> · clé <?= e($cle) ?></p>
            <ul class="g-etapes">
                <?php foreach ($versions as $v): ?>
                    <li class="g-etape">
                        <span><?= g_badge('v' . (int) $v['version'], 'grey') ?></span>
                        <div>
                            <strong><a href="<?= e(url('/gestion/modeles/' . $v['id'] . '/')) ?>"><?= e(ProcedureTemplate::STATUTS[$v['status']][0]) ?></a>
                                <?php if ($v['status'] === 'publie' && ProcedureTemplate::aRevoir($v)): ?><?= g_badge('À revérifier', 'amber') ?><?php endif; ?></strong>
                            <small><?= count($v['steps']) ?> étapes · <?= $v['verified_at'] ? 'vérifié le ' . e(g_date($v['verified_at'])) . ($v['verified_first_name'] ? ' par ' . e($v['verified_first_name'] . ' ' . $v['verified_last_name']) : '') : 'jamais vérifié' ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
