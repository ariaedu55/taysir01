<?php
/**
 * Espace client : détail d'une demande, historique, documents, réponse.
 *
 * @var array       $request
 * @var array|null  $service
 * @var list<array> $documents
 * @var list<array> $events
 * @var bool        $canAdd
 */
use App\Models\ServiceRequest;
use App\Services\DocumentStorage;

$reference = $request['reference'];
$replyError = errors()['reply'] ?? null;
?>
<main id="contenu" class="dashboard portal">
    <section class="wrap dashboard-head">
        <div data-reveal="up">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/mon-espace/')) ?>">Mon espace</a></li>
                    <li><a href="<?= e(url('/mon-espace/demandes/')) ?>">Mes demandes</a></li>
                    <li aria-current="page"><?= e($reference) ?></li>
                </ol>
            </nav>
            <h1><?= e($service['title'] ?? 'Demande') ?></h1>
            <p class="dashboard-profile">Référence <?= e($reference) ?> · envoyée le <?= e(date('d/m/Y', strtotime($request['created_at']))) ?></p>
        </div>
        <div data-reveal="up"><?php partial('status', ['label' => ServiceRequest::statusLabel($request['status']), 'tone' => ServiceRequest::statusTone($request['status'])]); ?></div>
    </section>

    <div class="wrap portal-detail">
        <div class="portal-stack">
            <?php partial('flash'); ?>

            <section class="account glass" aria-labelledby="historique-titre" data-reveal="up">
                <h2 id="historique-titre">Avancement</h2>
                <?php partial('request-timeline', ['events' => $events, 'staffView' => false]); ?>
            </section>

            <?php if ($canAdd): ?>
                <section class="account glass" aria-labelledby="repondre-titre" data-reveal="up">
                    <h2 id="repondre-titre">Écrire à votre conseiller</h2>
                    <form class="portal-form portal-reply" method="post" action="<?= e(url('/mon-espace/demandes/' . $reference . '/')) ?>" enctype="multipart/form-data" data-portal-form>
                        <?= csrf_field() ?>
                        <div class="field<?= $replyError ? ' has-error' : '' ?>">
                            <label for="champ-message">Message <span class="field-optional">(facultatif si vous joignez un document)</span></label>
                            <div class="field-control"><textarea id="champ-message" name="message" rows="4" maxlength="2000"<?= $replyError ? ' aria-describedby="champ-reponse-erreur" aria-invalid="true"' : '' ?>><?= e(old('message')) ?></textarea></div>
                        </div>
                        <div class="field">
                            <label class="dropzone dropzone-compact" for="champ-documents" data-dropzone>
                                <strong>Joindre des documents</strong>
                                <span id="champ-documents-aide">PDF, JPG ou PNG · <?= DocumentStorage::MAX_FILES ?> fichiers de 5 Mo au maximum</span>
                                <input id="champ-documents" name="documents[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    data-max-files="<?= DocumentStorage::MAX_FILES ?>" data-max-size="<?= DocumentStorage::MAX_SIZE ?>" aria-describedby="champ-documents-aide">
                            </label>
                            <ul class="dropzone-files" data-dropzone-files aria-live="polite"></ul>
                        </div>
                        <?php if ($replyError): ?><p class="field-error" id="champ-reponse-erreur"><?= e($replyError) ?></p><?php endif; ?>
                        <button class="button primary" type="submit">Envoyer</button>
                    </form>
                </section>
            <?php endif; ?>
        </div>

        <aside class="portal-stack">
            <?php partial('request-summary', ['request' => $request, 'service' => $service]); ?>
            <?php partial('request-documents', ['documents' => $documents, 'base' => '/mon-espace/demandes/' . $reference . '/documents/']); ?>
        </aside>
    </div>
</main>
