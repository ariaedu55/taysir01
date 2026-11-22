<?php
/**
 * Choix du nouveau mot de passe, après ouverture du lien reçu par e-mail (docs/mot-de-passe-oublie.md).
 *
 * @var string $email     Compte concerné
 * @var bool   $firstTime Invitation d'un membre de l'équipe (premier mot de passe)
 */
?>
<main id="contenu" class="auth">
    <?php partial('auth-aside', ['photo' => 'hero/paris-portrait', 'caption' => 'Paris · France']); ?>

    <section class="auth-panel" aria-labelledby="nouveau-titre">
        <div class="auth-card">
            <p class="kicker" data-reveal="up">Espace Taysir</p>
            <?php if ($firstTime): ?>
                <h1 id="nouveau-titre" data-reveal="up">Bienvenue <em>dans l’équipe</em></h1>
                <p class="auth-lead" data-reveal="up">Choisissez votre mot de passe pour activer votre accès au back-office.</p>
            <?php else: ?>
                <h1 id="nouveau-titre" data-reveal="up">Nouveau <em>mot de passe</em></h1>
            <?php endif; ?>
            <p class="auth-lead" data-reveal="up">Pour le compte <strong><?= e($email) ?></strong>.</p>

            <form class="auth-form" method="post" action="<?= e(url('/reinitialiser-mot-de-passe/')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" hidden>
                <?php partial('form-field', ['name' => 'password', 'label' => 'Nouveau mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'hint' => '8 caractères au minimum.']); ?>
                <?php partial('form-field', ['name' => 'password_confirmation', 'label' => 'Confirmez le mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true]); ?>
                <button class="button primary auth-submit" type="submit">Enregistrer et me connecter</button>
            </form>
        </div>
    </section>
</main>
