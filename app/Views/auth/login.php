<?php
/** @var int $lockedFor Secondes de blocage restantes après trop de tentatives */
$errors = errors();
?>
<main id="contenu" class="auth">
    <?php partial('auth-aside', ['photo' => 'hero/calgary-portrait', 'caption' => 'Calgary · Canada']); ?>

    <section class="auth-panel" aria-labelledby="connexion-titre">
        <div class="auth-card">
            <p class="kicker" data-reveal="up">Espace Taysir</p>
            <h1 id="connexion-titre" data-reveal="up">Bon retour <em>parmi nous</em></h1>
            <p class="auth-lead" data-reveal="up">Connectez-vous pour suivre vos demandes et vos rendez-vous.</p>

            <?php partial('flash'); ?>
            <?php if (isset($errors['email'])): ?>
                <p class="flash flash-error" role="alert">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    <?= e($errors['email']) ?>
                </p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= e(url('/connexion/')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="champ-email">Adresse e-mail</label>
                    <div class="field-control">
                        <input id="champ-email" name="email" type="email" value="<?= e(old('email')) ?>" autocomplete="username" required placeholder="vous@exemple.com"<?= isset($errors['email']) ? ' aria-invalid="true"' : '' ?>>
                    </div>
                </div>
                <?php partial('form-field', ['name' => 'password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true]); ?>
                <p class="auth-forgot"><a href="<?= e(url('/mot-de-passe-oublie/')) ?>">Mot de passe oublié ?</a></p>
                <button class="button primary auth-submit" type="submit"<?= $lockedFor > 0 ? ' disabled' : '' ?>>Se connecter</button>
            </form>

            <p class="auth-switch">Pas encore de compte ? <a href="<?= e(url('/inscription/')) ?>">Créer mon compte</a></p>
        </div>
    </section>
</main>
