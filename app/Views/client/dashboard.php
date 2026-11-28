<?php
use App\Models\ServiceRequest;

require_once BASE_PATH . '/app/Views/gestion/icones.php';

$list = '/mon-espace/demandes/';
$first = $requests[0] ?? null;
$path = $first ? $list . $first['reference'] . '/' : '';
$serviceTitle = $first ? (ServiceRequest::service($first['service'])['title'] ?? 'Votre dossier') : null;
[$message, $action, $target, $icon, $tone] = match (true) {
    !empty($first['next_piece']) => ['Une pièce est attendue pour votre dossier.', 'Déposer la pièce', $path . '#piece-' . $first['next_piece'], 'upload', 'amber'],
    ($first['status'] ?? null) === 'attente-client' => ['Votre conseiller attend une réponse sur votre dossier.', 'Voir ce qui est attendu', $path, 'chat', 'amber'],
    $openCount > 0 && $first !== null => ['Retrouvez les étapes et les nouvelles de votre dossier.', 'Suivre mon dossier', $path, 'route', 'blue'],
    $appointmentCount > 0 => ['Retrouvez la date et les informations de votre rendez-vous.', 'Voir mes rendez-vous', $list . '#rdv-titre', 'calendar', 'blue'],
    $requestCount > 0 => ['Retrouvez les démarches que vous avez déjà effectuées.', 'Voir mes demandes', $list, 'folder', 'blue'],
    default => ['Choisissez un service pour commencer votre démarche.', 'Faire une demande', $list . '#nouvelle-titre', 'route', 'blue'],
};
$ownerParent = $user['profile'] === 'parent';
$stats = [
    ['Dossiers en cours', $openCount, 'folder', 'blue', $list],
    ['Dossiers en attente', $waitingCount, 'clock', 'amber', $list],
    ['Rendez-vous à venir', $appointmentCount, 'calendar', 'green', $list . '#rdv-titre'],
];
?>
<main id="contenu" class="dashboard portal client-wrap">
    <section class="client-intro" aria-labelledby="bienvenue-titre">
        <div><p class="client-label">Mon espace</p><h1 id="bienvenue-titre">Bonjour <?= e($user['first_name']) ?></h1></div>
        <?php if ($profileLabel !== ''): ?><span class="client-profile"><?= e($profileLabel) ?></span><?php endif; ?>
    </section>
    <?php partial('flash'); ?>

    <section class="client-next client-tone-<?= e($tone) ?>" aria-labelledby="prochaine-action">
        <span class="client-icon"><?= gestion_icone($icon, 24) ?></span>
        <div class="client-next-copy">
            <p class="client-label">Prochaine action</p>
            <h2 id="prochaine-action"><?= e($path !== '' && str_starts_with($target, $path) ? $serviceTitle : ($target === $list . '#rdv-titre' ? 'Votre rendez-vous' : ($first ? 'Vos demandes' : 'Votre parcours chez Taysir'))) ?></h2>
            <p><?= e($message) ?></p>
        </div>
        <a class="button primary" href="<?= e(url($target)) ?>"><?= e($action) ?> <?= gestion_icone('arrow', 16) ?></a>
    </section>

    <div class="client-metrics">
        <?php foreach ($stats as [$label, $count, $icon, $tone, $href]): ?>
            <a class="client-metric client-tone-<?= e($tone) ?>" href="<?= e(url($href)) ?>">
                <span class="client-icon"><?= gestion_icone($icon, 18) ?></span><strong><?= (int) $count ?></strong><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="portal-detail">
        <section class="account glass" aria-labelledby="demandes-titre">
            <div class="client-section-head"><h2 id="demandes-titre">Mes dossiers</h2><?php if ($requests !== []): ?><a href="<?= e(url($list)) ?>">Tout voir</a><?php endif; ?></div>
            <?php if ($requests === []): ?>
                <p class="client-empty-lead">Vous n’avez pas encore de dossier. Voici comment Taysir vous accompagne :</p>
                <ol class="client-steps">
                    <li class="client-tone-blue"><span class="client-icon"><?= gestion_icone('route', 18) ?></span><strong>1. Votre demande</strong><span>Choisissez le service : admission, logement, visa, prise en charge.</span></li>
                    <li class="client-tone-amber"><span class="client-icon"><?= gestion_icone('upload', 18) ?></span><strong>2. Vos pièces</strong><span>Déposez vos documents en ligne, en toute confidentialité.</span></li>
                    <li class="client-tone-green"><span class="client-icon"><?= gestion_icone('check', 18) ?></span><strong>3. Le suivi</strong><span>Suivez chaque étape ici ; votre conseiller vous tient informé.</span></li>
                </ol>
                <?php if ($target !== $list . '#nouvelle-titre'): ?><p><a class="button primary" href="<?= e(url($list . '#nouvelle-titre')) ?>">Faire une demande <?= gestion_icone('arrow', 16) ?></a></p><?php endif; ?>
            <?php else: ?>
                <?php $iconeService = ['admission' => 'compass', 'logement' => 'home', 'visa' => 'doc', 'prise-en-charge' => 'wallet', 'billet' => 'plane', 'hotel' => 'building']; ?>
                <ul class="client-rows">
                    <?php foreach ($requests as $request): ?>
                        <li><a class="client-row" href="<?= e(url($list . $request['reference'] . '/')) ?>">
                            <span class="client-icon client-tone-<?= in_array($request['service'], ['billet', 'hotel'], true) ? 'green' : 'blue' ?>"><?= gestion_icone($iconeService[$request['service']] ?? 'folder', 18) ?></span>
                            <span class="client-row-main"><strong><?= e(ServiceRequest::service($request['service'])['title'] ?? 'Demande') ?></strong>
                                <span><?= e($request['reference']) ?><?= $request['destination'] ? ' · ' . e($request['destination']) : '' ?></span></span>
                            <?php partial('status', ['label' => ServiceRequest::statusLabel($request['status']), 'tone' => ServiceRequest::statusTone($request['status'])]); ?>
                            <span class="client-row-go" aria-hidden="true"><?= gestion_icone('chevron', 18) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <aside class="portal-stack">
            <section class="account glass" aria-labelledby="raccourcis-client">
                <h2 id="raccourcis-client">Autres démarches</h2>
                <ul class="client-shortcuts">
                    <?php if ($target !== $list . '#nouvelle-titre'): ?><li><a href="<?= e(url($list . '#nouvelle-titre')) ?>"><?= gestion_icone('plus', 18) ?> Faire une demande</a></li><?php endif; ?>
                    <li><a href="<?= e(url('/rendez_vous_orientation/')) ?>"><?= gestion_icone('calendar', 18) ?> Prendre rendez-vous</a></li>
                    <?php if ($ownerParent): ?><li><a href="<?= e(url($list . '#dossiers-partages')) ?>"><?= gestion_icone('users', 18) ?> Dossiers partagés</a></li><?php endif; ?>
                    <?php if ($comptesRendus !== []): ?><li><a href="#comptes-rendus"><?= gestion_icone('file', 18) ?> Comptes rendus</a></li><?php endif; ?>
                </ul>
            </section>
            <details class="account glass client-account" id="mon-compte">
                <summary>Mon compte</summary>
                <dl>
                    <div><dt>Nom</dt><dd><?= e($user['first_name'] . ' ' . $user['last_name']) ?></dd></div>
                    <div><dt>E-mail</dt><dd><?= e($user['email']) ?></dd></div>
                    <div><dt>Téléphone</dt><dd><?= e($user['phone'] ?? 'Non renseigné') ?></dd></div>
                </dl>
            </details>
        </aside>
    </div>

    <?php if ($comptesRendus !== []): ?>
        <section class="account glass client-notes" id="comptes-rendus" aria-labelledby="cr-titre">
            <h2 id="cr-titre">Comptes rendus de vos conseillers</h2>
            <ul>
                <?php foreach ($comptesRendus as $cr): ?>
                    <li>
                        <p class="client-note-meta"><?= e($cr['author_first_name']) ?> · <?= e(\App\Services\Access::libelle($cr['author_poste'])) ?> · <?= e(date('d/m/Y', strtotime($cr['created_at']))) ?><?= $cr['appointment_reference'] ? ' · entretien ' . e($cr['appointment_reference']) : '' ?></p>
                        <p><?= nl2br(e($cr['body'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
