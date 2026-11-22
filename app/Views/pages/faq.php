<?php
/**
 * Questions fréquentes (config/faq.php), en accordéons natifs (<details>).
 *
 * @var list<array> $groups
 */
?>
<main id="contenu" class="destinations-page agency-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li>L’agence</li>
                    <li aria-current="page">Questions fréquentes</li>
                </ol>
            </nav>
            <h1 data-split>Questions <span class="accent">fréquentes</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">Services, tarifs, visa, espace en ligne, agences : l’essentiel pour bien démarrer votre projet avec Taysir.</p>
        </div>
    </section>

    <div class="wrap service-layout">
        <div class="service-main faq">
            <?php foreach ($groups as $g => [$theme, $questions]): ?>
                <section aria-labelledby="faq-<?= $g ?>">
                    <h2 class="orientation-title" id="faq-<?= $g ?>" data-reveal="up"><?= e($theme) ?></h2>
                    <div class="faq-list">
                        <?php foreach ($questions as [$question, $answer, $link]): ?>
                            <details class="faq-item glass" data-reveal="up">
                                <summary><?= e($question) ?></summary>
                                <div class="faq-answer">
                                    <p><?= e($answer) ?></p>
                                    <?php if ($link): ?><a class="faq-link" href="<?= e(url($link[1])) ?>"><?= e($link[0]) ?></a><?php endif; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <aside class="service-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">Une autre question ?</p>
                <h2>Écrivez-nous</h2>
                <p>Un conseiller vous répond, en agence, au téléphone ou sur WhatsApp.</p>
                <a class="button primary" href="<?= e(config('site.contact.whatsapp')) ?>">Écrire sur WhatsApp</a>
                <a class="button light" href="<?= e(url('/contact/')) ?>">Formulaire de contact</a>
            </div>
        </aside>
    </div>
</main>
