<?php
/**
 * Gabarit commun des pages publiques.
 *
 * @var string        $slot          Contenu de la page, rendu par la vue
 * @var string|null   $title         Titre de la page, affiché « Titre — Taysir Orientation Voyage »
 * @var string|null   $documentTitle Titre complet de l'onglet, prioritaire sur $title
 * @var string|null   $description   Méta-description
 * @var string[]|null $styles        Feuilles de style propres à la page, relatives à public/assets/ (app.css est toujours chargé)
 * @var string[]|null $scripts       Scripts différés propres à la page, relatifs à public/assets/ (app.js est toujours chargé)
 * @var array|null    $photoCredits  Crédits photo affichés dans le pied de page
 * @var string|null   $canonical     Adresse canonique de la page (url('/…/'))
 * @var string|null   $ogImage       Image d'aperçu lors d'un partage
 * @var string|null   $robots        Consigne aux moteurs de recherche (« noindex » pour les pages privées)
 * @var bool|null     $heroHeader   true si la page commence par un bandeau sombre : l'en-tête se pose dessus, en blanc
 */
$siteName = config('site.name');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="<?= e($description ?? 'Taysir Orientation Voyage accompagne vos études au Sénégal et à l’étranger et vous aide à préparer votre voyage.') ?>">
    <title><?= e($documentTitle ?? (isset($title) ? $title . ' — ' . $siteName : $siteName)) ?></title>
    <link rel="icon" href="<?= e(asset('images/brand/logo-navy.webp')) ?>">
    <?php if (isset($robots)): ?>
        <meta name="robots" content="<?= e($robots) ?>">
    <?php endif; ?>
    <?php if (isset($canonical)): ?>
        <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($title ?? $siteName) ?>">
    <meta property="og:description" content="<?= e($description ?? '') ?>">
    <?php if (isset($ogImage)): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <script>document.documentElement.classList.add('js');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prata&amp;family=Mrs+Saint+Delafield&amp;family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,500&amp;display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach ($styles ?? [] as $style): ?>
        <link rel="stylesheet" href="<?= e(asset($style)) ?>">
    <?php endforeach; ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php foreach ($scripts ?? [] as $script): ?>
        <script src="<?= e(asset($script)) ?>" defer></script>
    <?php endforeach; ?>
</head>
<body class="<?= !empty($heroHeader) ? 'has-hero-header' : 'has-light-header' ?>">
    <a class="skip" href="#contenu">Aller au contenu</a>
    <?php partial('header', ['heroHeader' => !empty($heroHeader)]); ?>
    <?= $slot ?>
    <?php partial('footer', ['photoCredits' => $photoCredits ?? []]); ?>
</body>
</html>
