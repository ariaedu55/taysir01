<?php
/**
 * Cartes d'embarquement des pays (docs/cartes-voyage.md) : photo, puis talon avec la route depuis Dakar,
 * la distance à vol d'oiseau et la région. data-pass relie la carte au radar de /destination/.
 *
 * @var list<array> $countries
 */
use App\Models\Destination;

[$originCode, $originCity] = Destination::ORIGIN;
$plane = e(asset('images/journey/avion-route.webp'));
?>
<ul class="pass-cards" data-stagger>
    <?php foreach ($countries as $country): ?>
        <?php
        $image = Destination::guideImageUrl($country);
        $airport = $country['airport'];
        $km = Destination::distanceLabel($country);
        ?>
        <li data-reveal="up" data-country-region="<?= e($country['region']) ?>">
            <a class="pass-card" href="<?= e(url('/' . $country['slug'] . '/')) ?>" data-pass="<?= e($country['slug']) ?>" aria-label="<?= e($country['name'] . ($km ? ' : ' . $km . ' depuis Dakar' : '')) ?>">
                <span class="pass-photo">
                    <?php if ($image): ?>
                        <img src="<?= e($image) ?>" alt="" loading="lazy" decoding="async"<?= isset($country['focus']) ? ' style="object-position: ' . e($country['focus']) . ' 50%"' : '' ?>>
                    <?php endif; ?>
                    <span class="pass-title">
                        <?php if ($country['flag']): ?>
                            <img src="<?= e(asset('images/destinations/' . $country['flag'] . '.webp')) ?>" alt="" width="26" height="17">
                        <?php endif; ?>
                        <?= e($country['name']) ?>
                    </span>
                </span>
                <span class="pass-stub" aria-hidden="true">
                    <?php if ($airport): ?>
                        <span class="pass-route">
                            <span class="pass-code"><?= e($originCode) ?><small><?= e($originCity) ?></small></span>
                            <span class="pass-line"><img src="<?= $plane ?>" alt="" width="22" height="22" loading="lazy"></span>
                            <span class="pass-code"><?= e($airport[0]) ?><small><?= e($airport[1]) ?></small></span>
                        </span>
                    <?php endif; ?>
                    <span class="pass-meta">
                        <?php if ($km): ?><span><small>Distance</small><?= e($km) ?></span><?php endif; ?>
                        <span><small>Région</small><?= e($country['region']) ?></span>
                        <span class="pass-go"><svg viewBox="0 0 16 16" width="16" height="16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </span>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
