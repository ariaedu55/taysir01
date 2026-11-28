<?php
/**
 * E-mails envoyés par le portail (/gestion/e-mails/, administrateur), docs/notifications-email.md.
 *
 * @var list<array>        $mails
 * @var array<string, int> $counts
 * @var string|null        $status
 * @var string             $driver
 */
use App\Models\MailOutbox;

$kinds = [
    'welcome' => 'Bienvenue', 'request-created' => 'Demande reçue', 'request-agency' => 'Alerte demande',
    'request-updated' => 'Suivi de demande', 'reply-agency' => 'Réponse client', 'appointment-created' => 'Rendez-vous reçu',
    'appointment-agency' => 'Alerte rendez-vous', 'appointment-confirmed' => 'Rendez-vous confirmé',
    'appointment-reminder' => 'Rappel de rendez-vous', 'appointment-moved' => 'Rendez-vous déplacé',
    'appointment-cancelled' => 'Rendez-vous annulé', 'password-reset' => 'Mot de passe oublié', 'password-changed' => 'Mot de passe modifié',
    'staff-invitation' => 'Invitation équipe', 'request-assigned' => 'Dossier affecté', 'contact-ack' => 'Contact reçu', 'contact-agency' => 'Alerte contact',
];
?>
<?php if ($driver === 'log'): ?>
    <p class="g-flash is-ech" role="note"><?= gestion_icone('alert', 17) ?> Mode développement : les e-mails ne partent pas, ils sont écrits dans storage/mails/.</p>
<?php endif; ?>
<div class="g-toolbar">
    <div class="g-views" role="group" aria-label="Filtrer par statut">
        <a class="g-view-link" href="<?= e(url('/gestion/e-mails/')) ?>"<?= $status === null ? ' aria-current="true"' : '' ?>>Tous<span><?= (int) array_sum($counts) ?></span></a>
        <?php foreach (MailOutbox::STATUSES as $cle => [$label]): ?>
            <a class="g-view-link" href="<?= e(url('/gestion/e-mails/?statut=' . $cle)) ?>"<?= $status === $cle ? ' aria-current="true"' : '' ?>><?= e($label) ?><span><?= (int) ($counts[$cle] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="g-table-wrap">
    <?php if ($mails === []): ?>
        <p class="g-empty">Aucun e-mail pour ce filtre.</p>
    <?php else: ?>
        <table class="g-table">
            <thead><tr><th>Date</th><th>Type</th><th>Destinataire</th><th>Objet</th><th>Statut</th><th><span class="sr-only">Action</span></th></tr></thead>
            <tbody>
                <?php foreach ($mails as $mail): ?>
                    <tr>
                        <td><?= e(g_date($mail['created_at'], true)) ?></td>
                        <td class="g-cell-main"><strong><?= e($kinds[$mail['kind']] ?? $mail['kind']) ?></strong><?php if ($mail['related']): ?><span><?= e($mail['related']) ?></span><?php endif; ?></td>
                        <td data-label="À"><?= e($mail['recipient']) ?></td>
                        <td data-label="Objet"><?= e($mail['subject']) ?><?php if ($mail['last_error'] && $mail['status'] !== 'envoye'): ?><br><small class="g-muted"><?= e($mail['last_error']) ?> (<?= (int) $mail['attempts'] ?> essai<?= $mail['attempts'] > 1 ? 's' : '' ?>)</small><?php endif; ?></td>
                        <td><?= g_badge(MailOutbox::STATUSES[$mail['status']][0], MailOutbox::STATUSES[$mail['status']][1]) ?></td>
                        <td>
                            <?php if ($mail['status'] !== 'envoye'): ?>
                                <form method="post" action="<?= e(url('/gestion/e-mails/' . $mail['id'] . '/')) ?>"><?= csrf_field() ?><button class="g-btn g-btn-sm" type="submit">Renvoyer</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
