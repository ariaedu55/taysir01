<?php
/**
 * Espace client : liste des demandes et des rendez-vous.
 *
 * @var list<array> $requests
 * @var list<array> $appointments
 * @var list<array> $services
 */
use App\Models\Appointment;
use App\Models\ServiceRequest;

$serviceNames = array_column($services, 'title', 'key');
?>
<main id="contenu" class="dashboard portal">
    <section class="wrap dashboard-head">
        <div data-reveal="up">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/mon-espace/')) ?>">Mon espace</a></li>
                    <li aria-current="page">Mes demandes</li>
                </ol>
            </nav>
            <h1>Mes <em>demandes</em></h1>
            <p class="dashboard-profile">Suivez chaque démarche et retrouvez vos rendez-vous.</p>
        </div>
        <a class="button primary" href="<?= e(url('/rendez_vous_orientation/')) ?>" data-reveal="up">Prendre rendez-vous</a>
    </section>

    <div class="wrap portal-stack">
        <?php partial('flash'); ?>

        <section class="account glass" aria-labelledby="demandes-titre" data-reveal="up">
            <h2 id="demandes-titre">Demandes en ligne</h2>
            <?php if ($requests === []): ?>
                <p class="portal-empty">Vous n’avez pas encore fait de demande. Choisissez un service ci-dessous pour commencer.</p>
            <?php else: ?>
                <ul class="request-list">
                    <?php foreach ($requests as $request): ?>
                        <li>
                            <a class="request-row" href="<?= e(url('/mon-espace/demandes/' . $request['reference'] . '/')) ?>">
                                <span class="request-main">
                                    <strong><?= e($serviceNames[$request['service']] ?? $request['service']) ?></strong>
                                    <span><?= e($request['reference']) ?><?= $request['destination'] ? ' · ' . e($request['destination']) : '' ?> · envoyée le <?= e(date('d/m/Y', strtotime($request['created_at']))) ?></span>
                                </span>
                                <?php partial('status', ['label' => ServiceRequest::statusLabel($request['status']), 'tone' => ServiceRequest::statusTone($request['status'])]); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="account glass" aria-labelledby="rdv-titre" data-reveal="up">
            <h2 id="rdv-titre">Rendez-vous</h2>
            <?php if ($appointments === []): ?>
                <p class="portal-empty">Aucun rendez-vous pour l’instant.</p>
            <?php else: ?>
                <ul class="request-list">
                    <?php foreach ($appointments as $appointment): ?>
                        <li>
                            <div class="request-row">
                                <span class="request-main">
                                    <strong><?= e(Appointment::MOTIVES[$appointment['motive']] ?? $appointment['motive']) ?></strong>
                                    <span>
                                        <?= e($appointment['reference']) ?> · <?= e(Appointment::MODES[$appointment['mode']] ?? '') ?> ·
                                        <?php if ($appointment['scheduled_at']): ?>
                                            le <?= e(date('d/m/Y à H\hi', strtotime($appointment['scheduled_at']))) ?>
                                        <?php else: ?>
                                            souhaité le <?= e(date('d/m/Y', strtotime($appointment['preferred_date']))) ?>, <?= $appointment['preferred_slot'] === 'matin' ? 'le matin' : 'l’après-midi' ?>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <?php partial('status', ['label' => Appointment::statusLabel($appointment['status']), 'tone' => Appointment::statusTone($appointment['status'])]); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section aria-labelledby="nouvelle-titre" data-reveal="up">
            <h2 class="portal-title" id="nouvelle-titre">Nouvelle demande</h2>
            <ul class="dashboard-modules" data-stagger>
                <?php foreach ($services as $service): ?>
                    <li data-reveal="up">
                        <a class="module glass" data-glow href="<?= e(url('/' . $service['slug'] . '/#formulaire')) ?>">
                            <h3><?= e($service['title']) ?></h3>
                            <p><?= e($service['lead']) ?></p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</main>
