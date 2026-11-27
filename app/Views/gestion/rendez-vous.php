<?php
/**
 * Rendez-vous demandés sur le site (/gestion/rendez-vous/), docs/gestion-phase-1.md (1B).
 * L'agenda complet (créneaux, anti-double réservation, compte rendu) arrive en phase 2.
 *
 * @var list<array>        $appointments
 * @var array<string, int> $counts
 * @var string|null        $status
 */
use App\Models\Appointment;

$errors = errors();
?>
<div class="g-toolbar">
    <div class="g-views" role="group" aria-label="Filtrer par statut">
        <a class="g-view-link" href="<?= e(url('/gestion/rendez-vous/')) ?>"<?= $status === null ? ' aria-current="true"' : '' ?>>Tous<span><?= (int) array_sum($counts) ?></span></a>
        <?php foreach (Appointment::STATUSES as $cle => [$label]): ?>
            <a class="g-view-link" href="<?= e(url('/gestion/rendez-vous/?statut=' . $cle)) ?>"<?= $status === $cle ? ' aria-current="true"' : '' ?>><?= e($label) ?><span><?= (int) ($counts[$cle] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($appointments === []): ?>
    <div class="g-card"><p class="g-empty">Aucun rendez-vous pour ce filtre.</p></div>
<?php else: ?>
    <ul class="g-cards-list">
        <?php foreach ($appointments as $a): ?>
            <?php $erreur = $errors['rdv_' . $a['id']] ?? null; ?>
            <li class="g-card g-rdv">
                <div class="g-rdv-info">
                    <p class="g-rdv-top"><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><?= g_badge(Appointment::statusLabel($a['status']), Appointment::statusTone($a['status'])) ?></p>
                    <p><?= e(Appointment::MOTIVES[$a['motive']] ?? '') ?> · <?= e(Appointment::MODES[$a['mode']] ?? '') ?></p>
                    <p class="g-muted">
                        <?= e($a['reference']) ?> · souhaité le <?= e(date('d/m/Y', strtotime($a['preferred_date']))) ?>, <?= $a['preferred_slot'] === 'matin' ? 'le matin' : 'l’après-midi' ?>
                        · <a href="tel:<?= e(str_replace(' ', '', $a['phone'])) ?>"><?= e($a['phone']) ?></a> · <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a>
                        <?= $a['user_id'] ? ' · compte client' : ' · sans compte' ?>
                    </p>
                    <?php if (!empty($a['message'])): ?><p class="g-quote"><?= nl2br(e($a['message'])) ?></p><?php endif; ?>
                </div>
                <form class="g-rdv-form" method="post" action="<?= e(url('/gestion/rendez-vous/' . $a['reference'] . '/')) ?>">
                    <?= csrf_field() ?>
                    <label class="g-form-row">Statut
                        <select class="g-select" name="status">
                            <?php foreach (Appointment::STATUSES as $cle => [$label]): ?>
                                <option value="<?= e($cle) ?>"<?= $cle === $a['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="g-form-row">Date et heure
                        <input class="g-input" name="scheduled_at" type="datetime-local" step="900" value="<?= $a['scheduled_at'] ? e(date('Y-m-d\TH:i', strtotime($a['scheduled_at']))) : '' ?>">
                    </label>
                    <button class="g-btn g-btn-primary g-btn-sm" type="submit"><?= gestion_icone('check', 15) ?> Mettre à jour</button>
                    <?php if ($erreur): ?><p class="g-field-error"><?= e($erreur) ?></p><?php endif; ?>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
