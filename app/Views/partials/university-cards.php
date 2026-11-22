<?php
/**
 * Grille de cartes universités (styles .destination-card de destination.css).
 *
 * @var list<array> $universities
 */
use App\Models\University;
?>
<ul class="destination-cards" data-stagger>
    <?php foreach ($universities as $university): ?>
        <li data-reveal="up">
            <a class="destination-card" href="<?= e(url('/' . $university['slug'] . '/')) ?>">
                <img src="<?= e(University::imageUrl($university)) ?>" alt="" loading="lazy">
                <span class="destination-card-go" aria-hidden="true"><svg viewBox="0 0 16 16" width="15" height="15"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="destination-card-body">
                    <span class="destination-card-region"><?= e($university['city']) ?></span>
                    <span class="destination-card-name"><?= e($university['acronym']) ?></span>
                    <span class="university-card-full"><?= e($university['name']) ?></span>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
