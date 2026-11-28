<?php
/**
 * Compte rendu d'un entretien (/gestion/rendez-vous/{référence}/compte-rendu/), étape 2C (E5, N3) : une note liée
 * au rendez-vous, choix interne ou partagé OBLIGATOIRE, un seul compte rendu par rendez-vous.
 *
 * @var array  $rdv
 * @var string $quand
 * @var bool   $dejaRedige
 */
use App\Models\Appointment;
use App\Models\Note;

$errors = errors();
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/rendez-vous/')) ?>"><?= gestion_icone('collapse', 15) ?> Agenda</a></p>

<section class="g-card is-ori g-form-card" aria-labelledby="cr-titre">
    <div class="g-card-head"><h3 id="cr-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('chat', 18) ?></span>Compte rendu de l’entretien</h3></div>
    <p class="g-case-sub">
        <strong><?= e($rdv['first_name'] . ' ' . $rdv['last_name']) ?></strong> ·
        <?= e(Appointment::MOTIVES[$rdv['motive']] ?? '') ?> · <?= e(Appointment::MODES[$rdv['mode']] ?? '') ?>
        <?= $quand !== '' ? ' · ' . e($quand) : '' ?> · <?= e($rdv['reference']) ?>
        <?= g_badge(Appointment::statusLabel($rdv['status']), Appointment::statusTone($rdv['status'])) ?>
    </p>
    <?php if ($dejaRedige): ?>
        <p class="g-notice"><?= gestion_icone('alert', 16) ?> Ce rendez-vous a déjà son compte rendu. Un complément s’ajoute comme note sur la fiche de la personne.</p>
        <?php if ($rdv['person_id']): ?><a class="g-btn g-btn-primary" href="<?= e(url('/gestion/personnes/' . $rdv['person_id'] . '/#notes')) ?>">Ouvrir la fiche</a><?php endif; ?>
    <?php else: ?>
        <form class="g-note-form" method="post" action="<?= e(url('/gestion/rendez-vous/' . $rdv['reference'] . '/compte-rendu/')) ?>" novalidate>
            <?= csrf_field() ?>
            <label class="g-form-row">Compte rendu
                <textarea class="g-input" name="body" rows="6" maxlength="<?= Note::LONGUEUR_MAX ?>" placeholder="Ce qui a été dit, ce qui est décidé, la prochaine étape…"><?= e(old('body')) ?></textarea>
            </label>
            <fieldset class="g-choice">
                <legend>Qui peut lire ce compte rendu ? <span class="g-muted">(choix obligatoire)</span></legend>
                <label class="g-choice-card is-ori"><input type="radio" name="visibility" value="interne"<?= old('visibility') === 'interne' ? ' checked' : '' ?>><span><strong><?= gestion_icone('lock', 14) ?> Interne</strong><small>L’équipe qui suit la personne. Jamais la personne, ni l’accueil, ni la comptabilité.</small></span></label>
                <label class="g-choice-card is-ok"><input type="radio" name="visibility" value="partagee"<?= old('visibility') === 'partagee' ? ' checked' : '' ?>><span><strong><?= gestion_icone('send', 14) ?> Partagé</strong><small>Visible dans l’espace de la personne ; un e-mail l’en avertit, sans le texte.</small></span></label>
            </fieldset>
            <?php if (isset($errors['note'])): ?><p class="g-field-error"><?= e($errors['note']) ?></p><?php endif; ?>
            <button class="g-btn g-btn-success" type="submit"><?= gestion_icone('check', 16) ?> Enregistrer le compte rendu</button>
            <p class="g-muted g-form-note"><?= $rdv['status'] === 'confirme' ? 'Le rendez-vous passera à « Honoré ». ' : '' ?>Un compte rendu ne se modifie pas.</p>
        </form>
    <?php endif; ?>
</section>
