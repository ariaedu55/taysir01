<?php
/**
 * Liste type : dossiers (docs/gestion-plan.md §12.3). Vues enregistrées, recherche, filtres, tri, sélection multiple,
 * panneau latéral d'aperçu. Données fictives.
 *
 * @var string $poste
 * @var array  $dossiers, $statuts, $etapes, $equipe
 */
$base = '/gestion/maquette';
$nomPoste = static fn (string $p): string => $equipe[$p][0] . ' ' . $equipe[$p][1];
$mine = in_array($poste, ['admin', 'secretaire', 'comptable'], true) ? null : $poste;
$compte = ['tous' => count($dossiers), 'mes' => 0, 'pieces' => 0, 'retard' => 0];
foreach ($dossiers as $d) {
    $compte['mes'] += $mine === null || $d[9] === $mine ? 1 : 0;
    $compte['pieces'] += $d[7] === 'piece' ? 1 : 0;
    $compte['retard'] += $d[7] === 'retard' || ($d[8] !== null && $d[8] < 0) ? 1 : 0;
}
?>
<div class="g-toolbar">
    <div class="g-views" role="group" aria-label="Vues enregistrées" data-g-views>
        <button type="button" aria-pressed="<?= $mine ? 'false' : 'true' ?>" data-view="tous">Tous<span><?= $compte['tous'] ?></span></button>
        <button type="button" aria-pressed="<?= $mine ? 'true' : 'false' ?>" data-view="mes">Mes dossiers<span><?= $compte['mes'] ?></span></button>
        <button type="button" aria-pressed="false" data-view="pieces">Pièces à valider<span><?= $compte['pieces'] ?></span></button>
        <button type="button" aria-pressed="false" data-view="retard">En retard<span><?= $compte['retard'] ?></span></button>
    </div>
    <label class="g-field-search"><?= gestion_icone('search', 17) ?><span class="sr-only">Filtrer la liste</span><input type="search" placeholder="Nom, référence, destination…" data-g-filter></label>
    <select class="g-select" aria-label="Parcours" data-g-parcours>
        <option value="">Tous les parcours</option><option value="etudes">Études</option><option value="voyage">Voyage</option>
    </select>
    <button class="g-btn" type="button" data-g-soon><?= gestion_icone('download', 17) ?> Exporter</button>
</div>

