<?php
/**
 * Liste des dossiers (/gestion/dossiers/), toujours limitée au périmètre du compte (docs/gestion-phase-1.md, 1B).
 *
 * @var list<array> $dossiers
 * @var array       $counts    Dossiers du périmètre par statut
 * @var string|null $vue, $status, $service
 * @var list<array> $services
 * @var bool        $affectable
 */
use App\Models\ServiceRequest;
use App\Services\Access;

$total = array_sum($counts);
$params = ['vue' => $vue, 'statut' => $status, 'service' => $service, 'pays' => $pays ?? null, 'motif' => $motif ?? null];
$motifsParService = array_filter(array_column($services, 'purposes', 'key'));
$vues = ['' => 'Tout mon périmètre'];
if ($affectable) {
    $vues += ['mes' => 'Mes dossiers', 'a-prendre' => 'À prendre'];
}
$titres = [];
foreach ($services as $s) {
    $titres[$s['key']] = $s['title'];
}
?>
<form class="g-toolbar" method="get" action="<?= e(url('/gestion/dossiers/')) ?>">
    <div class="g-views" role="group" aria-label="Vues">
        <?php foreach ($vues as $cle => $label): ?>
            <a class="g-view-link" href="<?= e(g_filtre('/gestion/dossiers/', ['vue' => $cle] + $params)) ?>"<?= ($vue ?? '') === $cle ? ' aria-current="true"' : '' ?>><?= e($label) ?><?= $cle === '' ? '<span>' . (int) $total . '</span>' : '' ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($vue !== null): ?><input type="hidden" name="vue" value="<?= e($vue) ?>"><?php endif; ?>
    <label class="sr-only" for="f-statut">Statut</label>
    <select class="g-select" id="f-statut" name="statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        <?php foreach (ServiceRequest::STATUSES as $cle => [, $court]): ?>
            <option value="<?= e($cle) ?>"<?= $status === $cle ? ' selected' : '' ?>><?= e($court) ?> (<?= (int) ($counts[$cle] ?? 0) ?>)</option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="f-service">Service</label>
    <select class="g-select" id="f-service" name="service" onchange="this.form.submit()">
        <option value="">Tous les services</option>
        <?php foreach ($services as $s): ?>
            <option value="<?= e($s['key']) ?>"<?= $service === $s['key'] ? ' selected' : '' ?>><?= e($s['title']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php // Phase 12 : pays et motif de la démarche ?>
    <label class="sr-only" for="f-pays">Pays</label>
    <select class="g-select" id="f-pays" name="pays" onchange="this.form.submit()">
        <option value="">Tous les pays</option>
        <?php foreach ($paysCouverts ?? [] as $nomPays): ?><option value="<?= e($nomPays) ?>"<?= ($pays ?? null) === $nomPays ? ' selected' : '' ?>><?= e($nomPays) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="f-motif">Motif</label>
    <select class="g-select" id="f-motif" name="motif" onchange="this.form.submit()">
        <option value="">Tous les motifs</option>
        <?php foreach ($motifsParService as $cleService => $motifsService): ?>
            <optgroup label="<?= e($titres[$cleService] ?? $cleService) ?>"><?php foreach ($motifsService as $cleMotif => $libMotif): ?><option value="<?= e($cleMotif) ?>"<?= ($motif ?? null) === $cleMotif ? ' selected' : '' ?>><?= e($libMotif) ?></option><?php endforeach; ?></optgroup>
        <?php endforeach; ?>
    </select>
    <noscript><button class="g-btn" type="submit">Filtrer</button></noscript>
</form>

<div class="g-table-wrap">
    <?php if ($dossiers === []): ?>
        <p class="g-empty"><?= $vue === 'mes' ? 'Aucun dossier ne vous est affecté.' : ($vue === 'a-prendre' ? 'Aucun dossier à prendre.' : 'Aucun dossier pour ces filtres.') ?></p>
    <?php else: ?>
        <table class="g-table">
            <thead>
                <tr><th>Personne et dossier</th><th>Démarche</th><th>Statut</th><th>Responsables</th><th>Pièces</th><th>Mis à jour</th></tr>
            </thead>
            <tbody>
                <?php foreach ($dossiers as $r): ?>
                    <?php $responsables = g_responsables($r['responsables']); ?>
                    <tr>
                        <td class="g-cell-main"><a class="g-row-link" href="<?= e(url('/gestion/dossiers/' . $r['reference'] . '/')) ?>"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></a><span><?= e($r['reference']) ?></span></td>
                        <td data-label="Démarche"><?= e(App\Services\Demarches::libelle($r['service'], $r['destination'], $r['purpose'] ?? null)) ?></td>
                        <td><?= g_badge(ServiceRequest::statusLabel($r['status'], true), ServiceRequest::statusTone($r['status'])) ?></td>
                        <td data-label="Responsables">
                            <?php if ($responsables === []): ?>
                                <span class="g-badge is-alerte"><span class="g-dot is-alerte"></span>À prendre</span>
                            <?php else: ?>
                                <?php foreach ($responsables as $poste => $nom): ?><span class="g-person"><small><?= e(Access::AFFECTATIONS[$poste] ?? $poste) ?></small><?= e($nom) ?></span><?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="g-mobile-hide"><?php if ((int) $r['pieces_total'] > 0): ?><?= g_badge((int) $r['pieces_recues'] . '/' . (int) $r['pieces_total'], (int) $r['pieces_recues'] >= (int) $r['pieces_total'] ? 'green' : 'grey') ?><?php else: ?><?= (int) $r['document_count'] ?><?php endif; ?></td>
                        <td data-label="Mis à jour"><?= e(g_date($r['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
