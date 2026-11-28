<?php
/**
 * Après journalisation : ouvre WhatsApp ou la messagerie de l'agent (phase 7, lot 7A).
 *
 * @var string $lien         https://wa.me/… ou mailto:…
 * @var string $canal        whatsapp ou email
 * @var string $retourFiche
 */
$whatsapp = $canal === 'whatsapp';
?>
<section class="g-card is-ok g-form-card" aria-labelledby="envoi-titre">
    <div class="g-card-head"><h3 id="envoi-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone($whatsapp ? 'chat' : 'send', 18) ?></span><?= $whatsapp ? 'WhatsApp s’ouvre…' : 'Votre messagerie s’ouvre…' ?></h3></div>
    <p>Si rien ne s’ouvre, utilisez le bouton. Vérifiez le message, puis envoyez-le.</p>
    <div class="g-case-tools">
        <a class="g-btn g-btn-success" href="<?= e($lien) ?>" data-g-ouvrir-lien<?= $whatsapp ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= $whatsapp ? 'Ouvrir WhatsApp' : 'Ouvrir l’e-mail' ?></a>
        <a class="g-btn" href="<?= e(url($retourFiche)) ?>">Retour à la fiche</a>
    </div>
</section>
<script>
    // Ouverture automatique ; le bouton reste en secours (bloqueur de fenêtres, appareil sans WhatsApp…)
    (function () { var l = document.querySelector('[data-g-ouvrir-lien]'); if (l) { window.location.href = l.href; } })();
</script>
