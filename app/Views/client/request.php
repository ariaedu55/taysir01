<?php
/**
 * Espace client : détail d'une demande, historique, documents, réponse.
 *
 * @var array       $request
 * @var array|null  $service
 * @var list<array> $documents
 * @var list<array> $events
 * @var bool        $owner, $canReply, $canDeposit, $canDepositAsTutor
 * @var list<array> $tutorAccesses
 */
use App\Models\ServiceRequest;
use App\Services\DocumentStorage;

require_once BASE_PATH . '/app/Views/gestion/icones.php';

$reference = $request['reference'];
$replyError = errors()['reply'] ?? null;
$nextPiece = $canDeposit ? array_find($requirements, static fn (array $piece): bool => App\Models\RequestRequirement::isExpired($piece)
    || in_array($piece['status'], ['manquante', 'refusee'], true)) : null;
?>
<main id="contenu" class="dashboard portal">
    <section class="wrap dashboard-head client-wrap">
        <div data-reveal="up">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/mon-espace/')) ?>">Mon espace</a></li>
                    <li><a href="<?= e(url($owner ? '/mon-espace/demandes/' : '/mon-espace/demandes/#dossiers-partages')) ?>"><?= $owner ? 'Mes demandes' : 'Dossiers partagés' ?></a></li>
                    <li aria-current="page"><?= e($reference) ?></li>
                </ol>
            </nav>
            <h1><?= e($service['title'] ?? 'Demande') ?></h1>
            <p class="dashboard-profile">Référence <?= e($reference) ?> · envoyée le <?= e(date('d/m/Y', strtotime($request['created_at']))) ?></p>
            <?php if (!$owner): ?>
                <p class="dashboard-profile"><?= e($request['first_name'] . ' ' . $request['last_name']) ?><?= $request['destination'] ? ' · ' . e($request['destination']) : '' ?></p>
                <p class="dashboard-profile"><?= $canDepositAsTutor ? 'Accès tuteur · Lecture et dépôt de pièces attendues' : 'Accès tuteur · Lecture seule' ?></p>
            <?php endif; ?>
        </div>
        <div data-reveal="up"><?php partial('status', ['label' => ServiceRequest::statusLabel($request['status']), 'tone' => ServiceRequest::statusTone($request['status'])]); ?></div>
    </section>

    <?php if ($nextPiece || ($canReply && $request['status'] === 'attente-client')): ?>
        <div class="client-wrap">
            <section class="client-next client-tone-amber" aria-labelledby="prochaine-action">
                <span class="client-icon"><?= gestion_icone($nextPiece ? 'upload' : 'chat', 24) ?></span>
                <div class="client-next-copy"><p class="client-label">Prochaine action</p><h2 id="prochaine-action"><?= e($nextPiece ? $nextPiece['title'] : 'Répondre à votre conseiller') ?></h2></div>
                <a class="button primary" href="<?= $nextPiece ? '#piece-' . (int) $nextPiece['id'] : '#repondre-titre' ?>"><?= $nextPiece ? 'Ajouter la pièce' : 'Écrire un message' ?> <?= gestion_icone('arrow', 16) ?></a>
            </section>
        </div>
    <?php endif; ?>

    <div class="wrap portal-detail client-wrap">
        <div class="portal-stack">
            <?php partial('flash'); ?>

            <?php if ($requirements !== []): ?>
                <section class="account glass" id="pieces-attendues" aria-labelledby="pieces-attendues-titre">
                    <h2 id="pieces-attendues-titre">Pièces attendues</h2>
                    <?php if ($pieceError = errors()['requirement'] ?? null): ?><p class="field-error" role="alert"><?= e($pieceError) ?></p><?php endif; ?>
                    <ul class="portal-requirements">
                        <?php foreach ($requirements as $piece): ?>
                            <?php $pieceId = (int) $piece['id']; $expired = App\Models\RequestRequirement::isExpired($piece);
                            $canUpload = $canDeposit && ($expired || in_array($piece['status'], ['manquante', 'refusee'], true)); ?>
                            <li class="portal-requirement" id="piece-<?= $pieceId ?>">
                                <div class="portal-requirement-head">
                                    <h3><?= e($piece['title']) ?></h3>
                                    <?php partial('requirement-status', ['state' => $expired ? 'expiree' : $piece['status']]); ?>
                                </div>
                                <?php if ($piece['refusal_reason']): ?><p><?= nl2br(e($piece['refusal_reason'])) ?></p><?php endif; ?>
                                <?php if ($piece['expires_on'] ?? null): ?><p>Valable jusqu’au <?= e(date('d/m/Y', strtotime($piece['expires_on']))) ?></p><?php endif; ?>
                                <?php if ($piece['minimum_valid_until']): ?><p>Validité exigée jusqu’au <?= e(date('d/m/Y', strtotime($piece['minimum_valid_until']))) ?></p><?php endif; ?>
                                <?php if ($piece['document_id']): ?>
                                    <ul class="piece-files" aria-label="Fichiers déposés pour <?= e($piece['title']) ?>">
                                        <?php foreach (($pieceFiles ?? [])[$pieceId] ?? [['id' => $piece['document_id'], 'original_name' => 'Pièce déposée', 'mime' => '']] as $fichierDepose): ?>
                                            <li><a href="<?= e(url('/mon-espace/demandes/' . $reference . '/documents/' . (int) $fichierDepose['id'] . '/')) ?>"><?= gestion_icone(str_starts_with((string) $fichierDepose['mime'], 'image/') ? 'eye' : 'doc', 15) ?> <?= e($fichierDepose['original_name']) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if ($canUpload): ?>
                                    <form method="post" class="portal-form portal-requirement-form" action="<?= e(url('/mon-espace/demandes/' . $reference . '/pieces-attendues/' . $pieceId . '/')) ?>" enctype="multipart/form-data" data-portal-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="previous_document_id" value="<?= (int) $piece['document_id'] ?>">
                                        <label class="dropzone dropzone-compact" for="fichier-<?= $pieceId ?>" data-dropzone>
                                            <?= gestion_icone('upload', 22) ?>
                                            <strong>Choisir les fichiers pour <?= e($piece['title']) ?></strong>
                                            <span id="format-<?= $pieceId ?>">PDF, JPG ou PNG · jusqu’à <?= DocumentStorage::MAX_FILES ?> fichiers (recto-verso, plusieurs pages) · 5 Mo chacun</span>
                                            <input id="fichier-<?= $pieceId ?>" name="documents[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                                required data-max-files="<?= DocumentStorage::MAX_FILES ?>" data-max-size="<?= DocumentStorage::MAX_SIZE ?>" aria-describedby="format-<?= $pieceId ?>">
                                        </label>
                                        <ul class="dropzone-files" data-dropzone-files aria-live="polite"></ul>
                                        <?php $libelleEnregistrer = $piece['status'] === 'manquante' ? 'Enregistrer la pièce' : 'Enregistrer la nouvelle version'; ?>
                                        <div class="dz-buttons" data-dz-buttons>
                                            <button class="button portal-requirement-button" type="submit" data-save-button data-label="<?= e($libelleEnregistrer) ?>"><?= gestion_icone('check', 16) ?> <span><?= e($libelleEnregistrer) ?></span></button>
                                        </div>
                                        <p class="dz-hint">1. Ajoutez vos fichiers · 2. Vérifiez-les avec « Aperçu » · 3. Cliquez sur « <?= e($libelleEnregistrer) ?> ». Rien n’est envoyé avant.</p>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php partial('request-follow-up', ['request' => $request, 'staff' => false]); ?>
            <?php if ($owner): ?>
                <?php $finance = App\Models\CaseFinance::forRequest((int) $request['id']); ?>
                <?php if ($finance['quote']) partial('case-finance', ['finance' => $finance, 'request' => $request, 'staff' => false]); ?>
            <?php endif; ?>
            <section class="account glass" aria-labelledby="historique-titre" data-reveal="up">
                <h2 id="historique-titre">Avancement</h2>
                <?php partial('request-timeline', ['events' => $events, 'staffView' => false, 'tutorView' => !$owner]); ?>
            </section>

            <?php if ($canReply): ?>
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
                                <?= gestion_icone('upload', 22) ?>
                                <strong>Joindre des documents (facultatif)</strong>
                                <span id="champ-documents-aide">PDF, JPG ou PNG · <?= DocumentStorage::MAX_FILES ?> fichiers de 5 Mo au maximum</span>
                                <input id="champ-documents" name="documents[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    data-max-files="<?= DocumentStorage::MAX_FILES ?>" data-max-size="<?= DocumentStorage::MAX_SIZE ?>" aria-describedby="champ-documents-aide">
                            </label>
                            <ul class="dropzone-files" data-dropzone-files aria-live="polite"></ul>
                        </div>
                        <?php if ($replyError): ?><p class="field-error" id="champ-reponse-erreur"><?= e($replyError) ?></p><?php endif; ?>
                        <button class="button primary" type="submit">Envoyer le message</button>
                    </form>
                </section>
            <?php endif; ?>
        </div>

        <aside class="portal-stack">
            <?php if ($owner): ?>
                <?php partial('request-summary', ['request' => $request, 'service' => $service]); ?>
            <?php endif; ?>
            <?php partial('request-documents', ['documents' => $documents, 'base' => '/mon-espace/demandes/' . $reference . '/documents/']); ?>
            <?php if ($owner): ?>
                <section class="account glass" id="acces-tuteurs" aria-labelledby="acces-tuteurs-titre">
                    <h2 id="acces-tuteurs-titre">Accès tuteurs</h2>
                    <?php if ($tutorAccesses === []): ?>
                        <p class="portal-empty">Aucun accès tuteur accordé.</p>
                    <?php else: ?>
                        <ul class="portal-requirements">
                            <?php foreach ($tutorAccesses as $access): ?>
                                <li class="portal-requirement">
                                    <h3><?= e($access['first_name'] . ' ' . $access['last_name']) ?></h3>
                                    <p><?= $access['can_deposit'] ? 'Lecture et dépôt de pièces attendues' : 'Lecture seule' ?><?= $access['account_eligible'] ? '' : ' · accès suspendu' ?></p>
                                    <details>
                                        <summary>Retirer cet accès</summary>
                                        <form method="post" action="<?= e(url('/mon-espace/demandes/' . $reference . '/tuteurs/' . $access['id'] . '/revoquer/')) ?>" novalidate>
                                            <?= csrf_field() ?>
                                            <p>Le compte perdra l’accès à ce dossier et à ses pièces.</p>
                                            <button class="button portal-requirement-button" type="submit">Confirmer le retrait</button>
                                        </form>
                                    </details>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</main>
