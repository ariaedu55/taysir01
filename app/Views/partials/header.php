<?php
/** @var bool $heroHeader En-tête posé sur un bandeau sombre au chargement */
$navigation = config('site.navigation');
$account = \App\Core\Auth::user();
$accountUrl = $account === null ? url('/connexion/') : url(in_array($account['role'], ['admin', 'conseiller'], true) ? '/admin/' : '/mon-espace/');
$accountLabel = $account === null ? 'Connexion' : 'Mon espace';
$chevron = '<svg class="chevron" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5 6 7.5 9 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<header class="site-header<?= $heroHeader ? ' is-on-hero' : '' ?>" data-header>
    <div class="site-header-bar">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="Taysir Orientation Voyage, retour à l’accueil">
            <img class="brand-logo brand-logo-white" src="<?= e(asset('images/brand/logo-white.webp')) ?>" width="159" height="56" alt="">
            <img class="brand-logo brand-logo-navy" src="<?= e(asset('images/brand/logo-navy.webp')) ?>" width="159" height="56" alt="">
        </a>

        <nav class="site-nav" aria-label="Navigation principale">
            <ul>
                <li><a class="nav-link" href="<?= e(url('/destination/')) ?>">Destinations</a></li>
                <?php foreach ($navigation as $i => [$label, $items]): ?>
                    <li class="nav-item" data-nav-item>
                        <button class="nav-trigger" type="button" aria-expanded="false" aria-controls="nav-panel-<?= $i ?>"><?= e($label) ?><?= $chevron ?></button>
                        <div class="nav-panel<?= count($items) > 8 ? ' is-wide' : '' ?>" id="nav-panel-<?= $i ?>">
                            <ul>
                                <?php foreach ($items as [$name, $path]): ?>
                                    <li><a href="<?= e(legacy_url($path)) ?>"><?= e($name) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <a class="header-link header-account" href="<?= e($accountUrl) ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg><?= e($accountLabel) ?></a>
            <a class="button primary button-sm header-cta" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Rendez-vous</a>
            <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mobile-menu" aria-haspopup="dialog" data-menu-toggle>
                Menu
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 8h16M8 16h12"/></svg>
            </button>
        </div>
    </div>

    <?php partial('mobile-menu', [
        'navigation' => $navigation,
        'accountUrl' => $accountUrl,
        'accountLabel' => $accountLabel,
        'guest' => $account === null,
        'chevron' => $chevron,
    ]); ?>
</header>
