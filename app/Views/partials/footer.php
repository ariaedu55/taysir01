<?php
/** @var array $photoCredits Liste de [ville, auteur, source, licence, conditions] (source et licence peuvent être null) ; section masquée si vide. */
use App\Models\Destination;

$contact = config('site.contact');
?>
<footer class="site-footer">
    <div class="footer-glow" aria-hidden="true"></div>
    <div class="wrap footer-top">
        <div class="footer-brand" data-reveal="up">
            <img src="<?= e(asset('images/brand/logo-navy.webp')) ?>" width="159" height="56" alt="Taysir Orientation Voyage" loading="lazy">
            <p>Orientation, études et voyage : nous accompagnons élèves, étudiants et particuliers, du premier conseil jusqu’à l’arrivée.</p>
            <address>
                <?= e($contact['address']) ?><br>
                <a href="tel:<?= e(str_replace(' ', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a><br>
                <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>
            </address>
            <a class="button glass-button button-sm" href="<?= e($contact['whatsapp']) ?>">Écrire sur WhatsApp</a>
        </div>
        <nav class="footer-columns" aria-label="Liens du pied de page" data-stagger>
            <div data-reveal="up">
                <h2>Destinations</h2>
                <ul>
                    <?php foreach (array_slice(Destination::all(), 0, 6) as $country): ?>
                        <li><a href="<?= e(url('/' . $country['slug'] . '/')) ?>"><?= e($country['name']) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?= e(url('/destination/')) ?>">Toutes les destinations</a></li>
                </ul>
            </div>
            <div data-reveal="up">
                <h2>Accompagnement</h2>
                <ul>
                    <?php foreach (config('site.services') as [$name, , $path]): ?>
                        <li><a href="<?= e(legacy_url($path)) ?>"><?= e($name) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div data-reveal="up">
                <h2>L’agence</h2>
                <ul>
                    <?php foreach (config('site.agencyLinks') as [$name, $path]): ?>
                        <li><a href="<?= e(legacy_url($path)) ?>"><?= e($name) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div data-reveal="up">
                <h2>Le réseau Taysir</h2>
                <ul>
                    <?php foreach (config('site.network') as [$name, $href]): ?>
                        <li><a href="<?= e($href) ?>"><?= e($name) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
    </div>

    <p class="footer-wordmark" aria-hidden="true" data-reveal="up">Taysir</p>

    <div class="wrap footer-bottom">
        <p>© <?= date('Y') ?> Taysir Orientation Voyage · Dakar, Sénégal</p>
        <?php if (!empty($photoCredits)): ?>
            <details class="photo-credits">
                <summary>Crédits photo</summary>
                <ul>
                    <?php foreach ($photoCredits as [$city, $author, $source, $license, $terms]): ?>
                        <li><?php if ($source): ?><a href="<?= e($source) ?>"><?= e($city) ?></a><?php else: ?><?= e($city) ?><?php endif; ?> · <?= e($author) ?><?php if ($license): ?> · <a href="<?= e($terms) ?>"><?= e($license) ?></a><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Visuels des villes : illustrations photoréalistes générées par IA, converties en WebP. Drapeaux fournis par <a href="https://flagcdn.com/">Flagcdn</a>.</p>
            </details>
        <?php endif; ?>
    </div>
</footer>
