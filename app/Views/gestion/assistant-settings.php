<?php
use App\Core\Session;

$error = Session::get('_assistant_error');
$status = !$enabled ? 'Désactivé' : ($mock ? 'Mode simulé' : 'Activé');
$keyStatus = match ($keySource) {
    'administration' => 'Clé enregistrée dans la gestion',
    'serveur' => 'Clé fournie par le serveur',
    default => 'Aucune clé enregistrée',
};
?>
<div class="g-grid g-grid-2">
    <section class="g-card g-form-card is-ok" aria-labelledby="assistant-cle-titre">
        <div class="g-card-head">
            <h2 id="assistant-cle-titre"><span class="g-head-icon"><?= gestion_icone('lock', 18) ?></span>Clé API DeepSeek</h2>
        </div>
        <?php if (is_string($error)): ?><p class="g-field-error" id="assistant-cle-erreur" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" action="<?= e(url('/gestion/assistant/')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <label class="g-form-row" for="assistant-cle">Nouvelle clé API
                <input class="g-input" type="password" id="assistant-cle" name="api_key" maxlength="512" required
                    autocomplete="off" spellcheck="false" autocapitalize="off"
                    aria-describedby="assistant-cle-aide<?= is_string($error) ? ' assistant-cle-erreur' : '' ?>">
            </label>
            <p class="g-muted g-form-note" id="assistant-cle-aide">Enregistrer une clé active l’assistant. Pour la remplacer, saisissez la nouvelle clé ici.</p>
            <button class="g-btn g-btn-success" type="submit"><?= $keySource === null ? 'Enregistrer la clé' : 'Remplacer la clé' ?></button>
        </form>
    </section>
    <section class="g-card is-rdv" aria-labelledby="assistant-etat-titre">
        <div class="g-card-head">
            <h2 id="assistant-etat-titre"><span class="g-head-icon"><?= gestion_icone('chat', 18) ?></span>État de l’assistant</h2>
            <?= g_badge($status, $enabled ? ($mock ? 'ech' : 'ok') : 'neutre') ?>
        </div>
        <p><?= e($keyStatus) ?></p>
        <?php if ($enabled || $keySource !== null): ?>
            <form class="g-case-tools" method="post" action="<?= e(url('/gestion/assistant/')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disable">
                <button class="g-btn g-btn-danger" type="submit"><?= $keySource === 'administration' ? 'Retirer la clé et désactiver' : 'Désactiver l’assistant' ?></button>
            </form>
        <?php endif; ?>
    </section>
</div>
