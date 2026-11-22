<?php
/**
 * Cartes de témoignages clients (database/content/agency/testimonials.php).
 *
 * @var list<array> $testimonials
 */
?>
<ul class="testimonial-grid" data-stagger>
    <?php foreach ($testimonials as $testimonial): ?>
        <?php $initials = implode('', array_map(static fn (string $part) => substr($part, 0, 1), array_slice(explode(' ', $testimonial['name']), 0, 2))); ?>
        <li data-reveal="up">
            <figure class="testimonial glass">
                <svg class="testimonial-quote" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" fill="currentColor"><path d="M9.6 6C6.5 7.3 4.5 9.9 4.5 13.4V18h6v-6H7.6c.2-1.9 1.4-3.3 3.2-4.2L9.6 6Zm9 0c-3.1 1.3-5.1 3.9-5.1 7.4V18h6v-6h-2.9c.2-1.9 1.4-3.3 3.2-4.2L18.6 6Z"/></svg>
                <blockquote><p><?= e($testimonial['text']) ?></p></blockquote>
                <figcaption>
                    <span class="testimonial-avatar" aria-hidden="true">
                        <?php if ($testimonial['photo']): ?>
                            <img src="<?= e(media_url($testimonial['photo'])) ?>" alt="" loading="lazy" decoding="async">
                        <?php else: ?>
                            <?= e(strtoupper($initials)) ?>
                        <?php endif; ?>
                    </span>
                    <span><strong><?= e($testimonial['name']) ?></strong><?= e($testimonial['role']) ?></span>
                </figcaption>
            </figure>
        </li>
    <?php endforeach; ?>
</ul>
