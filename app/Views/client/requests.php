<?php
/**
 * Espace client : demandes, rendez-vous et choix d'une nouvelle démarche.
 * Refonte du 28/11/2026 : services en cartes (icône, phrase, repères, bouton), états vides qui guident,
 * choix du service en tête tant que le client n'a rien demandé.
 *
 * @var list<array> $requests
 * @var list<array>|null $sharedRequests
 * @var list<array> $appointments
 * @var list<array> $services
 */
use App\Models\Appointment;
use App\Models\ServiceRequest;

require_once BASE_PATH . '/app/Views/gestion/icones.php';

$serviceNames = array_column($services, 'title', 'key');
// Icône et famille de chaque service : études (bleu) ou voyage (vert)
$visuels = [
    'admission' => ['compass', 'etudes'], 'logement' => ['home', 'etudes'], 'visa' => ['doc', 'etudes'],
    'prise-en-charge' => ['wallet', 'etudes'], 'billet' => ['plane', 'voyage'], 'hotel' => ['building', 'voyage'],
];
$familles = [
    'etudes' => ['Études à l’étranger', 'De l’admission jusqu’à l’installation dans votre ville d’études.', 'blue'],
    'voyage' => ['Voyage', 'Votre billet et votre hébergement, réservés par l’agence.', 'green'],
];
$parFamille = ['etudes' => [], 'voyage' => []];
foreach ($services as $service) {
    $parFamille[$visuels[$service['key']][1] ?? 'etudes'][] = $service;
}
$icone = static fn (string $key): string => $visuels[$key][0] ?? 'folder';
$aucuneDemande = $requests === [] && ($sharedRequests ?? []) === [];

