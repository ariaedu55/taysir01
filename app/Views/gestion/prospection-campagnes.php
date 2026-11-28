<?php
$root = '/gestion/prospects/';
$active = static fn (array $campaign): bool => $campaign['starts_on'] <= date('Y-m-d') && (!$campaign['ends_on'] || $campaign['ends_on'] >= date('Y-m-d'));
?>
<div class="g-toolbar"><a class="g-btn" href="<?= e(url($root)) ?>">Retour aux prospects</a></div>
<?php if ($error = errors()['prospection'] ?? null): ?><p class="g-field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="g-grid g-grid-2">
    <section class="g-card g-grid" aria-labelledby="campaigns-title">
        <div class="g-card-head"><h2 id="campaigns-title"><?= gestion_icone('target', 18) ?> Campagnes</h2></div>
        <?php if (!$campaigns): ?><p class="g-empty">Aucune campagne dans votre agence.</p><?php else: ?>
            <ul class="g-tasks"><?php foreach ($campaigns as $entry): ?>
                <li class="g-task">
                    <span class="g-doc-icon"><?= gestion_icone('target', 17) ?></span>
                    <div><strong><?= e($entry['name']) ?></strong><p><?= e($entry['agency'] . ' · ' . g_date($entry['starts_on'])) ?><?= $entry['ends_on'] ? ' au ' . e(g_date($entry['ends_on'])) : '' ?></p>
                        <p><?= (int) $entry['collected'] ?> prospects · <?= (int) $entry['schools'] ?> établissements avec prospects</p></div>
                    <a class="g-btn g-btn-sm" href="<?= e(g_filtre($root, ['campagne' => $entry['id']])) ?>"><?= $active($entry) ? 'En cours' : 'Voir' ?></a>
                </li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </section>
    <section class="g-card g-grid" aria-labelledby="schools-title">
        <div class="g-card-head"><h2 id="schools-title"><?= gestion_icone('folder', 18) ?> Établissements</h2></div>
        <?php if (!$schools): ?><p class="g-empty">Aucun établissement dans votre agence.</p><?php else: ?>
            <ul class="g-tasks"><?php foreach ($schools as $entry): ?>
                <li class="g-task"><span class="g-doc-icon"><?= gestion_icone('folder', 17) ?></span><div><strong><?= e($entry['name']) ?></strong><p><?= e($entry['city'] . ' · ' . $entry['agency']) ?></p></div></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </section>
</div>
<?php if ($manage && $agencies): ?>
    <div class="g-grid g-grid-2 g-prospect-add">
        <?php foreach (['school' => 'Ajouter un établissement', 'campaign' => 'Créer une campagne'] as $kind => $label): ?>
            <form class="g-card" method="post" action="<?= e(url($root . 'campagnes/')) ?>">
                <div class="g-card-head"><h2><?= e($label) ?></h2></div>
                <?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($kind) ?>">
                <label class="g-form-row">Agence <select class="g-select" name="agency" required>
                    <?php foreach ($agencies as $agency): ?><option value="<?= (int) $agency['id'] ?>"<?= (int) $agency['id'] === (int) $me['agency_id'] ? ' selected' : '' ?>><?= e($agency['name']) ?></option><?php endforeach; ?>
                </select></label>
                <label class="g-form-row">Nom <input class="g-input" name="name" maxlength="150" required></label>
                <?php if ($kind === 'school'): ?>
                    <label class="g-form-row">Ville <input class="g-input" name="city" maxlength="100" required></label>
                <?php else: ?>
                    <label class="g-form-row">Date de début <input class="g-input" type="date" name="start" required></label>
                    <label class="g-form-row">Date de fin (facultative) <input class="g-input" type="date" name="end"></label>
                <?php endif; ?>
                <button class="g-btn g-btn-success" type="submit">Enregistrer</button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
