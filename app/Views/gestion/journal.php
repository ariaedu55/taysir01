<?php
/**
 * Journal d'audit (/gestion/journal/, administrateur) : connexions, accès refusés, pièces ouvertes, affectations,
 * changements de poste et missions. docs/gestion-plan.md §8, docs/gestion-phase-1.md (1B).
 *
 * @var list<array> $lignes
 * @var string|null $action
 * @var array       $actions
 */
use App\Services\Access;

$tons = ['acces_refuse' => 'urgent', 'piece_telechargee' => 'ech', 'affectation' => 'rdv', 'connexion' => 'neutre',
    'poste_change' => 'ori', 'mission_attribuee' => 'ori', 'mission_retiree' => 'ori', 'compte_desactive' => 'neutre', 'compte_reactive' => 'ok', 'membre_invite' => 'ok'];
?>
<div class="g-toolbar">
    <div class="g-views" role="group" aria-label="Filtrer par action">
        <a class="g-view-link" href="<?= e(url('/gestion/journal/')) ?>"<?= $action === null ? ' aria-current="true"' : '' ?>>Tout</a>
        <?php foreach (['acces_refuse', 'piece_telechargee', 'affectation', 'connexion'] as $cle): ?>
            <a class="g-view-link" href="<?= e(url('/gestion/journal/?action=' . $cle)) ?>"<?= $action === $cle ? ' aria-current="true"' : '' ?>><?= e($actions[$cle]) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<div class="g-table-wrap">
    <?php if ($lignes === []): ?>
        <p class="g-empty">Aucune ligne pour ce filtre.</p>
    <?php else: ?>
        <table class="g-table g-journal">
            <thead><tr><th>Date</th><th>Action</th><th>Auteur</th><th>Objet</th><th>Détail</th></tr></thead>
            <tbody>
                <?php foreach ($lignes as $l): ?>
                    <?php $detail = json_decode((string) $l['detail'], true) ?: []; ?>
                    <tr>
                        <td><?= e(g_date($l['created_at'], true)) ?></td>
                        <td><span class="g-badge is-<?= e($tons[$l['action']] ?? 'neutre') ?>"><span class="g-dot"></span><?= e($actions[$l['action']] ?? $l['action']) ?></span></td>
                        <td data-label="Auteur"><?= $l['first_name'] ? e($l['first_name'] . ' ' . $l['last_name']) . ' <small class="g-muted">' . e($l['role'] === 'client' ? 'Client' : Access::libelle((string) $l['role'])) . '</small>' : '<span class="g-muted">visiteur</span>' ?></td>
                        <td data-label="Objet"><?= e(trim(($l['subject_type'] ?? '') . ' ' . ($l['subject_ref'] ?? ''))) ?></td>
                        <td data-label="Détail" class="g-muted"><?= e(implode(' · ', array_map(static fn ($k, $v) => $k . ' : ' . (is_scalar($v) ? $v : json_encode($v)), array_keys($detail), $detail))) ?><?= $l['ip'] ? ' · ' . e($l['ip']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
