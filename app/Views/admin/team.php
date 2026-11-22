<?php
/**
 * Back-office (administrateurs) : membres de l'équipe et invitation de nouveaux conseillers.
 *
 * @var list<array>           $members
 * @var array                 $me
 * @var array<string, string> $roles
 */
$errors = errors();
?>
<main id="contenu" class="dashboard portal admin">
    <section class="wrap dashboard-head">
        <div>
            <?php partial('admin-nav', ['active' => 'equipe']); ?>
            <h1>L’<em>équipe</em></h1>
            <p class="dashboard-profile"><?= count(array_filter($members, static fn ($m) => $m['active'])) ?> compte(s) actif(s). Chaque membre choisit son mot de passe grâce à l’invitation reçue par e-mail.</p>
        </div>
    </section>

    <div class="wrap portal-detail">
        <div class="portal-stack">
            <?php partial('flash'); ?>
            <ul class="appointment-list">
                <?php foreach ($members as $member): ?>
                    <?php
                    $self = (int) $member['id'] === (int) $me['id'];
                    $pending = $member['last_login_at'] === null;
                    $error = $errors['member_' . $member['id']] ?? null;
                    ?>
                    <li class="account glass appointment-card team-member<?= $member['active'] ? '' : ' is-inactive' ?>">
                        <div class="appointment-info">
                            <div class="appointment-top">
                                <strong><?= e($member['first_name'] . ' ' . $member['last_name']) ?><?= $self ? ' (vous)' : '' ?></strong>
                                <?php partial('status', $member['active']
                                    ? ($pending ? ['label' => 'Invitation en attente', 'tone' => 'amber'] : ['label' => $roles[$member['role']], 'tone' => $member['role'] === 'admin' ? 'violet' : 'blue'])
                                    : ['label' => 'Désactivé', 'tone' => 'grey']); ?>
                            </div>
                            <p class="table-muted">
                                <a href="mailto:<?= e($member['email']) ?>"><?= e($member['email']) ?></a><?= $member['phone'] ? ' · ' . e($member['phone']) : '' ?>
                                · <?= $pending ? 'jamais connecté(e)' : 'dernière connexion le ' . e(date('d/m/Y', strtotime($member['last_login_at']))) ?>
                                · <?= (int) $member['open_requests'] ?> demande(s) en cours
                            </p>
                            <?php if ($error): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                        </div>
                        <?php if (!$self): ?>
                            <div class="team-actions">
                                <?php if ($member['active']): ?>
                                    <form method="post" action="<?= e(url('/admin/equipe/' . $member['id'] . '/')) ?>" class="team-role">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="role">
                                        <label class="sr-only" for="role-<?= (int) $member['id'] ?>">Rôle de <?= e($member['first_name']) ?></label>
                                        <div class="field-control field-select">
                                            <select id="role-<?= (int) $member['id'] ?>" name="role">
                                                <?php foreach ($roles as $value => $label): ?>
                                                    <option value="<?= e($value) ?>"<?= $value === $member['role'] ? ' selected' : '' ?>><?= e($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button class="button light button-sm" type="submit">Changer</button>
                                    </form>
                                    <form method="post" action="<?= e(url('/admin/equipe/' . $member['id'] . '/')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="invite">
                                        <button class="button light button-sm" type="submit"><?= $pending ? 'Renvoyer l’invitation' : 'Envoyer un lien de mot de passe' ?></button>
                                    </form>
                                    <form method="post" action="<?= e(url('/admin/equipe/' . $member['id'] . '/')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="deactivate">
                                        <button class="button light button-sm" type="submit">Désactiver</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('/admin/equipe/' . $member['id'] . '/')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button class="button light button-sm" type="submit">Réactiver</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <aside class="portal-stack">
            <section class="account glass" id="ajouter" aria-labelledby="ajouter-titre">
                <h2 id="ajouter-titre">Ajouter un membre</h2>
                <form class="portal-form" method="post" action="<?= e(url('/admin/equipe/')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php partial('form-field', ['name' => 'first_name', 'label' => 'Prénom', 'required' => true]); ?>
                    <?php partial('form-field', ['name' => 'last_name', 'label' => 'Nom', 'required' => true]); ?>
                    <?php partial('form-field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'required' => true, 'hint' => 'L’invitation est envoyée à cette adresse.']); ?>
                    <?php partial('form-field', ['name' => 'phone', 'label' => 'Téléphone', 'type' => 'tel']); ?>
                    <?php partial('form-field', ['name' => 'role', 'label' => 'Rôle', 'type' => 'select', 'required' => true, 'options' => $roles, 'value' => 'conseiller', 'hint' => 'Administrateur : gère aussi l’équipe.']); ?>
                    <button class="button primary" type="submit">Envoyer l’invitation</button>
                </form>
            </section>
        </aside>
    </div>
</main>
