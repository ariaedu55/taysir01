<?php
/**
 * Soldes et échéances (/gestion/finance/), administrateur et comptable (revue du 28/11/2026, écart 5).
 * Un dossier par ligne : devis, payé, solde, montant échu non payé, prochaine échéance.
 *
 * @var list<array>          $lignes
 * @var array<string, int>   $totaux
 * @var string               $filtre    '', 'solde' ou 'retard'
 * @var string               $recherche
 * @var string               $today
 */
use App\Models\ServiceRequest;

$nombre = static fn (int|string $montant): string => number_format((int) $montant, 0, ',', ' ');
$fcfa = static fn (int|string $montant): string => number_format((int) $montant, 0, ',', ' ') . ' FCFA';
$vues = ['' => 'Tous les dossiers', 'solde' => 'Solde à encaisser', 'retard' => 'En retard'];
?>
<div class="g-kpis">
    <div class="g-kpi is-rdv"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('folder', 20) ?></span><strong><?= e($nombre($totaux['devis'])) ?></strong><span class="g-kpi-label">Devis (FCFA)</span></div>
    <div class="g-kpi is-ok"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('check', 20) ?></span><strong><?= e($nombre($totaux['paye'])) ?></strong><span class="g-kpi-label">Encaissé (FCFA)</span></div>
    <div class="g-kpi is-ech"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('clock', 20) ?></span><strong><?= e($nombre($totaux['solde'])) ?></strong><span class="g-kpi-label">Reste à encaisser (FCFA)</span></div>
    <div class="g-kpi is-<?= $totaux['retard'] > 0 ? 'urgent' : 'neutre' ?>"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('alert', 20) ?></span><strong><?= e($nombre($totaux['retard'])) ?></strong><span class="g-kpi-label">En retard, FCFA (<?= (int) $totaux['nbRetard'] ?> dossier<?= $totaux['nbRetard'] > 1 ? 's' : '' ?>)</span></div>
</div>

<form class="g-toolbar" method="get" action="<?= e(url('/gestion/finance/')) ?>" role="search">
    <div class="g-views" role="group" aria-label="Vues">
        <?php foreach ($vues as $cle => $libelle): ?>
            <a class="g-view-link" href="<?= e(g_filtre('/gestion/finance/', ['vue' => $cle, 'q' => $recherche])) ?>"<?= $filtre === $cle ? ' aria-current="true"' : '' ?>><?= e($libelle) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($filtre !== ''): ?><input type="hidden" name="vue" value="<?= e($filtre) ?>"><?php endif; ?>
    <label class="g-search-field"><?= gestion_icone('search', 17) ?><span class="sr-only">Rechercher</span>
        <input class="g-input" type="search" name="q" value="<?= e($recherche) ?>" placeholder="Nom ou référence du dossier">
    </label>
    <button class="g-btn" type="submit">Rechercher</button>
    <a class="g-btn g-btn-primary" href="<?= e(url('/gestion/exports/finance/')) ?>"><?= gestion_icone('download', 16) ?> Exporter (CSV)</a>
</form>

<div class="g-table-wrap">
    <?php if ($lignes === []): ?>
        <p class="g-empty"><?= $filtre === 'retard' ? 'Aucun dossier en retard.' : ($recherche !== '' ? 'Aucun dossier ne correspond.' : 'Aucun devis enregistré.') ?></p>
    <?php else: ?>
        <table class="g-table">
            <thead><tr><th>Dossier</th><th>Devis</th><th>Encaissé</th><th>Solde</th><th>En retard</th><th>Prochaine échéance</th></tr></thead>
            <tbody>
                <?php foreach ($lignes as $l): ?>
                    <tr>
                        <td class="g-cell-main">
                            <a class="g-row-link" href="<?= e(url('/gestion/dossiers/' . $l['reference'] . '/finance/')) ?>"><strong><?= e($l['first_name'] . ' ' . $l['last_name']) ?></strong></a>
                            <span><?= e($l['reference']) ?> · <?= e($l['title']) ?> · <?= e(ServiceRequest::statusLabel($l['status'], true)) ?></span>
                        </td>
                        <td data-label="Devis"><?= e($fcfa($l['amount'])) ?></td>
                        <td data-label="Encaissé"><?= e($fcfa($l['paid'])) ?></td>
                        <td data-label="Solde"><strong><?= e($fcfa($l['balance'])) ?></strong></td>
                        <td data-label="En retard"><?= $l['overdue'] > 0 ? g_badge($fcfa($l['overdue']), 'red') : '—' ?></td>
                        <td data-label="Prochaine échéance"><?= $l['next_due'] ? e(date('d/m/Y', strtotime($l['next_due']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<p class="g-muted g-form-note">« En retard » : total des échéances arrivées au <?= e(date('d/m/Y', strtotime($today))) ?>, moins les règlements encaissés (hors règlements annulés).</p>
