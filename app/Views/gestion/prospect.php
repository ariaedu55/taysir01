<?php
use App\Models\Appointment;
use App\Models\Prospect;

$root = '/gestion/prospects/';
$action = url($root . $row['id'] . '/');
$status = Prospect::STATES[$row['state']];
$activeAppointment = array_find($appointments, static fn (array $appointment): bool => $appointment['status'] === 'demande'
    || ($appointment['status'] === 'confirme' && $appointment['ends_at_utc'] >= gmdate('Y-m-d H:i:s')));
?>
<div class="g-toolbar">
    <a class="g-btn" href="<?= e(url($root)) ?>">Retour aux prospects</a>
    <?php if ($me['role'] === 'admin'): ?><a class="g-btn" href="<?= e(url('/gestion/personnes/' . $row['person_id'] . '/')) ?>">Ouvrir la fiche personne</a><?php endif; ?>
</div>
<?php if ($error = errors()['prospection'] ?? null): ?><p class="g-field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="g-grid g-grid-2">
    <div class="g-grid">
        <section class="g-card is-pro" aria-labelledby="prospect-summary">
            <div class="g-card-head"><h2 id="prospect-summary"><span class="g-head-icon"><?= gestion_icone('users') ?></span>Fiche prospect</h2><?= g_badge($status, $row['state'] === 'transmis' ? 'ok' : 'pro') ?></div>
            <dl class="g-prospect-details">
                <div><dt>Établissement</dt><dd><?= e($row['school'] . ' · ' . $row['level']) ?></dd></div>
                <div><dt>Campagne</dt><dd><?= e($row['campaign'] . ' · ' . $row['agency']) ?></dd></div>
                <div><dt>Commerciale</dt><dd><?= e($row['assignee'] ?? 'Non attribué') ?></dd></div>
                <?php if ($row['follow_up_on']): ?><div><dt>Relance</dt><dd><?= e(g_date($row['follow_up_on'])) ?></dd></div><?php endif; ?>
                <?php if ($row['phone']): ?><div><dt>Téléphone</dt><dd><a href="tel:<?= e($row['phone']) ?>"><?= e($row['phone']) ?></a></dd></div><?php endif; ?>
                <?php if ($row['email']): ?><div><dt>E-mail</dt><dd><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></dd></div><?php endif; ?>
            </dl>
        </section>
        <section class="g-card is-rdv" aria-labelledby="prospect-appointments">
            <div class="g-card-head"><h2 id="prospect-appointments"><span class="g-head-icon"><?= gestion_icone('calendar') ?></span>Rendez-vous</h2></div>
            <?php if (!$appointments): ?><p class="g-empty">Aucun rendez-vous proposé.</p><?php else: ?>
                <ul class="g-tasks"><?php foreach ($appointments as $appointment): ?>
                    <li class="g-task"><span class="g-doc-icon"><?= gestion_icone('calendar', 16) ?></span><div>
                        <strong><?= e($appointment['reference']) ?> · <?= e(Appointment::statusLabel($appointment['status'])) ?></strong>
                        <p><?= e($appointment['starts_at_utc'] ? Appointment::local($appointment['starts_at_utc'], Appointment::zone((int) $appointment['agency_id']), 'd/m/Y H:i') : g_date($appointment['preferred_date']) . ' · ' . ($appointment['preferred_slot'] === 'matin' ? 'Matin' : 'Après-midi')) ?></p>
                    </div></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
        <section class="g-card is-neutre" aria-labelledby="prospect-history">
            <div class="g-card-head"><h2 id="prospect-history"><span class="g-head-icon"><?= gestion_icone('clock') ?></span>Suivi interne</h2></div>
            <?php if (!$events): ?><p class="g-empty">Aucune action de suivi enregistrée.</p><?php else: ?>
                <ul class="g-tasks"><?php foreach ($events as $event): ?>
                    <li class="g-task"><span class="g-doc-icon"><?= gestion_icone('doc', 16) ?></span><div><strong><?= e($event['author']) ?> · <?= e(g_date($event['created_at'])) ?></strong><p class="g-prospect-note"><?= e($event['detail']) ?></p></div></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    </div>
    <?php if ($canTrack): ?><div class="g-grid">
        <form class="g-card is-ok" method="post" action="<?= e($action) ?>">
            <div class="g-card-head"><h2><span class="g-head-icon"><?= gestion_icone('phone') ?></span>Enregistrer un appel</h2></div>
            <?= csrf_field() ?><input type="hidden" name="action" value="appel">
            <label class="g-form-row">Résultat <select class="g-select" name="state" required><option value="">Choisir un résultat</option>
                <?php foreach (Prospect::CALLS as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select></label>
            <label class="g-form-row">Date de relance si « À rappeler » <input class="g-input" name="follow_up" type="date" min="<?= e($today) ?>"></label>
            <label class="g-form-row">Note interne (facultative) <textarea class="g-input" name="note" rows="3" maxlength="1500"></textarea></label>
            <button class="g-btn g-btn-success" type="submit">Enregistrer l’appel</button>
        </form>
        <form class="g-card is-rdv" method="post" action="<?= e($action) ?>">
            <div class="g-card-head"><h2><span class="g-head-icon"><?= gestion_icone('calendar') ?></span><?= $activeAppointment ? 'Transmettre le prospect' : 'Proposer un rendez-vous' ?></h2></div>
            <?= csrf_field() ?>
            <?php if ($activeAppointment): ?>
                <p class="g-muted">Le rendez-vous <?= e($activeAppointment['reference']) ?> est déjà dans l’agenda. Transmettre la fiche clôture le suivi commercial.</p>
                <button class="g-btn g-btn-success" name="action" value="transmission" type="submit">Transmettre à l’accueil</button>
            <?php else: ?>
            <label class="g-form-row">Jour souhaité <input class="g-input" name="date" type="date" min="<?= e($today) ?>" required></label>
            <label class="g-form-row">Plage souhaitée <select class="g-select" name="slot"><option value="matin">Matin</option><option value="apres-midi">Après-midi</option></select></label>
            <label class="g-form-row">Mode <select class="g-select" name="mode">
                <?php foreach (Prospect::MODES as $mode): ?><option value="<?= e($mode) ?>"><?= e(Appointment::MODES[$mode]) ?></option><?php endforeach; ?>
            </select></label>
            <p class="g-muted">L’accueil confirme le rendez-vous. En transmettant la fiche, vous clôturez le suivi commercial.</p>
            <div class="g-toolbar g-prospect-actions">
                <button class="g-btn g-btn-primary" name="action" value="rendez-vous" type="submit">Proposer</button>
                <button class="g-btn g-btn-success" name="action" value="transmission" type="submit">Transmettre à l’accueil</button>
            </div>
            <?php endif; ?>
        </form>
    </div><?php endif; ?>
</div>
