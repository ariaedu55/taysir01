<?php
/**
 * Back-office : traitement d'une demande.
 *
 * @var array       $request
 * @var array|null  $service
 * @var list<array> $documents
 * @var list<array> $events   Historique complet, notes internes comprises
 * @var list<array> $staff    Membres actifs de l'équipe
 * @var array       $me
 */
use App\Models\ServiceRequest;

$reference = $request['reference'];
$errors = errors();
?>
<main id="contenu" class="dashboard portal admin">
    <section class="wrap dashboard-head">
        <div>
            <?php partial('admin-nav', ['active' => 'demandes']); ?>
            <h1><?= e($service['title'] ?? 'Demande') ?> <em><?= e($reference) ?></em></h1>
            <p class="dashboard-profile">Reçue le <?= e(date('d/m/Y à H\hi', strtotime($request['created_at']))) ?> · <?= e($request['first_name'] . ' ' . $request['last_name']) ?></p>
        </div>
        <?php partial('status', ['label' => ServiceRequest::statusLabel($request['status'], true), 'tone' => ServiceRequest::statusTone($request['status'])]); ?>
    </section>

    <div class="wrap portal-detail">
        <div class="portal-stack">
            <?php partial('flash'); ?>

            <section class="account glass" aria-labelledby="traiter-titre">
                <h2 id="traiter-titre">Traiter la demande</h2>
                <form class="portal-form" method="post" action="<?= e(url('/admin/demandes/' . $reference . '/')) ?>">
                    <?= csrf_field() ?>
                    <div class="field-grid">
                        <?php partial('form-field', [
                            'name' => 'status', 'label' => 'Statut', 'type' => 'select', 'required' => true,
                            'options' => array_map(static fn ($status) => $status[1], ServiceRequest::STATUSES),
                            'value' => $request['status'],
                        ]); ?>
                        <?php partial('form-field', ['name' => 'note', 'label' => 'Note', 'type' => 'textarea', 'hint' => 'Pièces manquantes, prochaine étape, rendez-vous consulaire…']); ?>
                    </div>
                    <label class="checkbox">
                        <input type="checkbox" name="visible" value="1"<?= old('visible', '1') === '1' ? ' checked' : '' ?>>
                        Afficher cette note au client dans son espace (sinon, elle reste interne à l’équipe)
                    </label>
                    <button class="button primary" type="submit">Enregistrer</button>
                </form>
            </section>

            <section class="account glass" aria-labelledby="historique-titre">
                <h2 id="historique-titre">Historique</h2>
                <?php partial('request-timeline', ['events' => $events, 'staffView' => true]); ?>
            </section>
        </div>

        <aside class="portal-stack">
            <?php
            $assignee = null;
            foreach ($staff as $member) {
                if ((int) $member['id'] === (int) $request['assigned_to']) {
                    $assignee = $member;
                }
            }
            $assignError = $errors['assigned_to'] ?? null;
            ?>
            <section class="account glass assignment" aria-labelledby="attribution-titre">
                <h2 id="attribution-titre">Conseiller</h2>
                <p class="assignment-current"><?= $assignee ? e($assignee['first_name'] . ' ' . $assignee['last_name']) . ((int) $assignee['id'] === (int) $me['id'] ? ' (vous)' : '') : 'Aucun conseiller attribué' ?></p>
                <form method="post" action="<?= e(url('/admin/demandes/' . $reference . '/attribution/')) ?>" class="assignment-form">
                    <?= csrf_field() ?>
                    <label class="sr-only" for="attribution">Attribuer à</label>
                    <div class="field-control field-select">
                        <select id="attribution" name="assigned_to"<?= $assignError ? ' aria-invalid="true"' : '' ?>>
                            <option value="">Personne</option>
                            <?php foreach ($staff as $member): ?>
                                <option value="<?= (int) $member['id'] ?>"<?= (int) $member['id'] === (int) $request['assigned_to'] ? ' selected' : '' ?>><?= e($member['first_name'] . ' ' . $member['last_name']) ?><?= (int) $member['id'] === (int) $me['id'] ? ' (vous)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="button light button-sm" type="submit">Attribuer</button>
                </form>
                <?php if ((int) $request['assigned_to'] !== (int) $me['id']): ?>
                    <form method="post" action="<?= e(url('/admin/demandes/' . $reference . '/attribution/')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="assigned_to" value="moi">
                        <button class="button primary button-sm" type="submit">Me l’attribuer</button>
                    </form>
                <?php endif; ?>
                <?php if ($assignError): ?><p class="field-error"><?= e($assignError) ?></p><?php endif; ?>
            </section>
            <?php partial('request-summary', ['request' => $request, 'service' => $service]); ?>
            <?php partial('request-documents', ['documents' => $documents, 'base' => '/admin/demandes/' . $reference . '/documents/']); ?>
            <div class="account glass portal-contact">
                <a class="button light" href="tel:<?= e(str_replace(' ', '', $request['phone'])) ?>">Appeler</a>
                <a class="button light" href="https://wa.me/<?= e(preg_replace('/\D/', '', $request['phone'])) ?>">WhatsApp</a>
                <a class="button light" href="mailto:<?= e($request['email']) ?>?subject=<?= e(rawurlencode('Votre demande ' . $reference)) ?>">E-mail</a>
            </div>
        </aside>
    </div>
</main>
