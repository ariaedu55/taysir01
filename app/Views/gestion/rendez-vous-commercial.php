<?php
use App\Models\Appointment;

$root = '/gestion/rendez-vous/';
?>
<div class="g-toolbar"><a class="g-btn g-btn-primary" href="<?= e(url('/gestion/prospects/')) ?>"><?= gestion_icone('target', 16) ?> Mes prospects</a></div>
<form class="g-toolbar" method="get" action="<?= e(url($root)) ?>">
    <label class="g-form-row">Statut <select class="g-select" name="statut">
        <option value="">Tous les rendez-vous</option>
        <?php foreach (Appointment::STATUSES as $key => $label): ?><option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e(Appointment::statusLabel($key)) ?> (<?= (int) ($counts[$key] ?? 0) ?>)</option><?php endforeach; ?>
    </select></label>
    <button class="g-btn" type="submit">Filtrer</button>
</form>
<div class="g-table-wrap">
    <?php if (!$appointments): ?><p class="g-empty">Aucune proposition dans cette vue. Ouvrez une fiche prospect pour proposer un rendez-vous.</p><?php else: ?>
    <table class="g-table g-prospect-table">
        <thead><tr><th>Personne</th><th>Date</th><th>Mode</th><th>Statut</th></tr></thead>
        <tbody><?php foreach ($appointments as $row): ?>
            <tr><td class="g-cell-main"><strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong><span><?= e($row['reference']) ?></span></td>
                <td data-label="Date"><?= e($row['starts_at_utc'] ? Appointment::local($row['starts_at_utc'], Appointment::zone((int) $row['agency_id']), 'd/m/Y H:i') : g_date($row['preferred_date']) . ' · ' . ($row['preferred_slot'] === 'matin' ? 'Matin' : 'Après-midi')) ?></td>
                <td data-label="Mode"><?= e(Appointment::MODES[$row['mode']]) ?></td>
                <td data-label="Statut"><?= g_badge($row['status'] === 'demande' ? 'À confirmer par l’accueil' : Appointment::statusLabel($row['status']), Appointment::statusTone($row['status'])) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>
</div>
