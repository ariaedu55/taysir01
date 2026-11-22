<?php
/**
 * Back-office : e-mails envoyés par le portail (file mail_outbox).
 *
 * @var list<array>        $mails
 * @var array<string, int> $counts
 * @var string|null        $status
 * @var string             $driver  Mode d'envoi (log, mail, smtp)
 */
use App\Models\MailOutbox;

$kinds = [
    'welcome' => 'Bienvenue', 'request-created' => 'Demande reçue', 'request-agency' => 'Alerte demande',
    'request-updated' => 'Suivi de demande', 'reply-agency' => 'Réponse client', 'appointment-created' => 'Rendez-vous reçu',
    'appointment-agency' => 'Alerte rendez-vous', 'appointment-confirmed' => 'Rendez-vous confirmé',
    'appointment-cancelled' => 'Rendez-vous annulé', 'password-reset' => 'Mot de passe oublié', 'password-changed' => 'Mot de passe modifié', 'staff-invitation' => 'Invitation équipe', 'request-assigned' => 'Demande attribuée', 'contact-ack' => 'Contact reçu', 'contact-agency' => 'Alerte contact',
];
?>
<main id="contenu" class="dashboard portal admin">
    <section class="wrap dashboard-head">
        <div>
            <?php partial('admin-nav', ['active' => 'e-mails']); ?>
            <h1>E-mails <em>envoyés</em></h1>
            <p class="dashboard-profile"><?= (int) ($counts['envoye'] ?? 0) ?> envoyé(s) · <?= (int) ($counts['en-attente'] ?? 0) ?> en attente · <?= (int) ($counts['echec'] ?? 0) ?> en échec</p>
        </div>
    </section>

    <div class="wrap portal-stack">
        <?php partial('flash'); ?>
        <?php if ($driver === 'log'): ?>
            <p class="flash" role="note">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                Mode développement : les e-mails ne partent pas, ils sont écrits dans storage/mails/. Configurez l’envoi dans config/mail.local.php.
            </p>
        <?php endif; ?>

        <div class="filters" role="group" aria-label="Filtrer par statut">
            <a class="filter<?= $status === null ? ' is-active' : '' ?>" href="<?= e(url('/admin/e-mails/')) ?>">Tous</a>
            <?php foreach (MailOutbox::STATUSES as $key => [$label]): ?>
                <a class="filter<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('/admin/e-mails/?statut=' . $key)) ?>"><?= e($label) ?> <span><?= (int) ($counts[$key] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>

        <section class="account glass" aria-label="E-mails">
            <?php if ($mails === []): ?>
                <p class="portal-empty">Aucun e-mail pour ce filtre.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Destinataire</th><th scope="col">Objet</th><th scope="col">Statut</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
                        <tbody>
                            <?php foreach ($mails as $mail): ?>
                                <tr>
                                    <td><?= e(date('d/m H\hi', strtotime($mail['created_at']))) ?></td>
                                    <td><?= e($kinds[$mail['kind']] ?? $mail['kind']) ?><?php if ($mail['related']): ?><br><span class="table-muted"><?= e($mail['related']) ?></span><?php endif; ?></td>
                                    <td><?= e($mail['recipient']) ?></td>
                                    <td class="mail-subject"><?= e($mail['subject']) ?><?php if ($mail['last_error'] && $mail['status'] !== 'envoye'): ?><br><span class="table-muted"><?= e($mail['last_error']) ?> (<?= (int) $mail['attempts'] ?> essai<?= $mail['attempts'] > 1 ? 's' : '' ?>)</span><?php endif; ?></td>
                                    <td><?php partial('status', ['label' => MailOutbox::STATUSES[$mail['status']][0], 'tone' => MailOutbox::STATUSES[$mail['status']][1]]); ?></td>
                                    <td>
                                        <?php if ($mail['status'] !== 'envoye'): ?>
                                            <form method="post" action="<?= e(url('/admin/e-mails/' . $mail['id'] . '/')) ?>">
                                                <?= csrf_field() ?>
                                                <button class="button light button-sm" type="submit">Renvoyer</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
