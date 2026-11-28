<?php
/**
 * Un modèle de procédure (/gestion/modeles/nouveau/ et /gestion/modeles/{id}/), administrateur (2C, E8, N5).
 * Brouillon : modifiable ; version publiée ou retirée : lecture seule, revérification et nouvelle version.
 *
 * @var array|null            $modele  null à la création
 * @var array<string, string> $services
 * @var bool|null             $aRevoir
 */
use App\Models\ProcedureTemplate;

$errors = errors();
$brouillon = $modele === null || $modele['status'] === 'brouillon';
$etapesTexte = $modele === null ? old('steps') : implode("\n", array_map(static fn (array $e): string => trim($e['titre'] . ' | ' . implode(' ; ', $e['pieces'] ?? []) . ' | ' . ($e['delai'] ?? ''), ' |'), $modele['steps']));
$sourcesTexte = $modele === null ? old('sources') : implode("\n", $modele['sources']);
$action = $modele === null ? url('/gestion/modeles/') : url('/gestion/modeles/' . $modele['id'] . '/');
$champ = static fn (string $nom): string => isset($errors[$nom]) ? '<p class="g-field-error">' . e($errors[$nom]) . '</p>' : '';
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/modeles/')) ?>"><?= gestion_icone('collapse', 15) ?> Modèles</a></p>

<?php if ($modele !== null): ?>
    <header class="g-case-head">
        <div>
            <div class="g-case-title">
                <h2><?= e($modele['title']) ?></h2>
                <?= g_badge('Version ' . (int) $modele['version'], 'grey') ?>
                <?= g_badge(ProcedureTemplate::STATUTS[$modele['status']][0], ProcedureTemplate::STATUTS[$modele['status']][1]) ?>
                <?php if (!empty($aRevoir)): ?><?= g_badge('À revérifier', 'amber') ?><?php endif; ?>
            </div>
            <p class="g-case-sub">Clé <?= e($modele['template_key']) ?> · <?= $modele['verified_at'] ? 'vérifié le ' . e(g_date($modele['verified_at'])) : 'jamais vérifié' ?></p>
        </div>
        <div class="g-case-actions">
            <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="verifier"><button class="g-btn" type="submit"><?= gestion_icone('check', 16) ?> Marquer vérifié aujourd’hui</button></form>
            <?php if ($modele['status'] === 'brouillon'): ?>
                <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="publier"><button class="g-btn g-btn-success" type="submit"><?= gestion_icone('send', 16) ?> Publier</button></form>
            <?php else: ?>
                <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="nouvelle-version"><button class="g-btn g-btn-primary" type="submit"><?= gestion_icone('plus', 16) ?> Nouvelle version</button></form>
            <?php endif; ?>
            <?php if ($modele['status'] === 'publie'): ?>
                <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="retirer"><button class="g-btn g-btn-warning" type="submit">Retirer</button></form>
            <?php endif; ?>
        </div>
    </header>
    <?php if (isset($errors['modele'])): ?><p class="g-notice"><?= gestion_icone('alert', 16) ?> <?= e($errors['modele']) ?></p><?php endif; ?>
<?php endif; ?>

<section class="g-card is-ok g-form-card" aria-labelledby="modele-titre">
    <div class="g-card-head"><h3 id="modele-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span><?= $modele === null ? 'Nouveau modèle' : ($brouillon ? 'Contenu du brouillon' : 'Contenu (figé)') ?></h3></div>
    <form method="post" action="<?= e($action) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($modele !== null): ?><input type="hidden" name="action" value="enregistrer"><?php endif; ?>
        <fieldset class="g-fieldset"<?= $brouillon ? '' : ' disabled' ?>>
            <div class="g-form-grid g-form-2">
                <?php if ($modele === null): ?>
                    <label class="g-form-row">Clé (unique) <input class="g-input" name="template_key" value="<?= e(old('template_key')) ?>" placeholder="france-campus" maxlength="60"></label>
                <?php endif; ?>
                <label class="g-form-row">Titre <input class="g-input" name="title" value="<?= e($modele['title'] ?? old('title')) ?>" maxlength="190"></label>
                <label class="g-form-row">Service
                    <select class="g-select" name="service">
                        <?php foreach ($services as $cle => $titre): ?><option value="<?= e($cle) ?>"<?= ($modele['service'] ?? old('service', 'admission')) === $cle ? ' selected' : '' ?>><?= e($titre) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="g-form-row">Destination <input class="g-input" name="destination" value="<?= e($modele['destination'] ?? old('destination')) ?>" maxlength="120"></label>
            </div>
            <?= $champ('template_key') . $champ('title') . $champ('service') ?>
            <label class="g-form-row">Étapes, une par ligne : « Titre | pièce 1 ; pièce 2 | délai en jours »
                <textarea class="g-input" name="steps" rows="7"><?= e($etapesTexte) ?></textarea>
            </label>
            <?= $champ('steps') ?>
            <label class="g-form-row">Sources officielles, une adresse par ligne (au moins une pour publier)
                <textarea class="g-input" name="sources" rows="3"><?= e($sourcesTexte) ?></textarea>
            </label>
            <?= $champ('sources') ?>
            <?php if ($brouillon): ?>
                <button class="g-btn g-btn-success" type="submit"><?= gestion_icone('check', 16) ?> <?= $modele === null ? 'Créer le brouillon' : 'Enregistrer le brouillon' ?></button>
            <?php endif; ?>
        </fieldset>
    </form>
</section>
