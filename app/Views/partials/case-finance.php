<?php
use App\Models\CaseFinance;

$quote = $finance['quote'];
$money = static fn (int|string $amount): string => number_format((int) $amount, 0, ',', ' ') . ' FCFA';
$paid = array_sum(array_map(static fn (array $payment): int => $payment['cancelled_at'] ? 0 : (int) $payment['amount'], $finance['payments']));
$base = $staff ? '/gestion/dossiers/' . $request['reference'] . '/finance/' : '/mon-espace/demandes/' . $request['reference'] . '/';
?>
<section class="<?= $staff ? 'g-card g-grid' : 'account glass portal-stack' ?>" aria-labelledby="finance-title">
    <?php if ($staff): ?>
        <div class="g-card-head"><h3 id="finance-title"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span>Devis et règlements</h3></div>
    <?php else: ?>
        <h2 id="finance-title">Devis et règlements</h2>
    <?php endif; ?>
    <?php if (!$quote): ?><p>Aucun devis enregistré.</p><?php else: ?>
        <p class="finance-quote-title"><?= e($quote['title']) ?></p>
        <dl class="finance-totals">
            <div><dt>Devis</dt><dd><?= e($money($quote['amount'])) ?></dd></div>
            <div><dt>Réglé</dt><dd><?= e($money($paid)) ?></dd></div>
            <div><dt>Solde</dt><dd><?= e($money((int) $quote['amount'] - $paid)) ?></dd></div>
        </dl>
        <?php if ($finance['installments']): ?>
            <h3 class="<?= $staff ? 'g-subhead' : '' ?>">Échéancier</h3>
            <ul class="finance-installments"><?php foreach ($finance['installments'] as $item): ?>
                <li><time datetime="<?= e($item['due_date']) ?>"><?= e(date('d/m/Y', strtotime($item['due_date']))) ?></time><strong><?= e($money($item['amount'])) ?></strong></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
        <h3 class="<?= $staff ? 'g-subhead' : '' ?>">Reçus</h3>
        <?php if (!$finance['payments']): ?><p>Aucun règlement enregistré.</p><?php endif; ?>
        <?php foreach ($finance['payments'] as $payment): ?>
            <div class="<?= $staff ? 'g-requirement' : 'portal-requirement' ?>">
                <strong><?= e($money($payment['amount'])) ?> · <?= e(CaseFinance::METHODS[$payment['method']]) ?></strong>
                <span><time datetime="<?= e($payment['received_on']) ?>"><?= e(date('d/m/Y', strtotime($payment['received_on']))) ?></time> · <?= $payment['cancelled_at'] ? 'Annulé' : 'Enregistré' ?></span>
                <a href="<?= e(url($base . 'recus/' . $payment['receipt'] . '/')) ?>">Télécharger le reçu <?= e($payment['receipt']) ?> (PDF)</a>
                <?php if ($staff && $payment['cancelled_at']): ?><p>Motif de l’annulation · <?= e($payment['reason']) ?></p><?php endif; ?>
                <?php if ($staff && $me['role'] === 'admin' && !$payment['cancelled_at']): ?>
                    <details><summary>Annuler le règlement</summary>
                        <form method="post" action="<?= e(url($base)) ?>" class="g-grid">
                            <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="receipt" value="<?= e($payment['receipt']) ?>">
                            <label class="g-form-row">Motif de l’annulation <input class="g-input" name="reason" maxlength="500" required></label>
                            <div><button class="g-btn g-btn-danger g-btn-sm">Confirmer l’annulation</button></div>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
