<?php
/**
 * Fiche dossier réelle (/gestion/dossiers/{réf}/), docs/gestion-phase-1.md (1B).
 * Le contrôleur a déjà vérifié le périmètre. Ici : le responsable (ou l'administrateur) traite et ouvre les pièces ;
 * la vue transversale (secrétaire, comptable) voit l'en-tête, les responsables et le suivi visible du client.
 *
 * @var array       $me
 * @var array       $request
 * @var array|null  $service
 * @var array       $affectations Poste => affectation
 * @var bool        $responsable
 * @var list<array> $documents, $events
 * @var list<string> $prenables, $transmissibles, $liberables Opérations d'affectation permises (Access)
 * @var array       $equipe      Poste => membres actifs
 */
use App\Models\ServiceRequest;
use App\Services\Access;

$ref = $request['reference'];
$errors = errors();
$labels = ['identity_number' => 'Passeport ou CNI'];
foreach ($service['fields'] ?? [] as [$name, $label]) {
    $labels[$name] = $label;
}
$infos = ['E-mail' => $request['email'], 'Téléphone' => $request['phone']];
if ($request['destination']) {
    $infos[$labels['destination'] ?? 'Destination'] = $request['destination'];
}
// Identité et champs du formulaire (passeport…) : réservés au responsable ; la vue transversale garde les coordonnées (§3.5)
if ($responsable) {
    if ($request['birth_date']) {
        $infos['Date de naissance'] = date('d/m/Y', strtotime($request['birth_date']));
    }
    foreach ($request['details'] as $key => $value) {
        $infos[$labels[$key] ?? $key] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? date('d/m/Y', strtotime((string) $value)) : (string) $value;
    }
}
$initiales = static fn (string $first, string $last): string => mb_substr($first, 0, 1) . mb_substr($last, 0, 1);
$auteurs = ['client' => 'Client'] + Access::POSTES;
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/dossiers/')) ?>"><?= gestion_icone('collapse', 15) ?> Dossiers</a></p>

<header class="g-case-head">
    <div>
        <div class="g-case-title">
            <h2><?= e($request['first_name'] . ' ' . $request['last_name']) ?></h2>
            <?= g_badge(ServiceRequest::statusLabel($request['status'], true), ServiceRequest::statusTone($request['status'])) ?>
        </div>
        <p class="g-case-sub"><?= e($ref) ?> · <?= e($service['title'] ?? $request['service']) ?><?= $request['destination'] ? ' · ' . e($request['destination']) : '' ?> · reçu <?= e(g_date($request['created_at'])) ?></p>
        <div class="g-owners" aria-label="Responsables par poste">
            <?php foreach (Access::AFFECTATIONS as $poste => $libelle): ?>
                <?php
                $a = $affectations[$poste] ?? null;
                $tenu = !empty($a['user_id']);
                $file = Access::enFile($poste, $affectations);
                ?>
                <span class="g-owner<?= $tenu ? '' : ($file ? ' is-empty' : ' is-later') ?>">
                    <span class="g-avatar is-sm" aria-hidden="true"><?= $tenu ? e($initiales($a['first_name'], $a['last_name'])) : ($file ? '…' : '–') ?></span>
                    <span><small><?= e($libelle) ?></small><?= $tenu ? e($a['first_name'] . ' ' . $a['last_name']) . ((int) $a['user_id'] === (int) $me['id'] ? ' (vous)' : '') : ($file ? 'dans la file' : 'pas encore transmis') ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($responsable): ?>
        <div class="g-case-actions">
            <a class="g-btn" href="tel:<?= e(str_replace(' ', '', $request['phone'])) ?>"><?= gestion_icone('phone', 16) ?> Appeler</a>
            <a class="g-btn" href="https://wa.me/<?= e(preg_replace('/\D/', '', $request['phone'])) ?>"><?= gestion_icone('chat', 16) ?> WhatsApp</a>
            <a class="g-btn" href="mailto:<?= e($request['email']) ?>?subject=<?= e(rawurlencode('Votre dossier ' . $ref)) ?>"><?= gestion_icone('send', 16) ?> E-mail</a>
        </div>
    <?php endif; ?>
</header>

