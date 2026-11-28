<?php
/**
 * Création d'une fiche personne (/gestion/personnes/nouvelle/) par l'accueil (visite), le conseiller, l'orientation
 * ou l'administrateur. Étape 2A (docs/gestion-phase-2.md).
 *
 * @var list<array> $agences
 * @var string      $origineParDefaut
 * (erreurs du formulaire : errors())
 */
use App\Models\Person;

$errors = errors();
$champ = static function (string $nom, string $label, string $type = 'text', string $aide = '') use ($errors): string {
    $html = '<label class="g-form-row">' . e($label)
        . '<input class="g-input" type="' . e($type) . '" name="' . e($nom) . '" value="' . e(old($nom)) . '"' . (isset($errors[$nom]) ? ' aria-invalid="true"' : '') . '>'
        . ($aide !== '' ? '<small class="g-muted">' . e($aide) . '</small>' : '') . '</label>';

    return $html . (isset($errors[$nom]) ? '<p class="g-field-error">' . e($errors[$nom]) . '</p>' : '');
};
$fuseaux = ['Africa/Dakar' => 'Dakar', 'Europe/Paris' => 'Paris', 'America/Toronto' => 'Toronto', 'America/Montreal' => 'Montréal', 'Europe/Brussels' => 'Bruxelles', 'Africa/Casablanca' => 'Casablanca'];
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/personnes/')) ?>"><?= gestion_icone('collapse', 15) ?> Personnes</a></p>

<section class="g-card is-ok g-form-card" aria-labelledby="nouvelle-titre">
    <div class="g-card-head"><h3 id="nouvelle-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('plus', 18) ?></span>Nouvelle personne</h3></div>
    <form method="post" action="<?= e(url('/gestion/personnes/nouvelle/')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="creation_token" value="<?= e($jetonCreation) ?>">
        <?php if (isset($errors['creation'])): ?><p role="alert"><?= e($errors['creation']) ?></p><?php endif; ?>
        <div class="g-form-grid">
            <?= $champ('first_name', 'Prénom') ?>
            <?= $champ('last_name', 'Nom') ?>
            <?= $champ('phone', 'Téléphone', 'tel') ?>
            <?= $champ('email', 'Adresse e-mail (facultative)', 'email') ?>
            <label class="g-form-row">Profil
                <select class="g-select" name="profile">
                    <option value="">Non précisé</option>
                    <?php foreach (Person::PROFILS as $cle => $libelle): ?><option value="<?= e($cle) ?>"<?= old('profile') === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?= $champ('birth_date', 'Date de naissance (facultative)', 'date') ?>
            <label class="g-form-row">Agence
                <select class="g-select" name="agency_id">
                    <?php foreach ($agences as $a): ?><option value="<?= (int) $a['id'] ?>"<?= old('agency_id') === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="g-form-row">Fuseau de la personne
                <select class="g-select" name="timezone">
                    <?php foreach ($fuseaux as $cle => $libelle): ?><option value="<?= e($cle) ?>"<?= old('timezone', 'Africa/Dakar') === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="g-form-row">Origine
                <select class="g-select" name="origin">
                    <?php foreach (['visite', 'site', 'prospect'] as $cle): ?><option value="<?= e($cle) ?>"<?= old('origin', $origineParDefaut) === $cle ? ' selected' : '' ?>><?= e(Person::ORIGINES[$cle]) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <p class="g-muted g-form-note">Au moins un téléphone ou une adresse e-mail. Une adresse déjà connue ne rattache rien automatiquement : la fiche est marquée « à rapprocher ».</p>
        <button class="g-btn g-btn-success" type="submit"><?= gestion_icone('check', 16) ?> Créer la fiche</button>
    </form>
</section>
