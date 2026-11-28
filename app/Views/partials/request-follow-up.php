<?php
use App\Models\RequestFollowUp;

$id = (int) $request['id'];
$deadlines = RequestFollowUp::deadlines($id);
$isAdmission = $request['service'] === 'admission';
$results = $isAdmission ? RequestFollowUp::results($id) : [];
$action = $staff ? url('/gestion/dossiers/' . $request['reference'] . '/suivi/') : '';
$editable = $staff && $canEdit && !in_array($request['status'], ['terminee', 'annulee'], true);
$listClass = $staff ? 'g-grid' : 'portal-requirements';
$itemClass = $staff ? 'g-requirement' : 'portal-requirement';
$headClass = $staff ? 'g-actions' : 'portal-requirement-head';
?>
<section class="<?= $staff ? 'g-card g-grid' : 'account glass portal-stack' ?>" id="suivi-dossier" aria-labelledby="suivi-dossier-titre">
    <?php if ($staff): ?>
        <div class="g-card-head"><h3 id="suivi-dossier-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('clock', 18) ?></span>Échéances et résultats</h3></div>
    <?php else: ?>
        <h2 id="suivi-dossier-titre">Échéances et résultats</h2>
    <?php endif; ?>
    <?php if ($staff && ($error = errors()['follow_up'] ?? null)): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
    <div>
        <h3 class="<?= $staff ? 'g-subhead' : '' ?>">Échéances</h3>
        <?php if ($deadlines === []): ?><p>Aucune échéance pour ce dossier.</p><?php endif; ?>
        <?php if ($deadlines): ?>
            <ul class="<?= $listClass ?>">
                <?php foreach ($deadlines as $deadline): ?>
                    <li class="<?= $itemClass ?>">
                        <div class="<?= $headClass ?>">
                            <strong><?= e($deadline['title']) ?></strong>
                            <time datetime="<?= e($deadline['due_date']) ?>"><?= e(date('d/m/Y', strtotime($deadline['due_date']))) ?></time>
                            <?php if ($deadline['completed_at']): ?><?php partial('status', ['label' => 'Terminée', 'tone' => 'green']); ?>
                            <?php elseif ($deadline['due_date'] < gmdate('Y-m-d')): ?><?php partial('status', ['label' => 'En retard', 'tone' => 'amber']); ?><?php endif; ?>
                        </div>
                        <?php if ($editable && !$deadline['completed_at']): ?>
                            <form method="post" action="<?= e($action) ?>">
                                <?= csrf_field() ?><input type="hidden" name="action" value="complete">
                                <input type="hidden" name="deadline_id" value="<?= (int) $deadline['id'] ?>">
                                <button class="g-btn g-btn-sm g-btn-success">Marquer comme terminée</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($editable): ?>
            <details><summary>Ajouter une échéance</summary>
                <form class="g-grid" method="post" action="<?= e($action) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="deadline">
                    <label class="g-form-row">Intitulé visible par le client <input class="g-input" name="title" maxlength="190" required></label>
                    <label class="g-form-row">Date <input class="g-input" name="due_date" type="date" required></label>
                    <div><button class="g-btn g-btn-sm g-btn-success">Enregistrer</button></div>
                </form>
            </details>
        <?php endif; ?>
    </div>
    <?php if ($isAdmission): ?>
        <div>
            <h3 class="<?= $staff ? 'g-subhead' : '' ?>">Admissions</h3>
            <?php if ($results === []): ?><p>Aucun résultat communiqué.</p><?php endif; ?>
            <?php if ($results): ?>
                <ul class="<?= $listClass ?>">
                    <?php foreach ($results as $result): ?>
                        <li class="<?= $itemClass ?>">
                            <div class="<?= $headClass ?>">
                                <strong><?= e($result['institution']) ?></strong>
                                <?php partial('status', ['label' => RequestFollowUp::RESULTS[$result['status']], 'tone' => $result['status'] === 'admis' ? 'green' : 'grey']); ?>
                            </div>
                            <?php if ($editable): ?>
                                <form class="g-grid" method="post" action="<?= e($action) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="result">
                                    <input type="hidden" name="institution" value="<?= e($result['institution']) ?>">
                                    <label class="g-form-row">Résultat pour <?= e($result['institution']) ?>
                                        <select name="result" class="g-select"><?php foreach (RequestFollowUp::RESULTS as $key => $label): ?><option value="<?= e($key) ?>"<?= $result['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                    </label>
                                    <div><button class="g-btn g-btn-sm g-btn-success">Mettre à jour</button></div>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($editable): ?>
                <details><summary>Ajouter un établissement</summary>
                    <form class="g-grid" method="post" action="<?= e($action) ?>">
                        <?= csrf_field() ?><input type="hidden" name="action" value="result">
                        <label class="g-form-row">Établissement <input class="g-input" name="institution" maxlength="190" required></label>
                        <label class="g-form-row">Résultat <select name="result" class="g-select"><?php foreach (RequestFollowUp::RESULTS as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <div><button class="g-btn g-btn-sm g-btn-success">Enregistrer</button></div>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