<div class="g-grid g-grid-2">
    <div class="g-grid">
        <?php if ($responsable): ?>
            <section class="g-card is-ok" aria-labelledby="traiter">
                <div class="g-card-head"><h3 id="traiter"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('check', 18) ?></span>Traiter le dossier</h3></div>
                <form method="post" action="<?= e(url('/gestion/dossiers/' . $ref . '/')) ?>">
                    <?= csrf_field() ?>
                    <label class="g-form-row">Statut
                        <select class="g-select" name="status">
                            <?php foreach (ServiceRequest::STATUSES as $cle => [, $court]): ?>
                                <option value="<?= e($cle) ?>"<?= $cle === $request['status'] ? ' selected' : '' ?>><?= e($court) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if (isset($errors['status'])): ?><p class="g-field-error"><?= e($errors['status']) ?></p><?php endif; ?>
                    <label class="g-form-row">Note
                        <textarea class="g-textarea" name="note" placeholder="Pièces manquantes, prochaine étape, rendez-vous consulaire…"<?= isset($errors['note']) ? ' aria-invalid="true"' : '' ?>><?= e(old('note')) ?></textarea>
                    </label>
                    <?php if (isset($errors['note'])): ?><p class="g-field-error"><?= e($errors['note']) ?></p><?php endif; ?>
                    <label class="g-check-row"><input type="checkbox" name="visible" value="1"<?= old('visible', '1') === '1' ? ' checked' : '' ?>> Visible par le client dans son espace (sinon, note interne)</label>
                    <button class="g-btn g-btn-primary" type="submit"><?= gestion_icone('check', 16) ?> Enregistrer</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="g-card" aria-labelledby="historique">
            <div class="g-card-head"><h3 id="historique"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('clock', 18) ?></span>Historique</h3><?php if (!$responsable): ?><span class="g-muted" style="font-size:.76rem">suivi visible par le client</span><?php endif; ?></div>
            <?php if ($events === []): ?>
                <p class="g-empty">Aucun événement.</p>
            <?php else: ?>
                <ul class="g-history">
                    <?php foreach (array_reverse($events) as $event): ?>
                        <li class="<?= empty($event['visible']) ? 'is-interne' : '' ?>">
                            <p class="g-history-head">
                                <strong><?= e($event['author_first_name'] ?? 'Système') ?></strong>
                                <?php if (!empty($event['author_role'])): ?><span class="g-muted"><?= e($auteurs[$event['author_role']] ?? '') ?></span><?php endif; ?>
                                <?php if ($event['type'] === 'statut'): ?><?= g_badge(ServiceRequest::statusLabel((string) $event['status'], true), ServiceRequest::statusTone((string) $event['status'])) ?><?php endif; ?>
                                <?php if (empty($event['visible'])): ?><span class="g-badge is-ori"><span class="g-dot is-ori"></span>Interne</span><?php endif; ?>
                                <small><?= e(g_date($event['created_at'], true)) ?></small>
                            </p>
                            <?php if (!empty($event['message'])): ?><p><?= nl2br(e($event['message'])) ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="g-grid">
        <?php if ($prenables !== [] || $transmissibles !== [] || $liberables !== []): ?>
            <section class="g-card is-rdv" aria-labelledby="affecter">
                <div class="g-card-head"><h3 id="affecter"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('swap', 18) ?></span>Affectation</h3></div>
                <div class="g-assign">
                    <?php foreach ($prenables as $poste): ?>
                        <form method="post" action="<?= e(url('/gestion/dossiers/' . $ref . '/attribution/')) ?>" class="g-assign-take">
                            <?= csrf_field() ?>
                            <input type="hidden" name="poste" value="<?= e($poste) ?>"><input type="hidden" name="operation" value="prendre">
                            <p>Ce dossier est dans la file des <?= e(mb_strtolower(Access::AFFECTATIONS[$poste])) ?>s.</p>
                            <button class="g-btn g-btn-primary" type="submit"><?= gestion_icone('check', 16) ?> Prendre le dossier</button>
                            <?php if (isset($errors['assigned_to_' . $poste])): ?><p class="g-field-error"><?= e($errors['assigned_to_' . $poste]) ?></p><?php endif; ?>
                        </form>
                    <?php endforeach; ?>
                    <?php foreach ($transmissibles as $poste): ?>
                        <?php $actuel = $affectations[$poste] ?? null; $erreur = $errors['assigned_to_' . $poste] ?? null; ?>
                        <form method="post" action="<?= e(url('/gestion/dossiers/' . $ref . '/attribution/')) ?>" class="g-assign-row">
                            <?= csrf_field() ?>
                            <input type="hidden" name="poste" value="<?= e($poste) ?>"><input type="hidden" name="operation" value="transmettre">
                            <label class="g-form-row"><?= !empty($actuel['user_id']) ? 'Réaffecter : ' : 'Transmettre : ' ?><?= e(mb_strtolower(Access::AFFECTATIONS[$poste])) ?>
                                <select class="g-select" name="assigned_to">
                                    <option value="">Dans la file du poste</option>
                                    <?php foreach ($equipe[$poste] ?? [] as $membre): ?>
                                        <option value="<?= (int) $membre['id'] ?>"<?= (int) $membre['id'] === (int) ($actuel['user_id'] ?? 0) ? ' selected' : '' ?>><?= e($membre['first_name'] . ' ' . $membre['last_name']) ?><?= (int) $membre['id'] === (int) $me['id'] ? ' (vous)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="g-btn g-btn-sm" type="submit"><?= gestion_icone('send', 15) ?> Transmettre</button>
                            <?php if ($erreur): ?><p class="g-field-error"><?= e($erreur) ?></p><?php endif; ?>
                        </form>
                    <?php endforeach; ?>
                    <?php foreach ($liberables as $poste): ?>
                        <form method="post" action="<?= e(url('/gestion/dossiers/' . $ref . '/attribution/')) ?>" class="g-assign-release">
                            <?= csrf_field() ?>
                            <input type="hidden" name="poste" value="<?= e($poste) ?>"><input type="hidden" name="operation" value="liberer">
                            <button class="g-link-btn" type="submit">Remettre dans la file (<?= e(mb_strtolower(Access::AFFECTATIONS[$poste])) ?>)</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="g-card is-ech" aria-labelledby="pieces">
            <div class="g-card-head"><h3 id="pieces"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('file', 18) ?></span>Pièces <span class="g-count"><?= count($documents) ?></span></h3></div>
            <?php if (!$responsable): ?>
                <p class="g-locked"><?= gestion_icone('lock', 16) ?> Les pièces s’ouvrent pour le responsable affecté<?= $prenables !== [] ? ' : prenez le dossier pour y accéder' : '' ?>.</p>
            <?php elseif ($documents === []): ?>
                <p class="g-empty">Aucune pièce jointe.</p>
            <?php else: ?>
                <ul class="g-docs">
                    <?php foreach ($documents as $d): ?>
                        <li class="g-doc">
                            <span class="g-doc-icon" aria-hidden="true"><?= gestion_icone('file', 17) ?></span>
                            <div><strong><?= e($d['original_name']) ?></strong><p><?= $d['mime'] === 'application/pdf' ? 'PDF' : 'Image' ?> · <?= e(g_taille((int) $d['size'])) ?> · <?= e(g_date($d['created_at'])) ?></p></div>
                            <div class="g-doc-actions"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/dossiers/' . $ref . '/pieces/' . $d['id'] . '/')) ?>"><?= gestion_icone('download', 15) ?> Ouvrir</a></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="g-muted" style="margin:.7rem 0 0;font-size:.74rem">Chaque ouverture est inscrite au journal d’audit.</p>
            <?php endif; ?>
        </section>

        <section class="g-card" aria-labelledby="infos">
            <div class="g-card-head"><h3 id="infos"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('users', 18) ?></span>Informations transmises</h3></div>
            <dl class="g-dl">
                <?php foreach ($infos as $label => $value): ?>
                    <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
                <?php endforeach; ?>
            </dl>
            <?php if ($responsable && !empty($request['message'])): ?><p class="g-quote"><?= nl2br(e($request['message'])) ?></p><?php endif; ?>
            <?php if (!$responsable): ?><p class="g-locked"><?= gestion_icone('lock', 16) ?> Identité et précisions du formulaire réservées au responsable.</p><?php endif; ?>
        </section>
    </div>
</div>
