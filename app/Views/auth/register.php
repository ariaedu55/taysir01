<?php
/** @var array<string, string> $profiles */
$errors = errors();
$selectedProfile = old('profile', 'etudiant');
?>
<main id="contenu" class="auth">
    <?php partial('auth-aside', ['photo' => 'hero/paris-portrait', 'caption' => 'Paris · France']); ?>

    <section class="auth-panel" aria-labelledby="inscription-titre">
        <div class="auth-card">
            <p class="kicker" data-reveal="up">Espace Taysir</p>
            <h1 id="inscription-titre" data-reveal="up">Créer <em>mon compte</em></h1>
            <p class="auth-lead" data-reveal="up">Un seul espace pour préparer vos études et votre départ, et échanger avec votre conseiller.</p>

            <?php partial('flash'); ?>
            <?php if ($errors !== []): ?>
                <p class="flash flash-error" role="alert">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    Merci de corriger <?= count($errors) > 1 ? 'les ' . count($errors) . ' champs signalés' : 'le champ signalé' ?>.
                </p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= e(url('/inscription/')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field-row">
                    <?php partial('form-field', ['name' => 'first_name', 'label' => 'Prénom', 'autocomplete' => 'given-name', 'required' => true]); ?>
                    <?php partial('form-field', ['name' => 'last_name', 'label' => 'Nom', 'autocomplete' => 'family-name', 'required' => true]); ?>
                </div>
                <?php partial('form-field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'autocomplete' => 'email', 'required' => true, 'placeholder' => 'vous@exemple.com']); ?>
                <?php partial('form-field', ['name' => 'phone', 'label' => 'Téléphone ou WhatsApp', 'type' => 'tel', 'autocomplete' => 'tel', 'placeholder' => '+221 77 000 00 00']); ?>

                <fieldset class="field profile-field<?= isset($errors['profile']) ? ' has-error' : '' ?>"<?= isset($errors['profile']) ? ' aria-describedby="profil-erreur"' : '' ?>>
                    <legend>Vous êtes</legend>
                    <div class="profile-options">
                        <?php foreach ($profiles as $value => $label): ?>
                            <label class="profile-option">
                                <input type="radio" name="profile" value="<?= e($value) ?>"<?= $selectedProfile === $value ? ' checked' : '' ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['profile'])): ?>
                        <p class="field-error" id="profil-erreur"><?= e($errors['profile']) ?></p>
                    <?php endif; ?>
                </fieldset>

                <div class="field-row">
                    <?php partial('form-field', ['name' => 'password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'hint' => '8 caractères minimum']); ?>
                    <?php partial('form-field', ['name' => 'password_confirmation', 'label' => 'Confirmation', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'hint' => 'Saisissez-le à nouveau']); ?>
                </div>

                <div class="field consent<?= isset($errors['consent']) ? ' has-error' : '' ?>">
                    <label class="checkbox">
                        <input type="checkbox" name="consent" value="1"<?= old('consent') === '1' ? ' checked' : '' ?><?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="consentement-erreur"' : '' ?>>
                        <span>J’accepte les <a href="<?= e(legacy_url('/terms-conditions/')) ?>">conditions d’utilisation</a> et le traitement de mes données pour le suivi de mon dossier.</span>
                    </label>
                    <?php if (isset($errors['consent'])): ?>
                        <p class="field-error" id="consentement-erreur"><?= e($errors['consent']) ?></p>
                    <?php endif; ?>
                </div>

                <button class="button primary auth-submit" type="submit">Créer mon compte</button>
            </form>

            <p class="auth-switch">Déjà inscrit(e) ? <a href="<?= e(url('/connexion/')) ?>">Se connecter</a></p>
        </div>
    </section>
</main>
