<?php
/**
 * @var array $sections
 * @var string $prefix
 */
?>
<?php foreach ($sections as $i => $section): ?>
    <details class="country-section" data-reveal="up" id="<?= e($prefix . ($i + 1)) ?>"<?= $i === 0 ? ' open' : '' ?>>
        <summary><h3><?= e($section['title']) ?></h3></summary>
        <div class="prose"><?= $section['html'] ?></div>
    </details>
<?php endforeach; ?>
