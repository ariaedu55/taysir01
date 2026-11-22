<?php
/**
 * Documents d'une demande, téléchargeables par leur propriétaire et par l'équipe.
 *
 * @var list<array> $documents
 * @var string      $base  Adresse de téléchargement, suivie de l'identifiant du document
 */
?>
<section class="account glass" aria-labelledby="documents-titre" data-reveal="up">
    <h2 id="documents-titre">Documents</h2>
    <?php if ($documents === []): ?>
        <p class="portal-empty">Aucun document joint.</p>
    <?php else: ?>
        <ul class="document-list">
            <?php foreach ($documents as $document): ?>
                <li>
                    <a href="<?= e(url($base . $document['id'] . '/')) ?>">
                        <span class="document-type"><?= $document['mime'] === 'application/pdf' ? 'PDF' : 'IMG' ?></span>
                        <span class="document-name"><?= e($document['original_name']) ?></span>
                        <span class="document-size"><?= $document['size'] < 1048576 ? max(1, (int) round($document['size'] / 1024)) . ' Ko' : e(number_format($document['size'] / 1048576, 1, ',', ' ')) . ' Mo' ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
