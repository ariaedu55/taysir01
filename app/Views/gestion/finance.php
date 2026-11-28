<?php
use App\Models\CaseFinance;
use App\Models\RequestRequirement;

$ref = $request['reference'];
$action = url('/gestion/dossiers/' . $ref . '/finance/');
$quote = $finance['quote'];
$open = RequestRequirement::isOpen($request);
?>
<p><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/dossiers/' . $ref . '/')) ?>">Retour au dossier <?= e($ref) ?></a></p>
<?php if ($error = errors()['finance'] ?? null): ?><p class="g-field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="g-grid<?= $open ? ' g-grid-2' : '' ?>">
<?php partial('case-finance', ['finance' => $finance, 'request' => $request, 'staff' => true, 'me' => $me]); ?>
<?php if ($open): ?>
    <div class="g-grid">
        <?php if (!$finance['payments']): ?>
            <form class="g-card g-grid" method="post" action="<?= e($action) ?>">
                <div class="g-card-head"><h3>Devis</h3></div>
                <?= csrf_field() ?><input type="hidden" name="action" value="quote">
                <label class="g-form-row">Intitulé <input class="g-input" name="title" maxlength="190" required value="<?= e($quote['title'] ?? '') ?>"></label>
                <label class="g-form-row">Montant en FCFA <input class="g-input" name="amount" type="number" min="1" max="1000000000" required value="<?= e((string) ($quote['amount'] ?? '')) ?>"></label>
                <div><button class="g-btn g-btn-success">Enregistrer le devis</button></div>
            </form>
        <?php endif; ?>
        <?php if ($quote): ?>
            <form class="g-card g-grid" method="post" action="<?= e($action) ?>">
                <div class="g-card-head"><h3>Échéance</h3></div>
                <?= csrf_field() ?><input type="hidden" name="action" value="installment">
                <label class="g-form-row">Date <input class="g-input" name="date" type="date" required></label>
                <label class="g-form-row">Montant en FCFA <input class="g-input" name="amount" type="number" min="1" max="1000000000" required></label>
                <div><button class="g-btn g-btn-success">Enregistrer l’échéance</button></div>
            </form>
            <form class="g-card g-grid" method="post" action="<?= e($action) ?>">
                <div class="g-card-head"><h3>Règlement reçu</h3></div>
                <?= csrf_field() ?><input type="hidden" name="action" value="payment">
                <input type="hidden" name="form_token" value="<?= e($paymentToken) ?>">
                <label class="g-form-row">Date de réception <input class="g-input" name="date" type="date" max="<?= gmdate('Y-m-d') ?>" required></label>
                <label class="g-form-row">Montant en FCFA <input class="g-input" name="amount" type="number" min="1" max="1000000000" required></label>
                <label class="g-form-row">Mode <select class="g-select" name="method"><?php foreach (CaseFinance::METHODS as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                <div><button class="g-btn g-btn-success">Enregistrer le règlement</button></div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
