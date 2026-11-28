<?php
require_once BASE_PATH . '/app/Views/gestion/icones.php';
$sections = [
    ['accueil', 'Mon espace', 'home', '/mon-espace/'],
    ['demandes', 'Mes demandes', 'folder', '/mon-espace/demandes/'],
    ['rendez-vous', 'Rendez-vous', 'calendar', '/mon-espace/demandes/#rdv-titre'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title><?= e($title) ?> — Taysir Orientation Voyage</title>
    <link rel="icon" href="<?= e(asset('images/brand/logo-navy.webp')) ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&amp;display=swap">
    <?php foreach (['app', 'auth', 'portal', 'gestion-client'] as $sheet): ?>
        <link rel="stylesheet" href="<?= e(asset('css/' . $sheet . '.css')) ?>">
    <?php endforeach; ?>
    <?php foreach ($scripts ?? [] as $script): ?><script src="<?= e(asset($script)) ?>" defer></script><?php endforeach; ?>
</head>
<body class="client-workspace">
    <a class="skip" href="#contenu">Aller au contenu</a>
    <header class="client-header">
        <div class="client-wrap client-header-row">
            <a class="client-brand" href="<?= e(url('/mon-espace/')) ?>"><img src="<?= e(asset('images/brand/logo-navy.webp')) ?>" width="128" height="44" alt="Taysir Orientation Voyage"></a>
            <nav class="client-nav" aria-label="Mon espace">
                <?php foreach ($sections as [$key, $label, $icon, $path]): ?>
                    <a href="<?= e(url($path)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= gestion_icone($icon, 18) ?><span><?= e($label) ?></span></a>
                <?php endforeach; ?>
            </nav>
            <form method="post" action="<?= e(url('/deconnexion/')) ?>">
                <?= csrf_field() ?>
                <button class="client-signout" type="submit"><?= gestion_icone('logout', 18) ?><span>Se déconnecter</span></button>
            </form>
        </div>
    </header>
    <?= $slot ?>
    <footer class="client-footer"><div class="client-wrap"><a href="<?= e(url('/')) ?>">Retour au site</a><a href="<?= e(url('/contact/')) ?>">Contacter l’agence</a></div></footer>
</body>
</html>
