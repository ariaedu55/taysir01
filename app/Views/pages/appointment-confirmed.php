<?php
/**
 * Confirmation après une demande de rendez-vous.
 *
 * @var array      $appointment
 * @var array|null $user
 */
use App\Models\Appointment;

$date = new DateTimeImmutable($appointment['preferred_date']);
$days = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
$months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$dateLabel = $days[(int) $date->format('N')] . ' ' . $date->format('j') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
?>
<main id="contenu" class="destinations-page service-page">
    <section class="wrap confirmation" aria-labelledby="confirmation-titre">
        <div class="confirmation-card glass" data-reveal="scale">
            <span class="confirmation-check" aria-hidden="true"><svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
            <p class="kicker">Référence <?= e($appointment['reference']) ?></p>
            <h1 id="confirmation-titre">Merci <?= e($appointment['first_name']) ?>, <em>c’est noté</em></h1>
            <p>Votre demande de rendez-vous est enregistrée. Un conseiller vous contacte au <strong><?= e($appointment['phone']) ?></strong> pour confirmer l’heure exacte.</p>
            <dl class="confirmation-summary">
                <div><dt>Objet</dt><dd><?= e(Appointment::MOTIVES[$appointment['motive']] ?? '') ?></dd></div>
                <div><dt>Échange</dt><dd><?= e(Appointment::MODES[$appointment['mode']] ?? '') ?></dd></div>
                <div><dt>Jour souhaité</dt><dd><?= e(ucfirst($dateLabel)) ?></dd></div>
                <div><dt>Moment</dt><dd><?= e(Appointment::SLOTS[$appointment['preferred_slot']] ?? '') ?></dd></div>
            </dl>
            <div class="actions">
                <?php if ($user !== null): ?>
                    <a class="button primary" href="<?= e(url('/mon-espace/demandes/')) ?>">Voir mes rendez-vous</a>
                <?php else: ?>
                    <a class="button primary" href="<?= e(url('/inscription/')) ?>">Créer mon compte Taysir</a>
                <?php endif; ?>
                <a class="button light" href="<?= e(url('/')) ?>">Retour à l’accueil</a>
            </div>
        </div>
    </section>
</main>
