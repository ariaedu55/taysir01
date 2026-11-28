<?php
/**
 * Agenda et demandes à placer dans le périmètre de l’équipe.
 *
 * @var list<array>        $appointments
 * @var array<string, int> $counts
 * @var string|null        $status
 * @var DateTimeZone       $zone   Fuseau de l'agence du compte (B2)
 * @var string             $ville  Ville de cette agence
 */
use App\Models\Appointment;

$errors = errors();
$memberNames = array_column(array_map(static fn (array $member): array => [
    'id' => $member['id'],
    'name' => $member['first_name'] . ' ' . $member['last_name'],
], $members), 'name', 'id');
?>
<section class="g-card" aria-labelledby="agenda-periode">
    <div class="g-toolbar g-agenda-bar">
        <h2 id="agenda-periode"><?= $canAssign ? 'Agenda du jour' : 'Ma semaine' ?> · heure de <?= e($ville) ?></h2>
        <div class="g-views">
            <?php $step = $canAssign ? 'day' : 'week'; ?>
            <a class="g-btn g-btn-sm" href="<?= e(url('/gestion/rendez-vous/?jour=' . $agendaDay->modify("-1 $step")->format('Y-m-d'))) ?>">Précédent</a>
            <time datetime="<?= e($agendaDay->format('Y-m-d')) ?>"><?= e($agendaDay->format('d/m/Y')) ?></time>
            <a class="g-btn g-btn-sm" href="<?= e(url('/gestion/rendez-vous/?jour=' . $agendaDay->modify("+1 $step")->format('Y-m-d'))) ?>">Suivant</a>
        </div>
    </div>
    <?php if (!$calendar): ?><p class="g-empty">Aucun rendez-vous planifié sur cette période.</p><?php endif; ?>
    <div class="g-agenda-columns">
        <?php foreach ($calendar as $group => $entries): ?>
            <div>
                <h3><?= e($canAssign ? ($memberNames[$group] ?? 'À attribuer') : ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][(int) (new DateTimeImmutable($group))->format('N')] . ' ' . (new DateTimeImmutable($group))->format('d/m')) ?></h3>
                <ul class="g-agenda">
                    <?php usort($entries, static fn ($a, $b) => $a['starts_at_utc'] <=> $b['starts_at_utc']); ?>
                    <?php foreach ($entries as $entry): ?>
                        <li><time><?= e($entry['heure_locale']) ?></time><div>
                            <strong><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></strong>
                            <?= e(Appointment::MOTIVES[$entry['motive']] ?? '') ?>
                        </div></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php if ($durations): ?>
<details id="durees" class="g-card g-pli"<?= isset($errors['duration']) ? ' open' : '' ?>>
    <summary>Durées par motif</summary>
    <?php if (isset($errors['duration'])): ?><p role="alert"><?= e($errors['duration']) ?></p><?php endif; ?>
    <?php foreach (Appointment::MOTIVES as $motive => $label): ?>
        <form method="post" action="<?= e(url('/gestion/rendez-vous/durees/')) ?>" class="g-toolbar">
            <?= csrf_field() ?>
            <input type="hidden" name="motive" value="<?= e($motive) ?>">
            <label><?= e($label) ?> · minutes <input class="g-input" name="minutes" type="number" min="15" max="180" value="<?= (int) $durations[$motive] ?>" required></label>
            <button type="submit" class="g-btn g-btn-success g-btn-sm">Enregistrer</button>
        </form>
    <?php endforeach; ?>
