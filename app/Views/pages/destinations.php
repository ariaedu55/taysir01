<?php
/**
 * Liste des destinations : bandeau photographique, radar et cartes d'embarquement.
 *
 * @var list<array> $countries Pays du registre
 */
use App\Models\Destination;

// Radar : chaque pays à sa direction réelle depuis Dakar (cap initial) et à un rayon proportionnel à la racine de sa distance.
$scale = 1.69; // 200 unités pour 14 000 km
$points = [];
foreach ($countries as $country) {
    $km = Destination::distanceKm($country);
    if ($km === null) {
        continue;
    }
    $angle = deg2rad(Destination::bearing($country));
    $r = $scale * sqrt($km);
    $x = round($r * sin($angle), 1);
    $y = round(-$r * cos($angle), 1);
    // Arc légèrement bombé vers la gauche de la route
    $cx = round($x / 2 + $y * .18, 1);
    $cy = round($y / 2 - $x * .18, 1);
    $points[] = ['country' => $country, 'x' => $x, 'y' => $y, 'arc' => "M0 0Q{$cx} {$cy} {$x} {$y}", 'km' => Destination::distanceLabel($country)];
}
$farthest = array_reduce($countries, static fn (?array $carry, array $c): ?array => $carry === null || Destination::distanceKm($c) > Destination::distanceKm($carry) ? $c : $carry);
$rings = [2000, 5000, 10000];
$regions = array_unique(array_column($countries, 'region'));
$cover = asset('images/destinations/guides/annuaire');
?>
<main id="contenu" class="destinations-page">
    <?php partial('institution-hero', [
        'title' => 'Où voulez-vous étudier ?',
        'image' => $cover . '.webp',
        'portrait' => $cover . '-portrait.webp',
        'context' => 'Départ de Dakar',
        'lead' => 'Comparez les pays, explorez les possibilités d’études et préparez votre départ avec Taysir.',
        'trail' => [],
        'current' => 'Destinations',
        'target' => 'liste-destinations',
        'action' => 'Voir les ' . count($countries) . ' destinations',
        'catalogue' => true,
    ]); ?>

    <section class="destination-atlas" id="depuis-dakar" aria-labelledby="atlas-heading">
        <div class="wrap destinations-hero-grid">
            <div class="destination-atlas-copy">
                <h2 id="atlas-heading" data-split>Les destinations vues depuis Dakar</h2>
                <p class="destinations-lead" data-reveal="up">Le radar indique les directions et les distances à vol d’oiseau. Retrouvez ces repères sur chaque carte pour comparer les destinations.</p>
                <dl class="destinations-stats" data-reveal="up" style="--delay: 450ms">
                    <div><dt>Pays</dt><dd><?= count($countries) ?></dd></div>
                    <div><dt>Régions du monde</dt><dd><?= count($regions) ?></dd></div>
                    <div><dt>Jusqu’à <?= e($farthest['airport'][1]) ?></dt><dd><?= e(Destination::distanceLabel($farthest)) ?></dd></div>
                </dl>
            </div>

            <figure class="radar" data-radar data-reveal="scale" style="--delay: 200ms">
                <svg viewBox="-200 -232 380 292" role="img" aria-labelledby="radar-titre">
                    <title id="radar-titre">Les <?= count($points) ?> destinations vues depuis Dakar, selon leur direction et leur distance réelles</title>
                    <g class="radar-rings">
                        <?php foreach ($rings as $km): $r = round($scale * sqrt($km), 1); ?>
                            <circle r="<?= $r ?>"/>
                            <text x="<?= round(-$r * .985, 1) ?>" y="<?= round($r * .174 + 4, 1) ?>"><?= number_format($km, 0, ',', ' ') ?> km</text>
                        <?php endforeach; ?>
                        <path class="radar-north" d="M0 -222V-212"/>
                        <text class="radar-north-label" x="0" y="-224">N</text>
                    </g>
                    <line class="radar-sweep" x1="0" y1="0" x2="0" y2="-230"/>
                    <?php foreach ($points as $i => $p): $right = $p['x'] >= 0; ?>
                        <g class="radar-item" data-radar-item="<?= e($p['country']['slug']) ?>" style="--i: <?= $i ?>">
                            <path class="radar-arc" d="<?= $p['arc'] ?>" pathLength="100"/>
                            <path class="radar-trail" d="<?= $p['arc'] ?>" pathLength="1"/>
                            <circle class="radar-dot" cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="3.6"/>
                            <text class="radar-label" x="<?= $p['x'] + ($right ? 9 : -9) ?>" y="<?= $p['y'] + 4 ?>" text-anchor="<?= $right ? 'start' : 'end' ?>"><?= e($p['country']['name']) ?> <tspan><?= e($p['km']) ?></tspan></text>
                        </g>
                    <?php endforeach; ?>
                    <g class="radar-origin">
                        <circle class="radar-pulse" r="6"/>
                        <circle r="5"/>
                        <text y="20" text-anchor="middle">Dakar</text>
                    </g>
                    <g class="radar-plane"><image href="<?= e(asset('images/journey/avion-route.webp')) ?>" x="-11" y="-11" width="22" height="22" transform="rotate(90)"/></g>
                </svg>
                <figcaption>Le rayon du radar suit la racine de la distance pour que les pays les plus proches restent lisibles.</figcaption>
            </figure>
        </div>
    </section>

    <section class="wrap destinations-list" id="liste-destinations" aria-labelledby="destinations-heading">
        <div class="destinations-list-head">
            <h2 id="destinations-heading" data-split>Choisir un pays</h2>
            <a href="#depuis-dakar">Voir le radar</a>
        </div>
        <div class="destinations-filters" data-region-controls hidden>
            <label for="destination-region">Filtrer par région</label>
            <select id="destination-region">
                <option value="">Toutes les régions</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?= e($region) ?>"><?= e($region) ?></option>
                <?php endforeach; ?>
            </select>
            <p role="status" data-region-count><?= count($countries) ?> pays affichés</p>
        </div>
        <?php partial('destination-cards', ['countries' => $countries]); ?>
    </section>

    <section class="wrap country-cta" aria-labelledby="hesitation" data-reveal="scale">
        <div>
            <h2 id="hesitation">Vous hésitez entre plusieurs pays ?</h2>
            <p>Un conseiller compare avec vous les formations, les budgets et les démarches de chaque destination.</p>
        </div>
        <div class="actions">
            <a class="button primary" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
            <a class="button outline-light" href="<?= e(config('site.contact.whatsapp')) ?>">Écrire sur WhatsApp</a>
        </div>
    </section>
</main>
