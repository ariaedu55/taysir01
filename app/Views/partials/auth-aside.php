<?php
/**
 * Panneau visuel des pages d'authentification.
 *
 * @var string $photo   Photo de public/assets/images/destinations/ (sans extension)
 * @var string $caption Légende du lieu
 */
$benefits = [
    'Suivez vos demandes d’admission, de visa et de logement',
    'Prenez rendez-vous avec un conseiller',
    'Déposez vos documents en toute sécurité',
];
?>
<aside class="auth-visual" aria-hidden="true">
    <img src="<?= e(asset('images/destinations/' . $photo . '.webp')) ?>" alt="" fetchpriority="high">
    <div class="auth-visual-content hero-panel">
        <img class="auth-logo" src="<?= e(asset('images/brand/logo-navy.webp')) ?>" width="136" height="48" alt="">
        <p class="auth-quote">Votre projet d’études et de voyage, <em>réuni dans un seul espace.</em></p>
        <ul class="auth-benefits">
            <?php foreach ($benefits as $benefit): ?>
                <li><span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><?= e($benefit) ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="auth-caption"><?= e($caption) ?></p>
    </div>
</aside>
