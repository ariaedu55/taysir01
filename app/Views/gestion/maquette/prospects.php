<?php
/**
 * Prospects (agent de prospection, commerciale) : sélection multiple et attribution en lot (tâche T4). Données fictives.
 *
 * @var string $poste
 * @var array  $prospects
 */
$attribuables = $poste !== 'commerciale';
?>
<div class="g-toolbar">
    <div class="g-views" role="group" aria-label="Vues enregistrées" data-g-prospect-views>
        <button type="button" aria-pressed="true" data-view="tous">Tous<span><?= count($prospects) ?></span></button>
        <button type="button" aria-pressed="false" data-view="libres">Non attribués<span><?= count(array_filter($prospects, static fn (array $p): bool => $p[5] === null)) ?></span></button>
    </div>
    <label class="g-field-search"><?= gestion_icone('search', 17) ?><span class="sr-only">Filtrer la liste</span><input type="search" placeholder="Nom, établissement, téléphone…" data-g-filter-prospects></label>
    <select class="g-select" aria-label="Campagne"><option>Campagne : Rentrée 2027</option><option>Salon de l’étudiant</option></select>
    <?php if ($attribuables): ?><button class="g-btn" type="button" data-g-soon><?= gestion_icone('upload', 17) ?> Importer (Excel)</button><?php endif; ?>
</div>

<div class="g-table-wrap">
    <table class="g-table" id="table-prospects"<?= $attribuables ? ' data-g-selectable="#bulk-prospects"' : '' ?>>
        <thead>
            <tr>
                <?php if ($attribuables): ?><th class="g-check"><input type="checkbox" data-g-select-all aria-label="Tout sélectionner"></th><?php endif; ?>
                <th>Prospect</th><th>Établissement</th><th>Téléphone</th><th>Campagne</th><th>Commerciale</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prospects as [$nom, $etab, $niveau, $tel, $campagne, $commerciale]): ?>
                <tr<?= $commerciale ? '' : ' data-g-libre' ?>>
                    <?php if ($attribuables): ?><td class="g-check"><input type="checkbox" data-g-select aria-label="Sélectionner <?= e($nom) ?>"></td><?php endif; ?>
                    <td class="g-cell-main"><strong><?= e($nom) ?></strong><span><?= e($niveau) ?></span></td>
                    <td data-label="Établissement"><?= e($etab) ?></td>
                    <td data-label="Téléphone"><a href="tel:+221<?= e(str_replace(' ', '', $tel)) ?>" style="color:inherit"><?= e($tel) ?></a></td>
                    <td class="g-mobile-hide"><?= e($campagne) ?></td>
                    <td data-label="Commerciale" data-g-owner><?= $commerciale ? e($commerciale) : '<span class="g-badge is-alerte"><span class="g-dot is-alerte"></span>Non attribué</span>' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="g-empty" hidden data-g-empty>Aucun prospect dans cette vue. Tout est attribué, ou la recherche ne trouve personne.</p>
</div>

<?php if ($attribuables): ?>
    <div class="g-bulk" id="bulk-prospects" hidden>
        <span><strong data-g-selected-count>0</strong> sélectionné(s)</span>
        <label class="sr-only" for="qui-commerciale">Commerciale</label>
        <select class="g-select" id="qui-commerciale"><option>Khady Diouf</option><option>Awa Niang</option></select>
        <button class="g-btn g-btn-sm" type="button" data-g-demo="attribuer" data-g-table="#table-prospects" data-g-who="#qui-commerciale">Attribuer</button>
    </div>
<?php endif; ?>

<script>
(() => {
    const input = document.querySelector('[data-g-filter-prospects]');
    const rows = [...document.querySelectorAll('#table-prospects tbody tr')];
    const n = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, '');
    const views = [...document.querySelectorAll('[data-g-prospect-views] button')];
    let view = 'tous';
    const apply = () => {
        const q = n(input.value);
        rows.forEach((row) => {
            const libre = row.hasAttribute('data-g-libre') && row.querySelector('[data-g-owner] .g-badge');
            row.hidden = (q !== '' && !n(row.textContent).includes(q)) || (view === 'libres' && !libre);
            // Une ligne masquée ne reste pas sélectionnée
            const box = row.querySelector('[data-g-select]');
            if (row.hidden && box) { box.checked = false; }
        });
        document.querySelector('[data-g-empty]').hidden = rows.some((row) => !row.hidden);
        document.querySelector('#table-prospects').gestionSelection?.update();
    };
    views.forEach((b) => b.addEventListener('click', () => {
        view = b.dataset.view;
        views.forEach((o) => o.setAttribute('aria-pressed', String(o === b)));
        apply();
    }));
    input.addEventListener('input', apply);
    // Après une attribution : compteur « Non attribués » et vue mis à jour
    document.querySelector('#table-prospects')?.addEventListener('gestion:attribue', () => {
        const libres = rows.filter((row) => row.querySelector('[data-g-owner] .g-badge')).length;
        const span = document.querySelector('[data-view="libres"] span');
        if (span) { span.textContent = libres; }
        apply();
    });
})();
</script>
