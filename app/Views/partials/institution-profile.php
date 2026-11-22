<?php
/**
 * @var string $title
 * @var string $focus
 * @var string|null $image
 * @var string|null $source
 * @var string $intro HTML nettoyé lors de l'import
 * @var string|null $former
 * @var bool $school
 */
?>
<section class="wrap institution-profile<?= $image ? ' has-original' : '' ?>" id="presentation" aria-labelledby="presentation-title">
    <div class="institution-profile-copy" data-reveal="up">
        <?php if (!empty($focus)): ?><p class="kicker"><?= e($focus) ?></p><?php endif; ?>
        <h2 id="presentation-title"><?= e($title) ?></h2>
        <?php if (!empty($former)): ?>
            <p class="institution-former">Anciennement <?= e($former) ?></p>
        <?php endif; ?>
        <?php if ($intro !== ''): ?>
            <div class="prose"><?= $intro ?></div>
        <?php endif; ?>
        <?php if ($source): ?>
            <a class="institution-source-link" href="<?= e($source) ?>"><?= e($sourceLabel ?? 'Consulter la source institutionnelle') ?> <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </div>
    <?php if ($image): ?>
        <figure class="institution-original<?= $school ? ' institution-original--school' : '' ?>" data-reveal="up">
            <img src="<?= e($image) ?>" alt="" loading="lazy" decoding="async">
            <figcaption>Visuel d’origine · Taysir Orientation Voyage</figcaption>
        </figure>
    <?php endif; ?>
</section>
