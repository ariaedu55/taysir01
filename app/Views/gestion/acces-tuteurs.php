<?php
$base = '/gestion/dossiers/' . $request['reference'] . '/';
$action = $base . 'tuteurs/';
$grantError = errors()['tutor_grant'] ?? null;
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url($base)) ?>"><?= gestion_icone('collapse', 15) ?> Retour au dossier</a></p>
<header class="g-case-head">
    <div>
        <div class="g-case-title"><h2>Accès tuteurs</h2></div>
        <p class="g-case-sub"><?= e($request['first_name'] . ' ' . $request['last_name']) ?> · <?= e($request['reference']) ?></p>
    </div>
</header>

<div class="g-grid g-grid-2">
    <section class="g-card is-rdv" aria-labelledby="accorder-acces">
        <div class="g-card-head"><h3 id="accorder-acces">Accorder un accès</h3></div>
        <p class="g-muted">Relevez l’identifiant du compte dans la fiche personne du parent ou du tuteur.</p>
        <form method="get" action="<?= e(url($action)) ?>" class="g-grid" novalidate>
            <label for="tutor-account-id">Identifiant du compte parent</label>
            <input id="tutor-account-id" class="g-input" type="text" inputmode="numeric" name="compte" value="<?= e($lookup) ?>"
                maxlength="20" required<?= $lookupError ? ' aria-invalid="true" aria-describedby="tutor-lookup-error"' : '' ?>>
            <?php if ($lookupError): ?><p class="g-field-error" id="tutor-lookup-error" role="alert"><?= e($lookupError) ?></p><?php endif; ?>
            <div><button class="g-btn" type="submit">Rechercher le compte</button></div>
        </form>

        <?php if ($grantError): ?><p class="g-field-error" role="alert"><?= e($grantError) ?></p><?php endif; ?>
        <?php if ($account !== null): ?>
            <form method="post" action="<?= e(url($action)) ?>" class="g-grid" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="parent_user_id" value="<?= e($account['id']) ?>">
                <p><strong><?= e($account['first_name'] . ' ' . $account['last_name']) ?></strong> · compte n° <?= e($account['id']) ?></p>
                <p class="g-muted">La lecture donne accès à l’avancement partagé et aux documents de ce dossier.</p>
                <label class="g-form-row">Référence interne du justificatif <input class="g-input" name="verification_reference" maxlength="100" required></label>
                <label><input type="checkbox" name="verified" value="1" required> Je confirme avoir vérifié le lien de tutelle et identifié ce compte en dehors de l’outil.</label>
                <label><input type="checkbox" name="can_deposit" value="1"<?= old('can_deposit') === '1' ? ' checked' : '' ?>> Autoriser aussi le dépôt pour les pièces attendues</label>
                <div><button class="g-btn g-btn-primary" type="submit">Accorder l’accès en lecture</button></div>
            </form>
        <?php endif; ?>
    </section>

    <section class="g-card" aria-labelledby="acces-accordes">
        <div class="g-card-head"><h3 id="acces-accordes">Accès accordés</h3></div>
        <?php if ($accesses === []): ?>
            <p class="g-empty">Aucun accès tuteur accordé.</p>
        <?php else: ?>
            <ul class="g-docs">
                <?php foreach ($accesses as $access): ?>
                    <li class="g-doc">
                        <span class="g-doc-icon" aria-hidden="true"><?= gestion_icone('users', 17) ?></span>
                        <div>
                            <strong><?= e($access['first_name'] . ' ' . $access['last_name']) ?> · compte n° <?= e($access['parent_user_id']) ?></strong>
                            <p><?= $access['can_deposit'] ? 'Lecture et dépôt de pièces attendues' : 'Lecture seule' ?> · accordé le <?= e(g_date($access['granted_at'])) ?><?= $access['account_eligible'] ? '' : ' · accès suspendu' ?></p>
                            <details>
                                <summary>Retirer cet accès</summary>
                                <form method="post" action="<?= e(url($action . $access['id'] . '/revoquer/')) ?>" novalidate>
                                    <?= csrf_field() ?>
                                    <p>Le compte perdra l’accès à ce dossier et à ses pièces.</p>
                                    <button class="g-btn g-btn-sm g-btn-danger" type="submit">Confirmer le retrait</button>
                                </form>
                            </details>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
