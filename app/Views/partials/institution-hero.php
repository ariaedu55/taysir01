<?php
/**
 * @var string $title Nom de l'établissement, de la destination ou de l'annuaire
 * @var string|null $visual Nom du panorama dans images/institutions/
 * @var string|null $image et $portrait Adresses lorsqu'un autre jeu d'images est utilisé
 * @var string $context Type et lieu
 * @var string $lead Repère éditorial
 * @var array $trail Étapes intermédiaires du fil d’Ariane
 * @var string $current Libellé de la page courante
 * @var string $target Ancre de la présentation ou du catalogue
 * @var string $action Libellé de l’ancre
 */
$image ??= isset($visual) ? asset('images/institutions/' . $visual . '.webp') : null;
$portrait ??= isset($visual) ? asset('images/institutions/' . $visual . '-portrait.webp') : null;
?>
<header class="institution-hero<?= !empty($catalogue) ? ' institution-hero--catalogue' : '' ?>" aria-labelledby="institution-title">
    <?php if ($image): ?>
        <div class="institution-hero-media" aria-hidden="true" data-parallax="0.10">
            <picture>
                <?php if ($portrait && $portrait !== $image): ?>
                    <source media="(max-aspect-ratio: 4/5)" srcset="<?= e($portrait) ?>">
                <?php endif; ?>
                <img src="<?= e($image) ?>" alt="" fetchpriority="high">
            </picture>
        </div>
    <?php endif; ?>
    <div class="wrap institution-hero-inner">
        <div class="institution-hero-copy">
            <p class="institution-context" data-reveal="up"><?= e($context) ?></p>
            <h1 id="institution-title" data-split><?= e($title) ?></h1>
            <p class="institution-hero-lead" data-reveal="up" style="--delay: 200ms"><?= e($lead) ?></p>
            <a class="institution-discover" href="#<?= e($target) ?>" data-reveal="up" style="--delay: 300ms">
                <i aria-hidden="true"></i><?= e($action) ?>
            </a>
        </div>
        <div class="institution-hero-foot">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <?php foreach ($trail as [$label, $href]): ?>
                        <li><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <li aria-current="page"><?= e($current) ?></li>
                </ol>
            </nav>
            <?php if ($image): ?><small><?= e($caption ?? 'Image d’illustration') ?></small><?php endif; ?>
        </div>
    </div>
</header>
