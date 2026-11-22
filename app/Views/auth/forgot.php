<?php
/** Mot de passe oublié : demande du lien de réinitialisation (docs/mot-de-passe-oublie.md). */
$errors = errors();
?>
<main id="contenu" class="auth">
    <?php partial('auth-aside', ['photo' => 'hero/paris-portrait', 'caption' => 'Paris · France']); ?>

    <section class="auth-panel" aria-labelledby="oubli-titre">
        <div class="auth-card">
            <p class="kicker" data-reveal="up">Espace Taysir</p>
            <h1 id="oubli-titre" data-reveal="up">Mot de passe <em>oublié ?</em></h1>
            <p class="auth-lead" data-reveal="up">Indiquez l’adresse e-mail de votre compte : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>

            <?php partial('flash'); ?>
            <?php if (isset($errors['email'])): ?>
                <p class="flash flash-error" role="alert">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    <?= e($errors['email']) ?>
                </p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= e(url('/mot-de-passe-oublie/')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="champ-email">Adresse e-mail</label>
                    <div class="field-control">
                        <input id="champ-email" name="email" type="email" value="<?= e(old('email')) ?>" autocomplete="username" required placeholder="vous@exemple.com"<?= isset($errors['email']) ? ' aria-invalid="true"' : '' ?>>
                    </div>
                </div>
                <button class="button primary auth-submit" type="submit">Recevoir le lien</button>
            </form>

            <p class="auth-switch">Vous l’avez retrouvé ? <a href="<?= e(url('/connexion/')) ?>">Se connecter</a></p>
            <p class="auth-forgot auth-help">Plus accès à votre adresse e-mail ? <a href="<?= e(config('site.contact.whatsapp')) ?>">Écrivez-nous sur WhatsApp</a></p>
        </div>
    </section>
</main>
