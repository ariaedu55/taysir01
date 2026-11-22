<?php
/**
 * Champ de formulaire accessible : libellé, aide, message d'erreur relié au champ.
 *
 * @var string      $name
 * @var string      $label
 * @var string|null $type          text, email, tel, password, date, number, select, textarea, country (text par défaut)
 * @var string|null $autocomplete
 * @var bool|null   $required
 * @var string|null $hint          Aide affichée sous le libellé
 * @var string|null $placeholder
 * @var string|null $value         Valeur par défaut (la saisie précédente reste prioritaire)
 * @var array|null  $options       Choix d'une liste (select) : liste de libellés ou [valeur => libellé]
 * @var string|null $min, $max     Bornes (date, number)
 */
$type ??= 'text';
$error = errors()[$name] ?? null;
$id = 'champ-' . $name;
$describedBy = trim(($hint ?? null ? $id . '-aide ' : '') . ($error ? $id . '-erreur' : ''));
$current = $type === 'password' ? '' : old($name, (string) ($value ?? ''));
$options ??= [];
$attributes = trim(
    (!empty($autocomplete) ? 'autocomplete="' . e($autocomplete) . '" ' : '')
    . (!empty($placeholder) ? 'placeholder="' . e($placeholder) . '" ' : '')
    . (isset($min) ? 'min="' . e((string) $min) . '" ' : '')
    . (isset($max) ? 'max="' . e((string) $max) . '" ' : '')
    . (!empty($required) ? 'required ' : '')
    . ($describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '" ' : '')
    . ($error ? 'aria-invalid="true"' : '')
);
$inputType = match ($type) {
    'country' => 'text',
    default => $type,
};
?>
<div class="field<?= $error ? ' has-error' : '' ?><?= $type === 'textarea' ? ' field-wide' : '' ?>">
    <label for="<?= e($id) ?>"><?= e($label) ?><?php if (empty($required)): ?> <span class="field-optional">(facultatif)</span><?php endif; ?></label>
    <?php if (!empty($hint)): ?>
        <p class="field-hint" id="<?= e($id) ?>-aide"><?= e($hint) ?></p>
    <?php endif; ?>
    <div class="field-control<?= $type === 'select' ? ' field-select' : '' ?>">
        <?php if ($type === 'select'): ?>
            <select id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $attributes ?>>
                <option value="">Choisir…</option>
                <?php foreach ($options as $optionValue => $optionLabel): ?>
                    <?php $optionValue = array_is_list($options) ? $optionLabel : (string) $optionValue; ?>
                    <option value="<?= e($optionValue) ?>"<?= $optionValue === $current ? ' selected' : '' ?>><?= e($optionLabel) ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($type === 'textarea'): ?>
            <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="5" maxlength="2000" <?= $attributes ?>><?= e($current) ?></textarea>
        <?php else: ?>
            <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($inputType) ?>" value="<?= e($current) ?>"
                <?= $type === 'country' ? 'list="liste-pays" autocomplete="country-name"' : '' ?>
                <?= $type === 'number' ? 'inputmode="numeric" step="1"' : '' ?>
                <?= $attributes ?>>
        <?php endif; ?>
        <?php if ($type === 'password'): ?>
            <button class="field-toggle" type="button" data-password-toggle aria-controls="<?= e($id) ?>" aria-pressed="false" hidden>
                <span class="sr-only">Afficher le mot de passe</span>
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        <?php endif; ?>
    </div>
    <?php if ($error): ?>
        <p class="field-error" id="<?= e($id) ?>-erreur"><?= e($error) ?></p>
    <?php endif; ?>
</div>
