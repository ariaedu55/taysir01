<?php
use App\Models\Prospect;

$root = '/gestion/prospects/';
$filters = ['q' => $query ?: null, 'vue' => $filter ?: null, 'campagne' => $campaign ?: null, 'etablissement' => $school ?: null, 'niveau' => $level ?: null];
$pages = max(1, (int) ceil($total / Prospect::PAGE));
$selectable = $manage && $assignees !== [];
$indicators = [
    ['Prospects', $stats['total'], 'rdv', 'users', null],
    $me['role'] === 'commerciale' ? ['Relances à faire', $stats['relances'], 'ech', 'clock', 'relances'] : ['Non attribués', $stats['libres'], 'ech', 'queue', 'libres'],
    ['Transmis à l’accueil', $stats['transmis'], 'ok', 'check', 'transmis'],
];
?>
<div class="g-kpis" aria-label="Indicateurs de prospection">
    <?php foreach ($indicators as [$label, $value, $tone, $icon, $view]): ?>
        <a class="g-kpi g-prospect-kpi is-<?= e($tone) ?>" href="<?= e(g_filtre($root, ['vue' => $view])) ?>">
            <span class="g-kpi-icon"><?= gestion_icone($icon) ?></span><strong><?= (int) $value ?></strong><span class="g-kpi-label"><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php if ($error = errors()['prospection'] ?? null): ?><p class="g-field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="g-toolbar g-prospect-actions">
    <?php if ($schools && $campaigns): ?><a class="g-btn g-btn-primary" href="#nouveau-prospect"><?= gestion_icone('plus', 16) ?> Ajouter un prospect</a><?php endif; ?>
    <?php if ($manage): ?><a class="g-btn" href="<?= e(url($root . 'importer/')) ?>"><?= gestion_icone('upload', 16) ?> Importer</a><?php endif; ?>
    <a class="g-btn" href="<?= e(url($root . 'campagnes/')) ?>"><?= gestion_icone('folder', 16) ?> Établissements et campagnes</a>
</div>
<form class="g-toolbar" method="get" action="<?= e(url($root)) ?>" role="search">
    <label class="g-search-field"><?= gestion_icone('search', 17) ?><span class="sr-only">Rechercher un prospect</span>
        <input class="g-input" type="search" name="q" maxlength="80" value="<?= e($query) ?>" placeholder="Nom, téléphone ou e-mail">
    </label>
    <select class="g-select" name="campagne" aria-label="Campagne"><option value="">Toutes les campagnes</option>
        <?php foreach ($campaigns as $entry): ?><option value="<?= (int) $entry['id'] ?>"<?= $campaign === (int) $entry['id'] ? ' selected' : '' ?>><?= e($entry['name'] . ' · ' . $entry['agency']) ?></option><?php endforeach; ?>
    </select>
    <select class="g-select" name="vue" aria-label="État du suivi"><option value="">Tous les états</option>
        <?php foreach (['libres' => 'Non attribués', 'relances' => 'Relances à faire', ...Prospect::STATES] as $key => $label): ?><option value="<?= e($key) ?>"<?= $filter === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <select class="g-select" name="etablissement" aria-label="Établissement"><option value="">Tous les établissements</option>
        <?php foreach ($schools as $entry): ?><option value="<?= (int) $entry['id'] ?>"<?= $school === (int) $entry['id'] ? ' selected' : '' ?>><?= e($entry['name'] . ' · ' . $entry['city']) ?></option><?php endforeach; ?>
    </select>
    <label><span class="sr-only">Niveau scolaire</span><input class="g-input" name="niveau" maxlength="80" placeholder="Niveau scolaire" value="<?= e($level) ?>"></label>
    <button class="g-btn g-btn-primary" type="submit">Filtrer</button>
    <?php if (array_filter($filters)): ?><a class="g-btn" href="<?= e(url($root)) ?>">Effacer les filtres</a><?php endif; ?>