$choix = static function () use ($familles, $parFamille, $icone): void { ?>
    <section class="client-choice" aria-labelledby="nouvelle-titre">
        <div class="client-choice-head">
            <h2 id="nouvelle-titre">Nouvelle demande</h2>
            <p>Choisissez la démarche qui vous concerne. Un conseiller Taysir prend ensuite votre dossier en charge.</p>
        </div>
        <?php foreach ($familles as $cle => [$titre, $sousTitre, $ton]): ?>
            <?php if ($parFamille[$cle] === []) continue; ?>
            <div class="client-family">
                <h3 class="client-family-title"><?= e($titre) ?> <span><?= e($sousTitre) ?></span></h3>
                <ul class="client-services">
                    <?php foreach ($parFamille[$cle] as $service): ?>
                        <li class="client-service client-tone-<?= e($ton) ?>">
                            <span class="client-icon"><?= gestion_icone($icone($service['key']), 22) ?></span>
                            <h4><?= e($service['title']) ?></h4>
                            <p><?= e($service['lead']) ?></p>
                            <ul class="client-service-tags">
                                <li><?= gestion_icone('route', 14) ?> <?= count($service['steps'] ?? []) ?> étapes</li>
                                <?php if (!empty($service['documents'])): ?><li><?= gestion_icone('upload', 14) ?> Pièces en ligne</li><?php endif; ?>
                                <?php if (!empty($service['identity'])): ?><li><?= gestion_icone('doc', 14) ?> Pièce d’identité</li><?php endif; ?>
                            </ul>
                            <a class="button primary client-service-cta" href="<?= e(url('/' . $service['slug'] . '/#formulaire')) ?>">Commencer <?= gestion_icone('arrow', 16) ?><span class="sr-only"> : <?= e($service['title']) ?></span></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <p class="client-help"><?= gestion_icone('chat', 18) ?> <span>Vous hésitez ? <a href="<?= e(url('/rendez_vous_orientation/')) ?>">Prenez rendez-vous avec un conseiller</a>, il vous oriente gratuitement vers la bonne démarche.</span></p>
    </section>
<?php };
?>
<main id="contenu" class="dashboard portal client-wrap">
    <section class="client-intro" aria-labelledby="mes-demandes-titre">
        <div>
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/mon-espace/')) ?>">Mon espace</a></li>
                    <li aria-current="page">Mes demandes</li>
                </ol>
            </nav>
            <h1 id="mes-demandes-titre">Mes demandes</h1>
            <p class="client-intro-lead">Suivez vos dossiers et vos rendez-vous, ou lancez une nouvelle démarche.</p>
        </div>
        <?php if (!$aucuneDemande): ?><a class="button primary" href="#nouvelle-titre"><?= gestion_icone('plus', 16) ?> Nouvelle demande</a><?php endif; ?>
    </section>

    <?php partial('flash'); ?>

    <?php if ($aucuneDemande) $choix(); ?>

    <div class="client-columns">
        <section class="account" aria-labelledby="demandes-titre">
            <div class="client-section-head"><h2 id="demandes-titre"><?= gestion_icone('folder', 20) ?> Mes dossiers</h2></div>
            <?php if ($requests === []): ?>
                <div class="client-empty">
                    <span class="client-icon"><?= gestion_icone('folder', 20) ?></span>
                    <div><strong>Aucune demande pour l’instant</strong><p>Choisissez une démarche<?= $aucuneDemande ? ' ci-dessus' : ' ci-dessous' ?> : votre dossier apparaîtra ici, avec chaque étape.</p></div>
                </div>
            <?php else: ?>
                <ul class="client-rows">
                    <?php foreach ($requests as $request): ?>
                        <li>
                            <a class="client-row" href="<?= e(url('/mon-espace/demandes/' . $request['reference'] . '/')) ?>">
                                <span class="client-icon client-tone-<?= ($visuels[$request['service']][1] ?? 'etudes') === 'voyage' ? 'green' : 'blue' ?>"><?= gestion_icone($icone($request['service']), 18) ?></span>
                                <span class="client-row-main">
                                    <strong><?= e($serviceNames[$request['service']] ?? $request['service']) ?></strong>
                                    <span><?= e($request['reference']) ?><?= $request['destination'] ? ' · ' . e($request['destination']) : '' ?> · envoyée le <?= e(date('d/m/Y', strtotime($request['created_at']))) ?></span>
                                </span>
                                <?php partial('status', ['label' => ServiceRequest::statusLabel($request['status']), 'tone' => ServiceRequest::statusTone($request['status'])]); ?>
                                <span class="client-row-go" aria-hidden="true"><?= gestion_icone('chevron', 18) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="account" aria-labelledby="rdv-titre">
            <div class="client-section-head"><h2 id="rdv-titre"><?= gestion_icone('calendar', 20) ?> Rendez-vous</h2><?php if ($appointments !== []): ?><a href="<?= e(url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a><?php endif; ?></div>
            <?php if ($appointments === []): ?>
                <div class="client-empty">
                    <span class="client-icon client-tone-green"><?= gestion_icone('calendar', 20) ?></span>
                    <div><strong>Aucun rendez-vous prévu</strong><p>Rencontrez un conseiller à l’agence, par téléphone ou en visio.</p>
                        <a class="button client-soft" href="<?= e(url('/rendez_vous_orientation/')) ?>"><?= gestion_icone('calendar', 16) ?> Prendre rendez-vous</a></div>
                </div>
            <?php else: ?>
                <ul class="client-rows">
                    <?php foreach ($appointments as $appointment): ?>
                        <li class="client-row client-row-static">
                            <span class="client-icon client-tone-green"><?= gestion_icone('calendar', 18) ?></span>
                            <span class="client-row-main">
                                <strong><?= e(Appointment::MOTIVES[$appointment['motive']] ?? $appointment['motive']) ?></strong>
                                <span>
                                    <?= e($appointment['reference']) ?> · <?= e(Appointment::MODES[$appointment['mode']] ?? '') ?> ·
                                    <?php if ($appointment['scheduled_at']): ?>
                                        le <?= e(str_replace(':', 'h', Appointment::local($appointment['starts_at_utc'] ?: $appointment['scheduled_at'], Appointment::zone($appointment['agency_id'] ? (int) $appointment['agency_id'] : null), 'd/m/Y à H:i'))) ?>
                                    <?php else: ?>
                                        souhaité le <?= e(date('d/m/Y', strtotime($appointment['preferred_date']))) ?>, <?= $appointment['preferred_slot'] === 'matin' ? 'le matin' : 'l’après-midi' ?>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php partial('status', ['label' => Appointment::statusLabel($appointment['status']), 'tone' => Appointment::statusTone($appointment['status'])]); ?>
                            <?php if (($appointment['status'] === 'demande' && $appointment['preferred_date'] >= gmdate('Y-m-d'))
                                || ($appointment['status'] === 'confirme' && $appointment['starts_at_utc'] > gmdate('Y-m-d H:i:s'))): ?>
                                <form class="client-row-action" method="post" action="<?= e(url('/mon-espace/rendez-vous/' . $appointment['reference'] . '/annuler/')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="button client-danger" type="submit">Annuler<span class="sr-only"> le rendez-vous <?= e($appointment['reference']) ?></span></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($sharedRequests !== null): ?>
        <section class="account" id="dossiers-partages" aria-labelledby="dossiers-partages-titre">
            <div class="client-section-head"><h2 id="dossiers-partages-titre"><?= gestion_icone('users', 20) ?> Dossiers partagés avec vous</h2></div>
            <?php if ($sharedRequests === []): ?>
                <div class="client-empty">
                    <span class="client-icon"><?= gestion_icone('users', 20) ?></span>
                    <div><strong>Aucun dossier partagé</strong><p>Quand votre enfant vous donne accès à son dossier, il apparaît ici.</p></div>
                </div>
            <?php else: ?>
                <ul class="client-rows">
                    <?php foreach ($sharedRequests as $shared): ?>
                        <li>
                            <a class="client-row" href="<?= e(url('/mon-espace/demandes/' . $shared['reference'] . '/')) ?>">
                                <span class="client-icon"><?= gestion_icone('users', 18) ?></span>
                                <span class="client-row-main">
                                    <strong><?= e($shared['first_name'] . ' ' . $shared['last_name']) ?></strong>
                                    <span><?= e($serviceNames[$shared['service']] ?? $shared['service']) ?> · <?= e($shared['reference']) ?> · <?= $shared['can_deposit'] ? 'Lecture et dépôt de pièces attendues' : 'Lecture seule' ?></span>
                                </span>
                                <?php partial('status', ['label' => ServiceRequest::statusLabel($shared['status']), 'tone' => ServiceRequest::statusTone($shared['status'])]); ?>
                                <span class="client-row-go" aria-hidden="true"><?= gestion_icone('chevron', 18) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!$aucuneDemande) $choix(); ?>
</main>
