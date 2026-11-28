<?php
/**
 * Modèles de messages de l'équipe (/gestion/messages-modeles/), administrateur (phase 7, lot 7A).
 *
 * @var list<array> $modeles
 */
use App\Models\MessageTemplate;

$errors = errors();
$formulaire = static function (?array $m): void {
    ?>
    <form class="g-grid" method="post" action="<?= e(url('/gestion/messages-modeles/')) ?>">
        <?= csrf_field() ?>
        <?php if ($m): ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><?php endif; ?>
        <label class="g-form-row">Titre <input class="g-input" name="title" maxlength="120" value="<?= e($m['title'] ?? '') ?>" required></label>
        <label class="g-form-row">Texte <textarea class="g-input" name="body" rows="4" maxlength="2000" required><?= e($m['body'] ?? '') ?></textarea></label>
        <div class="g-form-grid g-form-2">
            <label class="g-form-row">Canal
                <select class="g-select" name="channel">
                    <?php foreach (MessageTemplate::CANAUX as $cle => $libelle): ?><option value="<?= e($cle) ?>"<?= ($m['channel'] ?? 'les-deux') === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="g-form-row g-inline-check"><input type="checkbox" name="active" value="1"<?= !$m || $m['active'] ? ' checked' : '' ?>> Proposé à l’équipe</label>
        </div>
        <div><button class="g-btn g-btn-success g-btn-sm" type="submit"><?= gestion_icone('check', 15) ?> Enregistrer</button></div>
    </form>
    <?php
};
?>
<p class="g-muted g-form-note">Champs remplis automatiquement : <?= e(implode(' ', MessageTemplate::CHAMPS)) ?>. Les textes de départ sont à faire relire par l’agence.</p>
<?php if (isset($errors['modele'])): ?><p class="g-field-error"><?= e($errors['modele']) ?></p><?php endif; ?>
<div class="g-grid g-grid-2">
    <?php foreach ($modeles as $m): ?>
        <section class="g-card is-<?= $m['active'] ? 'ok' : 'neutre' ?>">
            <div class="g-card-head"><h3><?= e($m['title']) ?></h3><?= $m['active'] ? g_badge('Proposé', 'green') : g_badge('Masqué', 'grey') ?></div>
            <?php $formulaire($m); ?>
        </section>
    <?php endforeach; ?>
    <section class="g-card is-rdv">
        <div class="g-card-head"><h3><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('plus', 18) ?></span>Nouveau modèle</h3></div>
        <?php $formulaire(null); ?>
    </section>
</div>
