<?php
/**
 * Back-office : messages de la page Contact.
 *
 * @var list<array>        $messages
 * @var array<string, int> $counts
 * @var string|null        $status
 */
use App\Models\ContactMessage;
?>
<main id="contenu" class="dashboard portal admin">
    <section class="wrap dashboard-head">
        <div>
            <?php partial('admin-nav', ['active' => 'messages']); ?>
            <h1>Messages <em>de contact</em></h1>
            <p class="dashboard-profile"><?= (int) ($counts['nouveau'] ?? 0) ?> à traiter · <?= (int) ($counts['traite'] ?? 0) ?> traité(s)</p>
        </div>
    </section>

    <div class="wrap portal-stack">
        <?php partial('flash'); ?>
        <div class="filters" role="group" aria-label="Filtrer par statut">
            <a class="filter<?= $status === null ? ' is-active' : '' ?>" href="<?= e(url('/admin/messages/')) ?>">Tous</a>
            <?php foreach (ContactMessage::STATUSES as $key => [$label]): ?>
                <a class="filter<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('/admin/messages/?statut=' . $key)) ?>"><?= e($label) ?> <span><?= (int) ($counts[$key] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>

        <?php if ($messages === []): ?>
            <section class="account glass"><p class="portal-empty">Aucun message pour ce filtre.</p></section>
        <?php endif; ?>

        <ul class="appointment-list">
            <?php foreach ($messages as $message): ?>
                <li class="account glass appointment-card">
                    <div class="appointment-info">
                        <div class="appointment-top">
                            <strong><?= e($message['name']) ?></strong>
                            <?php partial('status', ['label' => ContactMessage::STATUSES[$message['status']][0], 'tone' => ContactMessage::STATUSES[$message['status']][1]]); ?>
                        </div>
                        <p class="table-muted">
                            Reçu le <?= e(date('d/m/Y à H\hi', strtotime($message['created_at']))) ?>
                            · <a href="tel:<?= e(str_replace(' ', '', $message['phone'])) ?>"><?= e($message['phone']) ?></a>
                            · <a href="mailto:<?= e($message['email']) ?>?subject=<?= e(rawurlencode('Votre message à Taysir Orientation Voyage')) ?>"><?= e($message['email']) ?></a>
                            <?= $message['user_id'] ? ' · compte client' : '' ?>
                            <?php if ($message['status'] === 'traite' && $message['handler_first_name']): ?>
                                · traité par <?= e($message['handler_first_name']) ?> le <?= e(date('d/m/Y', strtotime($message['handled_at']))) ?>
                            <?php endif; ?>
                        </p>
                        <p class="summary-message"><?= nl2br(e($message['message'])) ?></p>
                    </div>
                    <form method="post" action="<?= e(url('/admin/messages/' . $message['id'] . '/')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= $message['status'] === 'nouveau' ? 'traite' : 'nouveau' ?>">
                        <button class="button <?= $message['status'] === 'nouveau' ? 'primary' : 'light' ?> button-sm" type="submit"><?= $message['status'] === 'nouveau' ? 'Marquer comme traité' : 'Remettre à traiter' ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</main>
