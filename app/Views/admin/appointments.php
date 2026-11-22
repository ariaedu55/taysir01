<?php
/**
 * Back-office : rendez-vous demandés, à confirmer ou terminés.
 *
 * @var list<array>        $appointments
 * @var array<string, int> $counts
 * @var string|null        $status
 */
use App\Models\Appointment;

$errors = errors();
?>
<main id="contenu" class="dashboard portal admin">
    <section class="wrap dashboard-head">
        <div>
            <?php partial('admin-nav', ['active' => 'rendez-vous']); ?>
            <h1>Rendez-<em>vous</em></h1>
            <p class="dashboard-profile"><?= (int) ($counts['demande'] ?? 0) ?> à confirmer · <?= (int) ($counts['confirme'] ?? 0) ?> confirmé(s)</p>
        </div>
    </section>

    <div class="wrap portal-stack">
        <?php partial('flash'); ?>

        <div class="filters" role="group" aria-label="Filtrer par statut">
            <a class="filter<?= $status === null ? ' is-active' : '' ?>" href="<?= e(url('/admin/rendez-vous/')) ?>">Tous</a>
            <?php foreach (Appointment::STATUSES as $key => [$label]): ?>
                <a class="filter<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('/admin/rendez-vous/?statut=' . $key)) ?>"><?= e($label) ?> <span><?= (int) ($counts[$key] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>

        <?php if ($appointments === []): ?>
            <section class="account glass"><p class="portal-empty">Aucun rendez-vous pour ce filtre.</p></section>
        <?php endif; ?>

        <ul class="appointment-list">
            <?php foreach ($appointments as $appointment): ?>
                <?php $error = $errors['status_' . $appointment['id']] ?? null; ?>
                <li class="account glass appointment-card">
                    <div class="appointment-info">
                        <div class="appointment-top">
                            <strong><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></strong>
                            <?php partial('status', ['label' => Appointment::statusLabel($appointment['status']), 'tone' => Appointment::statusTone($appointment['status'])]); ?>
                        </div>
                        <p><?= e(Appointment::MOTIVES[$appointment['motive']] ?? '') ?> · <?= e(Appointment::MODES[$appointment['mode']] ?? '') ?></p>
                        <p class="table-muted">
                            <?= e($appointment['reference']) ?> · souhaité le <?= e(date('d/m/Y', strtotime($appointment['preferred_date']))) ?>, <?= $appointment['preferred_slot'] === 'matin' ? 'le matin' : 'l’après-midi' ?>
                            · <a href="tel:<?= e(str_replace(' ', '', $appointment['phone'])) ?>"><?= e($appointment['phone']) ?></a>
                            · <a href="mailto:<?= e($appointment['email']) ?>"><?= e($appointment['email']) ?></a>
                            <?= $appointment['user_id'] ? ' · compte client' : ' · sans compte' ?>
                        </p>
                        <?php if (!empty($appointment['message'])): ?><p class="summary-message"><?= nl2br(e($appointment['message'])) ?></p><?php endif; ?>
                    </div>
                    <form class="appointment-form" method="post" action="<?= e(url('/admin/rendez-vous/' . $appointment['reference'] . '/')) ?>">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="statut-<?= (int) $appointment['id'] ?>">Statut</label>
                            <div class="field-control field-select">
                                <select id="statut-<?= (int) $appointment['id'] ?>" name="status">
                                    <?php foreach (Appointment::STATUSES as $key => [$label]): ?>
                                        <option value="<?= e($key) ?>"<?= $key === $appointment['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="heure-<?= (int) $appointment['id'] ?>">Date et heure</label>
                            <div class="field-control">
                                <input id="heure-<?= (int) $appointment['id'] ?>" name="scheduled_at" type="datetime-local" step="900"
                                    value="<?= $appointment['scheduled_at'] ? e(date('Y-m-d\TH:i', strtotime($appointment['scheduled_at']))) : '' ?>">
                            </div>
                        </div>
                        <button class="button primary button-sm" type="submit">Mettre à jour</button>
                        <?php if ($error): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</main>
