<?php
/**
 * Gabarit de l'espace équipe RÉEL (/gestion/), même coquille que la maquette validée en 1A
 * (docs/gestion-phase-1.md) mais alimentée par le compte connecté (app/Services/Coquille.php) :
 * navigation et compteurs du périmètre, recherche limitée au périmètre, déconnexion.
 *
 * @var string $slot     Contenu de la page
 * @var string $title    Titre de la page
 * @var array  $coquille Données de Coquille::pour()
 */
use App\Core\Session;

require_once BASE_PATH . '/app/Views/gestion/icones.php';

$c = $coquille;
$initiales = mb_substr($c['prenom'], 0, 1) . mb_substr($c['nom'], 0, 1);
$flashes = array_filter(['ok' => Session::get('_success'), 'info' => Session::get('_notice')]);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title><?= e($title) ?> — Gestion Taysir</title>
    <link rel="icon" href="<?= e(asset('images/brand/logo-navy.webp')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&amp;display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/gestion.css')) ?>">
    <script src="<?= e(asset('js/gestion.js')) ?>" defer></script>
</head>
<body class="g-body">
<a class="skip" href="#g-contenu">Aller au contenu</a>
<div class="g-shell" data-g-shell>

    <aside class="g-sidebar" id="g-sidebar" aria-label="Navigation de l’espace équipe">
        <div class="g-brand">
            <a href="<?= e(url('/gestion/')) ?>" class="g-brand-logo" aria-label="Taysir, Ma journée">
                <img src="<?= e(asset('images/brand/logo-navy.webp')) ?>" width="124" height="44" alt="Taysir Orientation Voyage">
            </a>
            <button class="g-icon-btn g-collapse" type="button" data-g-collapse aria-label="Réduire la barre latérale" title="Réduire la barre latérale"><?= gestion_icone('collapse', 18) ?></button>
            <button class="g-icon-btn g-drawer-close" type="button" data-g-drawer-close aria-label="Fermer le menu"><?= gestion_icone('close', 18) ?></button>
        </div>
        <p class="g-agency"><span class="g-agency-dot" aria-hidden="true"></span><span class="g-hide-collapsed"><?= e($c['agence']) ?></span></p>

        <nav class="g-nav">
            <?php foreach ($c['nav'] as [$groupe, $famille, $items]): ?>
                <?php if ($groupe !== ''): ?><p class="g-nav-title is-<?= e($famille) ?>"><span class="g-nav-mark" aria-hidden="true"></span><?= e($groupe) ?></p><?php endif; ?>
                <ul class="is-<?= e($famille) ?>">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <a class="g-nav-link<?= $item['actif'] ? ' is-active' : '' ?>" href="<?= e(url($item['url'])) ?>"<?= $item['actif'] ? ' aria-current="page"' : '' ?> title="<?= e($item['label']) ?>">
                                <span class="g-nav-icon" aria-hidden="true"><?= gestion_icone($item['icone'], 17) ?></span>
                                <span class="g-nav-label"><?= e($item['label']) ?></span>
                                <?php if ($item['compteur'] !== null): ?><span class="g-count" aria-label="<?= (int) $item['compteur'] ?> en attente"><?= (int) $item['compteur'] ?></span><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <div class="g-sidebar-foot">
            <div class="g-me">
                <span class="g-avatar" aria-hidden="true"><?= e($initiales) ?></span>
                <span class="g-hide-collapsed"><strong><?= e($c['prenom'] . ' ' . $c['nom']) ?></strong><small><?= e($c['intitule']) ?></small></span>
            </div>
            <div class="g-foot-links g-hide-collapsed">
                <a class="g-client-link" href="<?= e(url('/')) ?>"><?= gestion_icone('home', 16) ?> Voir le site</a>
                <form method="post" action="<?= e(url('/deconnexion/')) ?>">
                    <?= csrf_field() ?>
                    <button class="g-client-link g-logout" type="submit"><?= gestion_icone('logout', 16) ?> Se déconnecter</button>
                </form>
            </div>
        </div>
    </aside>
    <div class="g-scrim" data-g-drawer-close></div>

    <div class="g-main">
        <header class="g-topbar">
            <button class="g-icon-btn g-menu-btn" type="button" data-g-drawer-open aria-controls="g-sidebar" aria-label="Ouvrir le menu"><?= gestion_icone('menu') ?></button>
            <div class="g-heading">
                <p><?= e($c['intitule']) ?></p>
                <h1><?= e($title) ?></h1>
            </div>
            <?php if ($c['recherche']): ?>
                <button class="g-search" type="button" data-g-search-open aria-haspopup="dialog">
                    <?= gestion_icone('search', 18) ?>
                    <span>Rechercher un dossier, un nom, un téléphone…</span>
                    <kbd>Ctrl K</kbd>
                </button>
            <?php endif; ?>
        </header>

        <main id="g-contenu" class="g-content" tabindex="-1">
            <?php foreach ($flashes as $ton => $message): ?>
                <p class="g-flash is-<?= $ton === 'ok' ? 'ok' : 'rdv' ?>" role="status"><?= gestion_icone($ton === 'ok' ? 'check' : 'alert', 17) ?> <?= e($message) ?></p>
            <?php endforeach; ?>
            <?= $slot ?>
        </main>
    </div>

    <nav class="g-tabbar" aria-label="Navigation rapide">
        <a href="<?= e(url('/gestion/')) ?>"<?= $c['actif'] === 'gestion' ? ' aria-current="page"' : '' ?>><?= gestion_icone('sun') ?><span>Ma journée</span></a>
        <?php if ($c['raccourci'] !== null): ?>
            <a href="<?= e(url($c['raccourci'][2])) ?>"<?= $c['actif'] === $c['raccourci'][3] ? ' aria-current="page"' : '' ?>><?= gestion_icone($c['raccourci'][1]) ?><span><?= e($c['raccourci'][0]) ?></span></a>
            <button type="button" class="g-tab-search" data-g-search-open><?= gestion_icone('search') ?><span>Rechercher</span></button>
        <?php else: ?>
            <a href="<?= e(url('/')) ?>"><?= gestion_icone('home') ?><span>Site</span></a>
            <span aria-hidden="true"></span>
        <?php endif; ?>
        <button type="button" data-g-drawer-open aria-controls="g-sidebar"><?= gestion_icone('menu') ?><span>Menu</span></button>
    </nav>
</div>

<?php if ($c['recherche']): ?>
    <div class="g-palette" role="dialog" aria-modal="true" aria-label="Recherche dans votre espace" hidden data-g-palette data-g-search-url="<?= e(url('/gestion/recherche/')) ?>">
        <div class="g-palette-box">
            <label class="g-palette-field"><?= gestion_icone('search') ?><span class="sr-only">Rechercher</span>
                <input type="search" placeholder="Nom, téléphone, e-mail, référence…" autocomplete="off" data-g-palette-input>
                <kbd>Échap</kbd>
            </label>
            <ul class="g-palette-results" role="listbox" data-g-palette-results></ul>
            <p class="g-palette-hint">Résultats limités à votre périmètre. ↑ ↓ pour choisir, Entrée pour ouvrir.</p>
        </div>
    </div>
<?php endif; ?>
<div class="g-toasts" aria-live="polite" data-g-toasts></div>
</body>
</html>
