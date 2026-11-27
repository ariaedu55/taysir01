<?php
/**
 * Messages de la page Contact (/gestion/messages/), docs/gestion-phase-1.md (1B).
 *
 * @var list<array>        $messages
 * @var array<string, int> $counts
 * @var string|null        $status
 */
use App\Models\ContactMessage;
?>
<div class="g-toolbar">
    <div class="g-views" role="group" aria-label="Filtrer par statut">
        <a class="g-view-link" href="<?= e(url('/gestion/messages/')) ?>"<?= $status === null ? ' aria-current="true"' : '' ?>>Tous<span><?= (int) array_sum($counts) ?></span></a>
        <?php foreach (ContactMessage::STATUSES as $cle => [$label]): ?>
            <a class="g-view-link" href="<?= e(url('/gestion/messages/?statut=' . $cle)) ?>"<?= $status === $cle ? ' aria-current="true"' : '' ?>><?= e($label) ?><span><?= (int) ($counts[$cle] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($messages === []): ?>
    <div class="g-card"><p class="g-empty">Aucun message pour ce filtre.</p></div>
<?php else: ?>
    <ul class="g-cards-list">
        <?php foreach ($messages as $m): ?>
            <li class="g-card g-rdv">
                <div class="g-rdv-info">
                    <p class="g-rdv-top"><strong><?= e($m['name']) ?></strong><?= g_badge(ContactMessage::STATUSES[$m['status']][0], ContactMessage::STATUSES[$m['status']][1]) ?></p>
                    <p class="g-muted">
                        Reçu <?= e(g_date($m['created_at'], true)) ?>
                        · <a href="tel:<?= e(str_replace(' ', '', $m['phone'])) ?>"><?= e($m['phone']) ?></a>
                        · <a href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Votre message à Taysir Orientation Voyage')) ?>"><?= e($m['email']) ?></a>
                        <?= $m['user_id'] ? ' · compte client' : '' ?>
                        <?php if ($m['status'] === 'traite' && $m['handler_first_name']): ?> · traité par <?= e($m['handler_first_name']) ?> <?= e(g_date($m['handled_at'])) ?><?php endif; ?>
                    </p>
                    <p class="g-quote"><?= nl2br(e($m['message'])) ?></p>
                </div>
                <form method="post" action="<?= e(url('/gestion/messages/' . $m['id'] . '/')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= $m['status'] === 'nouveau' ? 'traite' : 'nouveau' ?>">
                    <button class="g-btn g-btn-sm<?= $m['status'] === 'nouveau' ? ' g-btn-primary' : '' ?>" type="submit"><?= $m['status'] === 'nouveau' ? 'Marquer comme traité' : 'Remettre à traiter' ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
