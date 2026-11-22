<?php
/**
 * Parcours de vol commun aux fiches et à l’accueil.
 *
 * @var string|null $destination Fin de phrase (« en Tunisie », « à l’UCAD »).
 */
$arrow = '<span class="arrow" aria-hidden="true"><svg viewBox="0 0 16 16" width="14" height="14"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
$icon = static fn (string $paths): string => '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
$plane = '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>';
// Le lien du service identifie la composition, même si l’ordre des étapes évolue.
$journeyCards = [
    '/rendez_vous_orientation/' => ['orientation', 'Choisir mon parcours', '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>'],
    '/demande-dadmission/' => ['admission', 'Préparer ma candidature', '<path d="M2 9.5 12 5l10 4.5-10 4.5z"/><path d="M6 11.5V16c3 2 9 2 12 0v-4.5"/><path d="M22 9.5V14"/>'],
    '/demande-de-prise-en-charge/' => ['prise', 'Préparer mes justificatifs', '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="M9 12l2 2 4-4"/>'],
    '/demande-de-visa/' => ['visa', 'Préparer ma demande de visa', '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M9 17h6"/>'],
    '/demande-de-logement/' => ['logement', 'Trouver un logement', '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>'],
    '/vente-billet-davion/' => ['billet', 'Réserver mon vol', $plane],
];
// Code-barres décoratif du talon de la carte d’embarquement.
$barcode = static function (string $seed): string {
    $hash = md5($seed);
    $x = 0;
    $bars = '';
    for ($k = 0; $k < 22; $k++) {
        $w = 1 + hexdec($hash[$k]) % 3;
        $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="28"/>';
        $x += $w + 1 + hexdec($hash[($k + 7) % 32]) % 2;
    }

    return '<svg class="pass-barcode" viewBox="0 0 ' . $x . ' 28" preserveAspectRatio="none" aria-hidden="true">' . $bars . '</svg>';
};
$cloud = '<svg viewBox="0 0 200 80" aria-hidden="true"><path d="M44 70h118c18 0 30-11 30-25s-12-25-28-25c-3 0-6 0-9 1C149 9 136 2 120 2c-18 0-33 11-37 26-4-2-9-3-14-3-16 0-29 11-30 25C26 52 14 58 14 64c0 3 12 6 30 6z"/></svg>';
$services = config('site.services');
$headingId = isset($destination) ? 'accompagnement' : 'etapes-titre';
$intro = isset($destination)
    ? "Un seul interlocuteur, de votre choix de formation jusqu’à votre arrivée {$destination}."
    : 'Un seul interlocuteur, du choix de la formation jusqu’à votre arrivée. Chaque étape est préparée avec vous.';
?>
<section class="journey" aria-labelledby="<?= e($headingId) ?>" data-journey-section>
    <span class="journey-cloud cloud-1" aria-hidden="true" data-parallax="0.18"><?= $cloud ?></span>
    <span class="journey-cloud cloud-2" aria-hidden="true" data-parallax="-0.12"><?= $cloud ?></span>
    <span class="journey-cloud cloud-3" aria-hidden="true" data-parallax="0.28"><?= $cloud ?></span>

    <div class="wrap journey-head">
        <p class="kicker" data-reveal="up">Accompagnement</p>
        <h2 id="<?= e($headingId) ?>" data-split>Votre parcours, <em>étape par étape</em></h2>
        <p class="journey-intro" data-reveal="up" style="--delay: 250ms"><?= e($intro) ?></p>
    </div>

    <div class="wrap journey-hud-zone">
        <div class="journey-hud" data-reveal="up" style="--delay: 350ms" aria-hidden="true">
            <div class="hud-city">
                <small>Départ</small>
                <strong>DKR</strong>
                <span>Dakar</span>
            </div>
            <div class="hud-track">
                <i class="hud-fill"></i>
                <img class="hud-plane" src="<?= e(asset('images/journey/avion-dkr.webp')) ?>" alt="" loading="lazy" decoding="async">
            </div>
            <div class="hud-city is-end">
                <small>Arrivée</small>
                <strong>✦</strong>
                <span>Votre campus</span>
            </div>
            <div class="hud-count">
                <small>Étape</small>
                <strong><span data-journey-count>01</span><em>/<?= str_pad((string) count($services), 2, '0', STR_PAD_LEFT) ?></em></strong>
            </div>
        </div>

        <ol class="journey-route" data-journey>
            <li class="route-line" role="presentation" aria-hidden="true">
                <svg class="route-map" focusable="false">
                    <path class="route-map-guide"/>
                    <path class="route-map-progress" pathLength="1"/>
                </svg>
                <img class="route-plane" src="<?= e(asset('images/journey/avion-route.webp')) ?>" alt="" loading="lazy" decoding="async">
            </li>
            <?php foreach ($services as $i => [$name, $text, $path]): ?>
                <?php
                $number = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                [$variant, $action, $symbol] = $journeyCards[$path] ?? ['', 'En savoir plus', $plane];
                ?>
                <li class="journey-step <?= $i % 2 === 0 ? 'is-left' : 'is-right' ?>" data-step>
                    <span class="route-node" data-reveal="pop" aria-hidden="true"><span><?= $number ?></span></span>
                    <a class="pass <?= $variant === '' ? '' : 'pass--' . e($variant) ?>" data-reveal="pass" href="<?= e(legacy_url($path)) ?>">
                        <?php if ($variant !== ''): ?>
                            <span class="pass-art" aria-hidden="true">
                                <img src="<?= e(asset('images/journey/' . $variant . '.webp')) ?>" alt="" loading="lazy" decoding="async">
                            </span>
                        <?php endif; ?>
                        <div class="pass-content">
                            <div class="pass-head">
                                <span class="pass-icon"><?= $icon($symbol) ?></span>
                                <span class="pass-stamp" aria-hidden="true">Étape<br>validée</span>
                            </div>
                            <h3><?= e($name) ?></h3>
                            <p><?= e($text) ?></p>
                        </div>
                        <span class="journey-pass-actions">
                            <span class="journey-pass-action"><?= e($action) ?><?= $arrow ?></span>
                            <?php if ($variant === 'billet'): ?>
                                <?= $barcode($name) ?>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <div class="wrap journey-cta" data-reveal="up">
        <p>Prêt à décoller ? Un conseiller construit votre plan de vol avec vous.</p>
        <a class="button primary" data-magnetic href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Parler à un conseiller<?= $arrow ?></a>
    </div>
</section>
