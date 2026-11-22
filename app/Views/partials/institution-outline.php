<?php
/**
 * @var array $sections
 * @var string $prefix
 */
?>
<?php if ($sections !== []): ?>
    <nav class="country-toc glass" aria-label="Sommaire" data-reveal="up">
        <p class="country-toc-title">Sommaire</p>
        <ol>
            <?php foreach ($sections as $i => $section): ?>
                <li><a href="#<?= e($prefix . ($i + 1)) ?>"><?= e($section['title']) ?></a></li>
            <?php endforeach; ?>
        </ol>
    </nav>
<?php endif; ?>
