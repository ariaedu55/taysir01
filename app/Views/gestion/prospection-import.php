<?php
$root = '/gestion/prospects/';
$excluded = $preview['duplicates'] ?? $report['duplicates'] ?? [];
$invalid = $preview['invalid'] ?? $report['invalid'] ?? [];
$eligible = $preview ? count($preview['rows']) - count($excluded) : (int) ($report['added'] ?? 0);
?>
<div class="g-toolbar"><a class="g-btn" href="<?= e(url($root)) ?>">Retour aux prospects</a></div>
<?php if ($error = errors()['prospection'] ?? null): ?><p class="g-field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($preview || $report): ?>
    <section class="g-card g-grid" aria-labelledby="import-report-title">
        <div class="g-card-head"><h2 id="import-report-title"><?= $preview ? 'Aperçu avant import' : 'Rapport d’import' ?></h2></div>
        <div class="g-kpis">
            <?php foreach ([[$eligible, $preview ? 'Prêts à importer' : 'Importés', 'ok', 'check'], [count($excluded), 'Doublons', 'ech', 'swap'], [count($invalid), 'Lignes à corriger', 'urg', 'alert']] as [$number, $label, $tone, $icon]): ?>
                <div class="g-kpi is-<?= e($tone) ?>"><span class="g-kpi-icon"><?= gestion_icone($icon) ?></span><strong><?= $number ?></strong><span class="g-kpi-label"><?= e($label) ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php if ($excluded): ?><p>Doublons ignorés aux lignes <?= e(implode(', ', $excluded)) ?>.</p><?php endif; ?>
        <?php if ($invalid): ?><ul><?php foreach ($invalid as $line => $issue): ?><li>Ligne <?= (int) $line ?> · <?= e($issue) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <?php if ($preview && $eligible > 0): ?>
            <ul class="g-tasks"><?php foreach (array_slice(array_diff_key($preview['rows'], array_flip($excluded)), 0, 6, true) as $line => $entry): ?>
                <li class="g-task"><span class="g-doc-icon"><?= gestion_icone('users', 16) ?></span><div><strong><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></strong><p>Ligne <?= (int) $line ?> · <?= e($entry['level']) ?></p></div></li>
            <?php endforeach; ?></ul>
            <form method="post" action="<?= e(url($root . 'importer/confirmer/')) ?>">
                <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($preview['token']) ?>">
                <button class="g-btn g-btn-success" type="submit">Confirmer l’import</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php if ($recent): ?>
    <section class="g-card g-prospect-add" aria-labelledby="recent-imports">
        <div class="g-card-head"><h2 id="recent-imports">Imports récents</h2></div>
        <ul class="g-tasks"><?php foreach ($recent as $entry): $detail = json_decode($entry['detail'], true); ?>
            <li class="g-task"><span class="g-doc-icon"><?= gestion_icone('upload', 16) ?></span><div>
                <strong><?= e($entry['name'] ?? 'Campagne') ?> · <?= e(g_date($entry['created_at'])) ?></strong>
                <p><?= (int) ($detail['ajoutes'] ?? 0) ?> importé(s) · <?= (int) ($detail['doublons'] ?? 0) ?> doublon(s)</p>
            </div></li>
        <?php endforeach; ?></ul>
    </section>
<?php endif; ?>
<?php if (!$schools || !$campaigns): ?>
    <p class="g-empty">Créez d’abord un établissement et une campagne de la même agence. <a href="<?= e(url($root . 'campagnes/')) ?>">Ouvrir les campagnes</a></p>
<?php else: ?>
    <form class="g-card g-prospect-add" method="post" enctype="multipart/form-data" action="<?= e(url($root . 'importer/')) ?>">
        <div class="g-card-head"><h2><?= $preview || $report ? 'Autre fichier' : 'Fichier Excel ou CSV' ?></h2></div>
        <?= csrf_field() ?>
        <div class="g-prospect-fields"><?php partial('prospect-context', ['schools' => $schools, 'campaigns' => $campaigns]); ?></div>
        <label class="g-form-row">Fichier <input class="g-input" name="list" type="file" accept=".xlsx,.csv" aria-describedby="import-help" required></label>
        <p class="g-muted" id="import-help">Première feuille XLSX ou CSV UTF-8, 500 lignes et 2 Mo maximum. En-tête attendu : Prénom, Nom, Niveau, Téléphone ou E-mail. Formatez les téléphones en texte dans Excel pour conserver les zéros et le signe +.</p>
        <button class="g-btn g-btn-primary" type="submit">Vérifier le fichier</button>
    </form>
<?php endif; ?>