</details>
<?php endif; ?>
<details id="disponibilites" class="g-card g-pli"<?= isset($errors['availability']) ? ' open' : '' ?>>
    <summary>Mes disponibilités et absences</summary>
    <?php if (isset($errors['availability'])): ?><p class="g-field-error" role="alert"><?= e($errors['availability']) ?></p><?php endif; ?>
    <?php $days = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']; ?>
    <div class="g-agenda-columns">
        <?php foreach (['rules' => 'Plages hebdomadaires', 'absences' => 'Absences à venir'] as $key => $label): ?>
            <div>
                <h3><?= e($label) ?></h3>
                <?php foreach ($availability[$key] as $entry): ?>
                    <form class="g-dispo-ligne" method="post" action="<?= e(url('/gestion/rendez-vous/disponibilites/')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kind" value="<?= $key === 'rules' ? 'delete-rule' : 'delete-absence' ?>">
                        <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                        <span><?= e($key === 'rules'
                            ? $days[$entry['weekday']] . ' · ' . substr($entry['starts_at'], 0, 5) . ' – ' . substr($entry['ends_at'], 0, 5) . ' · ' . $entry['mode']
                            : g_date(Appointment::local($entry['starts_at_utc'], $zone, 'Y-m-d H:i:s'), true) . ' – ' . g_date(Appointment::local($entry['ends_at_utc'], $zone, 'Y-m-d H:i:s'), true)) ?></span>
                        <button class="g-btn g-btn-sm g-btn-danger" type="submit">Supprimer</button>
                    </form>
                <?php endforeach; ?>
                <form class="g-rdv-form" method="post" action="<?= e(url('/gestion/rendez-vous/disponibilites/')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kind" value="<?= $key === 'rules' ? 'rule' : 'absence' ?>">
                    <?php if ($key === 'rules'): ?>
                        <label class="g-form-row">Jour <select class="g-select" name="weekday">
                            <?php foreach ($days as $day => $name): ?><option value="<?= $day ?>"><?= e($name) ?></option><?php endforeach; ?>
                        </select></label>
                        <label class="g-form-row">Mode <select class="g-select" name="mode"><option value="agence">En agence</option><option value="visio">Visio</option></select></label>
                    <?php endif; ?>
                    <label class="g-form-row">Début<?= $key === 'absences' ? ' · heure de ' . e($ville) : '' ?> <input class="g-input" name="start" type="<?= $key === 'rules' ? 'time' : 'datetime-local' ?>" required></label>
                    <label class="g-form-row">Fin<?= $key === 'absences' ? ' · heure de ' . e($ville) : '' ?> <input class="g-input" name="end" type="<?= $key === 'rules' ? 'time' : 'datetime-local' ?>" required></label>
                    <button class="g-btn g-btn-sm g-btn-success" type="submit">Enregistrer</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</details>
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
            <?php $zoneRdv = Appointment::zone($a['agency_id'] ? (int) $a['agency_id'] : null); $villeRdv = Appointment::city($a['agency_id'] ? (int) $a['agency_id'] : null); ?>
            <li class="g-card g-rdv">
                <div class="g-rdv-info">
                    <p class="g-rdv-top"><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><?= g_badge(Appointment::statusLabel($a['status']), Appointment::statusTone($a['status'])) ?></p>
                    <p><?= e(Appointment::MOTIVES[$a['motive']] ?? '') ?> · <?= e(Appointment::MODES[$a['mode']] ?? '') ?></p>
                    <?php if ($a['needs_reschedule']): ?>
                        <p><?= g_badge('Responsable indisponible · à replanifier', 'urgent') ?></p>
                    <?php endif; ?>
                    <p class="g-muted">
                        <?= e($a['reference']) ?> · souhaité le <?= e(date('d/m/Y', strtotime($a['preferred_date']))) ?>, <?= $a['preferred_slot'] === 'matin' ? 'le matin' : 'l’après-midi' ?>
                        · <a href="tel:<?= e(str_replace(' ', '', $a['phone'])) ?>"><?= e($a['phone']) ?></a> · <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a>
                        <?= $a['user_id'] ? ' · compte client' : ' · sans compte' ?>
                    </p>
                    <?php if (!empty($a['message'])): ?><p class="g-quote"><?= nl2br(e($a['message'])) ?></p><?php endif; ?>
                    <?php if ($a['starts_at_utc'] && $a['mode'] === 'visio' && $a['person_timezone']): ?>
                        <?php $visio = new DateTimeImmutable($a['starts_at_utc'], new DateTimeZone('UTC')); ?>
                        <p class="g-muted"><?= e($visio->setTimezone($zoneRdv)->format('d/m H:i')) ?> à <?= e($villeRdv) ?> · <?= e($visio->setTimezone(new DateTimeZone($a['person_timezone']))->format('d/m H:i T')) ?> pour la personne</p>
                    <?php endif; ?>
                    <?php
                    // 2C (E5, N3) : compte rendu d'un entretien commencé, par son responsable ou l'administrateur
                    $redacteur = App\Services\Access::posteEcriture($me) !== null
                        && ((int) $a['assigned_to'] === (int) $me['id'] || in_array('admin', App\Services\Access::postes($me), true));
                    ?>
                    <?php if ($redacteur && $a['assigned_to'] && in_array($a['status'], ['confirme', 'honore'], true) && $a['starts_at_utc'] && $a['starts_at_utc'] <= gmdate('Y-m-d H:i:s')): ?>
                        <p><a class="g-btn g-btn-sm g-btn-primary" href="<?= e(url('/gestion/rendez-vous/' . $a['reference'] . '/compte-rendu/')) ?>"><?= gestion_icone('chat', 15) ?> Compte rendu</a></p>
                    <?php endif; ?>
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
                    <?php if ($canAssign): ?>
                        <label class="g-form-row">Responsable
                            <select class="g-select" name="assigned_to">
                                <option value="">À attribuer</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= (int) $member['id'] ?>"<?= (int) $member['id'] === (int) $a['assigned_to'] ? ' selected' : '' ?>><?= e($member['first_name'] . ' ' . $member['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label class="g-form-row">Début · heure de <?= e($villeRdv) ?>
                        <input class="g-input" name="scheduled_at" type="datetime-local" value="<?= e(Appointment::local($a['starts_at_utc'], $zoneRdv, 'Y-m-d\TH:i')) ?>">
                    </label>
                    <label class="g-form-row">Fin · heure de <?= e($villeRdv) ?>
                        <input class="g-input" name="ends_at" type="datetime-local" value="<?= e(Appointment::local($a['ends_at_utc'], $zoneRdv, 'Y-m-d\TH:i')) ?>">
                    </label>
                    <button class="g-btn g-btn-success g-btn-sm" type="submit"><?= gestion_icone('check', 15) ?> Mettre à jour</button>
                    <?php if ($erreur): ?><p class="g-field-error"><?= e($erreur) ?></p><?php endif; ?>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
