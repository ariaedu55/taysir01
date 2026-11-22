<?php
/** @var array $navigation Rubriques du menu principal */
$contact = config('site.contact');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isCurrent = static fn (string $href): bool => $href === $currentPath;
$destinationsUrl = url('/destination/');
$contactUrl = legacy_url('/contact/');

$categories = [
    'Après le bac' => ['Universités, écoles et débouchés', 'M2 9l10-5 10 5-10 5L2 9Zm4 2v5c4 3 8 3 12 0v-5m4-2v6'],
    'S’orienter' => ['Filières, secteurs et métiers', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm4-14-2.5 5.5L8 16l2.5-5.5L16 8Z'],
    'Études à l’étranger' => ['Admission, visa et logement', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20ZM2 12h20M12 2c5 5 5 15 0 20C7 17 7 7 12 2Z'],
    'Offres' => ['Stages, emplois et conseils', 'M9 7V5h6v2M3 7h18v13H3V7Zm0 5c5 4 13 4 18 0m-9 2v3'],
    'Activités' => ['Salons et événements', 'M3 5h18v16H3V5Zm0 5h18M7 3v4m10-4v4m-9 8h2m4 0h2m-8 3h2'],
];
$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>';
?>
<dialog class="mobile-menu" id="mobile-menu" aria-labelledby="mobile-menu-title" data-mobile-menu>
    <div class="mobile-menu-frame">
        <div class="mobile-menu-top">
            <a class="mobile-menu-brand" href="<?= e(url('/')) ?>" aria-label="Taysir Orientation Voyage, retour à l’accueil">
                <img src="<?= e(asset('images/brand/logo-navy.webp')) ?>" width="127" height="45" alt="">
            </a>
            <h2 id="mobile-menu-title">Navigation</h2>
            <form method="dialog">
                <button type="submit" class="mobile-menu-close" aria-label="Fermer le menu" autofocus>
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </form>
        </div>

        <div class="mobile-menu-scroll">
            <nav class="mobile-menu-nav" aria-label="Navigation principale">
                <a class="mobile-menu-destinations" href="<?= e($destinationsUrl) ?>"<?= $isCurrent($destinationsUrl) ? ' aria-current="page"' : '' ?>>
                    <img src="<?= e(asset('images/destinations/guides/annuaire-portrait.webp')) ?>" width="753" height="941" loading="lazy" decoding="async" alt="">
                    <span class="mobile-menu-feature-text">
                        <strong>Destinations</strong>
                        <span>Comparer les pays</span>
                    </span>
                    <span class="mobile-nav-indicator" aria-hidden="true"><?= $arrow ?></span>
                </a>

                <ul class="mobile-nav">
                    <?php foreach ($navigation as [$label, $items]): ?>
                        <?php
                        [$description, $icon] = $categories[$label] ?? ['', 'M5 12h14m-7-7v14'];
                        $links = array_map(static fn (array $item): array => [$item[0], legacy_url($item[1])], $items);
                        $active = !$isCurrent($destinationsUrl) && in_array($currentPath, array_column($links, 1), true);
                        ?>
                        <li>
                            <details name="taysir-navigation"<?= $active ? ' open' : '' ?>>
                                <summary>
                                    <span class="mobile-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="<?= e($icon) ?>"/></svg></span>
                                    <span class="mobile-nav-label"><strong><?= e($label) ?></strong><?php if ($description): ?><small><?= e($description) ?></small><?php endif; ?></span>
                                    <span class="mobile-nav-indicator"><?= $chevron ?></span>
                                </summary>
                                <ul class="mobile-nav-links">
                                    <?php foreach ($links as [$name, $href]): ?>
                                        <li><a href="<?= e($href) ?>"<?= $isCurrent($href) ? ' aria-current="page"' : '' ?>><?= e($name) ?><?= $arrow ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="mobile-menu-contact">
                <a href="<?= e($contactUrl) ?>"<?= $isCurrent($contactUrl) ? ' aria-current="page"' : '' ?>>Contact</a>
                <a href="tel:<?= e(str_replace(' ', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a>
                <a href="<?= e($contact['whatsapp']) ?>">WhatsApp</a>
            </div>
        </div>

        <div class="mobile-menu-actions">
            <a class="button primary" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
            <a class="button outline" href="<?= e($accountUrl) ?>"><?= e($accountLabel) ?></a>
            <?php if ($guest): ?>
                <a class="mobile-menu-register" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
            <?php endif; ?>
        </div>
    </div>
</dialog>