</form>
<form method="post" action="<?= e(url($root . 'attribuer/')) ?>">
    <?php if ($selectable): ?><?= csrf_field() ?><?php endif; ?>
    <div class="g-table-wrap">
        <?php if (!$rows): ?><p class="g-empty">Aucun prospect avec ces filtres.</p><?php else: ?>
        <table class="g-table g-prospect-table"<?= $selectable ? ' data-g-selectable="#affectation-prospects"' : '' ?>>
            <thead><tr><?php if ($selectable): ?><th class="g-check"><input type="checkbox" data-g-select-all aria-label="Sélectionner les prospects de cette page"></th><?php endif; ?>
                <th>Prospect</th><th>Établissement</th><th>Campagne</th><th>Suivi</th><th>Commerciale</th></tr></thead>
            <tbody><?php foreach ($rows as $row): $name = $row['first_name'] . ' ' . $row['last_name']; ?>
                <tr>
                    <?php if ($selectable): ?><td class="g-check"><?php if ($row['state'] !== 'transmis'): ?><input type="checkbox" name="selection[]" value="<?= (int) $row['id'] ?>" data-g-select aria-label="Sélectionner <?= e($name) ?>"><?php endif; ?></td><?php endif; ?>
                    <td class="g-cell-main"><a class="g-row-link" href="<?= e(url($root . $row['id'] . '/')) ?>"><strong><?= e($name) ?></strong></a>
                        <?php if ($row['phone']): ?><span><a href="tel:<?= e($row['phone']) ?>"><?= e($row['phone']) ?></a></span><?php endif; ?></td>
                    <td data-label="Établissement"><?= e($row['school'] . ' · ' . $row['level']) ?></td>
                    <td data-label="Campagne"><?= e($row['campaign'] . ' · ' . $row['agency']) ?></td>
                    <td data-label="Suivi"><?= g_badge(Prospect::STATES[$row['state']], match ($row['state']) {'transmis', 'interesse' => 'ok', 'a-rappeler' => 'ech', default => 'neutre'}) ?>
                        <?php if ($row['follow_up_on']): ?><small><?= e(g_date($row['follow_up_on'])) ?></small><?php endif; ?></td>
                    <td data-label="Commerciale"><?= e($row['assignee'] ?? 'Non attribué') ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php if ($selectable && $rows): ?>
        <div class="g-bulk g-prospect-bulk" id="affectation-prospects">
            <span><strong data-g-selected-count>0</strong> sélectionné(s)</span>
            <select class="g-select" name="assignee" aria-label="Commerciale à attribuer" required><option value="">Commerciale</option>
                <?php foreach ($assignees as $assignee): ?><option value="<?= (int) $assignee['id'] ?>"><?= e($assignee['first_name'] . ' ' . $assignee['last_name']) ?></option><?php endforeach; ?></select>
            <button class="g-btn g-btn-primary" type="submit">Attribuer</button>
        </div>
    <?php endif; ?>
</form>
<?php if ($pages > 1): ?>
    <nav class="g-pager" aria-label="Pages des prospects">
        <?php if ($page > 1): ?><a class="g-btn" href="<?= e(g_filtre($root, $filters + ['page' => $page - 1])) ?>">Précédente</a><?php endif; ?>
        <span class="g-muted">Page <?= $page ?> sur <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="g-btn" href="<?= e(g_filtre($root, $filters + ['page' => $page + 1])) ?>">Suivante</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?php if ($schools && $campaigns): ?>
    <section class="g-card g-prospect-add" id="nouveau-prospect" aria-labelledby="prospect-new-title">
        <div class="g-card-head"><h2 id="prospect-new-title">Ajouter un prospect</h2></div>
        <form method="post" action="<?= e(url($root)) ?>">
            <?= csrf_field() ?>
            <div class="g-prospect-fields">
                <label class="g-form-row">Prénom <input class="g-input" name="first_name" maxlength="80" required value="<?= e(old('first_name')) ?>"></label>
                <label class="g-form-row">Nom <input class="g-input" name="last_name" maxlength="80" required value="<?= e(old('last_name')) ?>"></label>
                <label class="g-form-row">Téléphone <input class="g-input" name="phone" type="tel" maxlength="30" value="<?= e(old('phone')) ?>"></label>
                <label class="g-form-row">E-mail <input class="g-input" name="email" type="email" maxlength="190" value="<?= e(old('email')) ?>"></label>
                <?php partial('prospect-context', ['schools' => $schools, 'campaigns' => $campaigns]); ?>
                <label class="g-form-row">Niveau <input class="g-input" name="level" maxlength="80" required value="<?= e(old('level')) ?>"></label>
            </div>
            <p class="g-muted">Renseignez au moins le téléphone ou l’e-mail.</p>
            <button class="g-btn g-btn-success" type="submit">Enregistrer le prospect</button>
        </form>
    </section>
<?php endif; ?>