<div class="g-table-wrap">
    <table class="g-table" data-g-selectable="#bulk-dossiers" id="table-dossiers">
        <thead>
            <tr>
                <th class="g-check"><input type="checkbox" data-g-select-all aria-label="Tout sélectionner"></th>
                <th>Personne et dossier</th>
                <th>Destination</th>
                <th>Avancement</th>
                <th>Statut</th>
                <th>Échéance</th>
                <th>Responsable</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dossiers as [$ref, $nom, $parcours, $service, $destination, $rentree, $etape, $statut, $echeance, $responsable]): ?>
                <?php
                [$libelle, $ton] = $statuts[$statut];
                $total = count($etapes[$parcours]);
                $late = $echeance !== null && $echeance < 0;
                $echeanceTexte = $echeance === null ? '—' : ($late ? 'il y a ' . abs($echeance) . ' j' : ($echeance === 0 ? 'aujourd’hui' : 'dans ' . $echeance . ' j'));
                ?>
                <tr tabindex="0" data-ref="<?= e($ref) ?>" data-href="<?= e(url($base . '/dossiers/' . $ref . '/')) ?>" data-statut="<?= e($statut) ?>" data-parcours="<?= e($parcours) ?>"
                    data-mine="<?= $mine === null || $responsable === $mine ? '1' : '0' ?>" data-late="<?= $late || $statut === 'retard' ? '1' : '0' ?>"
                    data-nom="<?= e($nom) ?>" data-service="<?= e($service) ?>" data-destination="<?= e($destination) ?>" data-rentree="<?= e($rentree) ?>"
                    data-etape="<?= e($etapes[$parcours][$etape]) ?>" data-libelle="<?= e($libelle) ?>" data-ton="<?= e($ton) ?>" data-responsable="<?= e($nomPoste($responsable)) ?>" data-echeance="<?= e($echeanceTexte) ?>">
                    <td class="g-check"><input type="checkbox" data-g-select aria-label="Sélectionner <?= e($nom) ?>"></td>
                    <td class="g-cell-main"><strong><?= e($nom) ?></strong><span><?= e($ref) ?> · <?= e($service) ?></span></td>
                    <td data-label="Destination"><?= e($destination) ?><?= $rentree !== '—' ? ' · ' . e($rentree) : '' ?></td>
                    <td class="g-mobile-hide"><div class="g-progress"><span class="g-progress-bar"><i style="width: <?= round(($etape + 1) / $total * 100) ?>%"></i></span><small><?= e($etapes[$parcours][$etape]) ?></small></div></td>
                    <td><span class="g-badge is-<?= e($ton) ?>"><span class="g-dot is-<?= e($ton) ?>"></span><?= e($libelle) ?></span></td>
                    <td data-label="Échéance"><span class="g-due<?= $late ? ' is-late' : '' ?>"><?= e($echeanceTexte) ?></span></td>
                    <td class="g-mobile-hide"><?= e($nomPoste($responsable)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="g-empty" hidden data-g-empty>Aucun dossier ne correspond. Essayez une autre vue ou effacez la recherche.</p>
</div>

<div class="g-bulk" id="bulk-dossiers" hidden>
    <span><strong data-g-selected-count>0</strong> sélectionné(s)</span>
    <select class="g-select" aria-label="Affecter à"><option>Affecter à…</option><option>Cheikh Gueye</option><option>Moussa Diop</option><option>Fatou Ba</option></select>
    <button class="g-btn g-btn-sm" type="button" data-g-demo="lot" data-g-message="Affectation enregistrée et notifiée aux personnes concernées.">Appliquer</button>
</div>

<div class="g-panel-scrim" data-g-panel-scrim></div>
<aside class="g-panel" data-g-panel aria-label="Aperçu du dossier">
    <div class="g-panel-head">
        <div><p class="g-muted" data-p="ref" style="margin:0;font-size:.78rem"></p><h2 data-p="nom"></h2></div>
        <button class="g-icon-btn" type="button" data-g-panel-close aria-label="Fermer l’aperçu"><?= gestion_icone('close', 18) ?></button>
    </div>
    <div class="g-panel-body">
        <p><span class="g-badge" data-p="badge"></span></p>
        <dl class="g-dl">
            <dt>Service</dt><dd data-p="service"></dd>
            <dt>Destination</dt><dd data-p="destination"></dd>
            <dt>Étape</dt><dd data-p="etape"></dd>
            <dt>Échéance</dt><dd data-p="echeance"></dd>
            <dt>Responsable</dt><dd data-p="responsable"></dd>
        </dl>
        <p class="g-muted" style="font-size:.82rem">Dernière activité : pièce déposée par le client, il y a 25 min.</p>
    </div>
    <div class="g-panel-foot">
        <a class="g-btn g-btn-primary" href="#" data-p="ouvrir">Ouvrir le dossier <?= gestion_icone('arrow', 16) ?></a>
        <button class="g-btn" type="button" data-g-demo="note" data-g-message="Note ajoutée au dossier (démonstration).">Ajouter une note</button>
    </div>
</aside>

<script>
// Liste des dossiers : vues, filtre, aperçu dans le panneau latéral (maquette).
(() => {
    const table = document.getElementById('table-dossiers');
    const rows = [...table.querySelectorAll('tbody tr')];
    const filter = document.querySelector('[data-g-filter]');
    const parcours = document.querySelector('[data-g-parcours]');
    const views = [...document.querySelectorAll('[data-g-views] button')];
    let view = views.find((b) => b.getAttribute('aria-pressed') === 'true')?.dataset.view || 'tous';
    // Retour depuis une fiche : la vue, la recherche et le parcours choisis sont retrouvés (revue 1A)
    const memoKey = 'g-dossiers-' + (document.querySelector('[data-g-poste]')?.value || '');
    const memo = (() => { try { return JSON.parse(sessionStorage.getItem(memoKey) || 'null'); } catch { return null; } })();
    if (memo && !new URLSearchParams(location.search).has('vue')) {
        const b = views.find((o) => o.dataset.view === memo.view);
        if (b) { view = memo.view; views.forEach((o) => o.setAttribute('aria-pressed', String(o === b))); }
        filter.value = memo.q || '';
        parcours.value = memo.parcours || '';
    }
    const normalize = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const apply = () => {
        const q = normalize(filter.value.trim());
        rows.forEach((row) => {
            const okView = view === 'tous' || (view === 'mes' && row.dataset.mine === '1') || (view === 'pieces' && row.dataset.statut === 'piece') || (view === 'retard' && row.dataset.late === '1');
            const okText = !q || normalize(row.textContent).includes(q);
            const okParcours = !parcours.value || row.dataset.parcours === parcours.value;
            row.hidden = !(okView && okText && okParcours);
            const box = row.querySelector('[data-g-select]');
            if (row.hidden && box) { box.checked = false; }
        });
        document.querySelector('[data-g-empty]').hidden = rows.some((row) => !row.hidden);
        try { sessionStorage.setItem(memoKey, JSON.stringify({ view, q: filter.value, parcours: parcours.value })); } catch { /* stockage indisponible : sans effet */ }
        table.gestionSelection?.update();
    };
    views.forEach((b) => b.addEventListener('click', () => {
        view = b.dataset.view;
        views.forEach((o) => o.setAttribute('aria-pressed', String(o === b)));
        apply();
    }));
    filter.addEventListener('input', apply);
    parcours.addEventListener('change', apply);
    apply();

    const open = (row) => window.gestionPanel.open((panel) => {
        const set = (k, v) => { panel.querySelector(`[data-p="${k}"]`).textContent = v; };
        ['ref', 'nom', 'service', 'destination', 'etape', 'echeance', 'responsable'].forEach((k) => set(k, row.dataset[k] || ''));
        set('destination', `${row.dataset.destination}${row.dataset.rentree !== '—' ? ' · ' + row.dataset.rentree : ''}`);
        const badge = panel.querySelector('[data-p="badge"]');
        badge.className = `g-badge is-${row.dataset.ton}`;
        badge.innerHTML = `<span class="g-dot is-${row.dataset.ton}"></span>`;
        badge.append(row.dataset.libelle);
        panel.querySelector('[data-p="ouvrir"]').href = row.dataset.href;
    }, row);
    rows.forEach((row) => {
        row.addEventListener('click', () => open(row));
        row.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                location.href = row.dataset.href;
            } else if (e.key === ' ') {
                e.preventDefault();
                open(row);
            }
        });
    });
})();
</script>
