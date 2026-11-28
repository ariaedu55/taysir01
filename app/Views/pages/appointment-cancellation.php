<?php
/**
 * Annulation d'un rendez-vous sans compte, par le lien reçu par e-mail (2B). L'ouverture du lien ne change rien :
 * seule la confirmation du formulaire annule et consomme le jeton.
 *
 * @var string|null $form        Identifiant du formulaire de cet onglet
 * @var array|null  $appointment Rendez-vous visé, si le lien est encore valide
 * @var bool|null   $result      true après une annulation réussie
 */
use App\Models\Appointment;

$quand = null;
if ($appointment !== null) {
    $zone = Appointment::zone($appointment['agency_id'] ? (int) $appointment['agency_id'] : null);
    $quand = $appointment['starts_at_utc']
        ? Appointment::local($appointment['starts_at_utc'], $zone, 'd/m/Y à H:i') . ', heure de ' . Appointment::city($appointment['agency_id'] ? (int) $appointment['agency_id'] : null)
        : date('d/m/Y', strtotime($appointment['preferred_date'])) . ' · ' . (Appointment::SLOTS[$appointment['preferred_slot']] ?? '');
}
?>
<main id="contenu" class="destinations-page service-page">
    <section class="wrap confirmation" aria-labelledby="annulation-titre">
        <div class="confirmation-card glass">
            <?php if ($result === true): ?>
                <h1 id="annulation-titre">Rendez-vous <em>annulé</em></h1>
                <p role="status">Le créneau est libéré. Vous pouvez reprendre rendez-vous quand vous le souhaitez.</p>
            <?php elseif ($form !== null && $appointment !== null): ?>
                <p class="kicker">Référence <?= e($appointment['reference']) ?></p>
                <h1 id="annulation-titre">Annuler ce <em>rendez-vous</em> ?</h1>
                <dl class="confirmation-summary">
                    <div><dt>Objet</dt><dd><?= e(Appointment::MOTIVES[$appointment['motive']] ?? '') ?></dd></div>
                    <div><dt>Échange</dt><dd><?= e(Appointment::MODES[$appointment['mode']] ?? '') ?></dd></div>
                    <div><dt>Date</dt><dd><?= e($quand) ?></dd></div>
                </dl>
                <p>Rien n’est annulé tant que vous ne confirmez pas.</p>
                <form class="actions" method="post" action="<?= e(url('/rendez_vous_orientation/annuler/')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="formulaire" value="<?= e($form) ?>">
                    <button class="button primary" type="submit">Confirmer l’annulation</button>
                    <a class="button light" href="<?= e(url('/')) ?>">Garder mon rendez-vous</a>
                </form>
            <?php else: ?>
                <h1 id="annulation-titre">Lien <em>inutilisable</em></h1>
                <p>Ce lien est invalide, expiré ou déjà utilisé. Contactez l’agence si nécessaire.</p>
            <?php endif; ?>
            <p><a href="<?= e(url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a></p>
        </div>
    </section>
</main>
