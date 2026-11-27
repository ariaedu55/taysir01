<?php
/**
 * Équipe (/gestion/equipe/, administrateur) : 8 postes, missions nominatives, invitation, désactivation.
 * docs/gestion-plan.md §3.1 et §3.6, docs/gestion-phase-1.md (1B).
 *
 * @var array       $me
 * @var list<array> $members
 * @var array       $missions      user_id => missions actives
 * @var array       $postes        Access::POSTES
 * @var array       $missionLabels Access::MISSIONS
 */
$errors = errors();
$familles = ['admin' => 'jour', 'secretaire' => 'rdv', 'prospection' => 'pro', 'commerciale' => 'pro', 'conseiller' => 'ori',
    'orientation' => 'ori', 'traitement' => 'ok', 'comptable' => 'fin'];
$actifs = count(array_filter($members, static fn (array $m): bool => (bool) $m['active']));
?>
<div class="g-grid g-grid-2">
    <section class="g-card" aria-labelledby="membres">
        <div class="g-card-head"><h3 id="membres"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('team', 18) ?></span>Membres <span class="g-count"><?= $actifs ?></span></h3></div>
        <?php if ($members === []): ?>
            <p class="g-empty">Aucun membre. Invitez le premier avec le formulaire.</p>
        <?php else: ?>
            <ul class="g-members">
                <?php foreach ($members as $m): ?>
                    <?php
                    $self = (int) $m['id'] === (int) $me['id'];
                    $pending = $m['last_login_at'] === null;
                    $erreur = $errors['member_' . $m['id']] ?? null;
                    $action = url('/gestion/equipe/' . $m['id'] . '/');
                    $sesMissions = $missions[(int) $m['id']] ?? [];
                    ?>
                    <li class="g-member<?= $m['active'] ? '' : ' is-inactive' ?>">
                        <div class="g-member-head">
                            <span class="g-avatar is-<?= e($familles[$m['role']] ?? 'neutre') ?>" aria-hidden="true"><?= e(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1)) ?></span>
                            <div>
                                <strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?><?= $self ? ' (vous)' : '' ?></strong>
                                <p class="g-muted"><?= e($m['email']) ?> · <?= $pending ? 'jamais connecté(e)' : 'connexion ' . e(g_date($m['last_login_at'])) ?> · <?= (int) $m['open_requests'] ?> dossier(s) en cours</p>
                            </div>
                            <span class="g-badge is-<?= e($m['active'] ? ($pending ? 'ech' : ($familles[$m['role']] ?? 'neutre')) : 'neutre') ?>"><span class="g-dot"></span><?= $m['active'] ? ($pending ? 'Invitation en attente' : e($postes[$m['role']])) : 'Désactivé' ?></span>
                        </div>
                        <?php if ($sesMissions !== []): ?>
                            <p class="g-member-missions">
                                <?php foreach ($sesMissions as $mission): ?>
                                    <span class="g-badge is-ori"><span class="g-dot"></span>Mission : <?= e($missionLabels[$mission['mission']][0]) ?> · depuis le <?= e(date('d/m/Y', strtotime($mission['granted_at']))) ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($erreur): ?><p class="g-field-error"><?= e($erreur) ?></p><?php endif; ?>
                        <?php if (!$self): ?>
                            <details class="g-member-manage"<?= $erreur ? ' open' : '' ?>>
                            <summary class="g-btn g-btn-sm">Gérer</summary>
                            <div class="g-member-actions">
                                <?php if ($m['active']): ?>
                                    <form method="post" action="<?= e($action) ?>" class="g-inline-form">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="role">
                                        <label class="sr-only" for="poste-<?= (int) $m['id'] ?>">Poste de <?= e($m['first_name']) ?></label>
                                        <select class="g-select" id="poste-<?= (int) $m['id'] ?>" name="role">
                                            <?php foreach ($postes as $cle => $libelle): ?><option value="<?= e($cle) ?>"<?= $cle === $m['role'] ? ' selected' : '' ?>><?= e($libelle) ?></option><?php endforeach; ?>
                                        </select>
                                        <button class="g-btn g-btn-sm" type="submit">Changer</button>
                                    </form>
                                    <form method="post" action="<?= e($action) ?>" class="g-inline-form">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="mission">
                                        <label class="sr-only" for="mission-<?= (int) $m['id'] ?>">Mission de <?= e($m['first_name']) ?></label>
                                        <select class="g-select" id="mission-<?= (int) $m['id'] ?>" name="mission">
                                            <?php foreach ($missionLabels as $cle => [$libelle]): ?><option value="<?= e($cle) ?>"><?= e($libelle) ?></option><?php endforeach; ?>
                                        </select>
                                        <button class="g-btn g-btn-sm" type="submit">Ajouter ou retirer</button>
                                    </form>
                                    <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="invite"><button class="g-btn g-btn-sm" type="submit"><?= $pending ? 'Renvoyer l’invitation' : 'Lien de mot de passe' ?></button></form>
                                    <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><button class="g-btn g-btn-sm" type="submit">Désactiver</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="activate"><button class="g-btn g-btn-sm" type="submit">Réactiver</button></form>
                                <?php endif; ?>
                            </div>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="g-card is-ok" id="ajouter" aria-labelledby="ajouter-titre">
        <div class="g-card-head"><h3 id="ajouter-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('plus', 18) ?></span>Inviter un membre</h3></div>
        <form method="post" action="<?= e(url('/gestion/equipe/')) ?>" novalidate>
            <?= csrf_field() ?>
            <?php foreach (['first_name' => ['Prénom', 'text'], 'last_name' => ['Nom', 'text'], 'email' => ['Adresse e-mail', 'email'], 'phone' => ['Téléphone (facultatif)', 'tel']] as $champ => [$label, $type]): ?>
                <label class="g-form-row"><?= e($label) ?>
                    <input class="g-input" type="<?= e($type) ?>" name="<?= e($champ) ?>" value="<?= e(old($champ)) ?>"<?= isset($errors[$champ]) ? ' aria-invalid="true"' : '' ?>>
                </label>
                <?php if (isset($errors[$champ])): ?><p class="g-field-error"><?= e($errors[$champ]) ?></p><?php endif; ?>
            <?php endforeach; ?>
            <label class="g-form-row">Poste
                <select class="g-select" name="role">
                    <?php foreach ($postes as $cle => $libelle): ?><option value="<?= e($cle) ?>"<?= old('role', 'conseiller') === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option><?php endforeach; ?>
                </select>
            </label>
            <p class="g-muted" style="font-size:.76rem;margin:0 0 .8rem">Un poste par personne. Un besoin ponctuel (encaissement au guichet, orientation…) se donne ensuite en mission nominative, datée et inscrite au journal.</p>
            <button class="g-btn g-btn-primary" type="submit"><?= gestion_icone('send', 16) ?> Envoyer l’invitation</button>
        </form>
    </section>
</div>
