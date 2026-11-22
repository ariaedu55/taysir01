<?php
/**
 * @var array       $user
 * @var int         $clientCount
 * @var array       $requestCounts, $appointmentCounts  Nombre par statut
 * @var int         $documentCount
 * @var int         $messageCount   Messages de contact à traiter
 * @var list<array> $latest
 * @var array       $profiles
 */
?>
<main id="contenu" class="dashboard portal admin">
    <section class="wrap dashboard-head">
        <div>
            <?php partial('admin-nav', ['active' => 'tableau']); ?>
            <h1>Tableau de bord <em>de l’agence</em></h1>
            <p class="dashboard-profile">Connecté(e) : <?= e($user['first_name'] . ' ' . $user['last_name']) ?> · <?= e($user['role']) ?></p>
        </div>
        <form method="post" action="<?= e(url('/deconnexion/')) ?>">
            <?= csrf_field() ?>
            <button class="button light" type="submit">Se déconnecter</button>
        </form>
    </section>

    <div class="wrap">
        <?php partial('flash'); ?>
        <ul class="dashboard-modules">
            <li><a class="module glass" href="<?= e(url('/admin/demandes/?statut=nouvelle')) ?>"><h2><?= (int) ($requestCounts['nouvelle'] ?? 0) ?></h2><p>Nouvelle(s) demande(s) à prendre en charge</p></a></li>
            <li><a class="module glass" href="<?= e(url('/admin/demandes/?statut=en-cours')) ?>"><h2><?= (int) ($requestCounts['en-cours'] ?? 0) + (int) ($requestCounts['attente-client'] ?? 0) ?></h2><p>Demande(s) en cours, dont <?= (int) ($requestCounts['attente-client'] ?? 0) ?> en attente du client</p></a></li>
            <li><a class="module glass" href="<?= e(url('/admin/rendez-vous/?statut=demande')) ?>"><h2><?= (int) ($appointmentCounts['demande'] ?? 0) ?></h2><p>Rendez-vous à confirmer · <?= (int) ($appointmentCounts['confirme'] ?? 0) ?> confirmé(s)</p></a></li>
            <li><a class="module glass" href="<?= e(url('/admin/messages/?statut=nouveau')) ?>"><h2><?= $messageCount ?></h2><p>Message(s) de contact à traiter</p></a></li>
            <li><div class="module glass"><h2><?= $clientCount ?></h2><p>Compte(s) client · <?= $documentCount ?> document(s) reçu(s)</p></div></li>
        </ul>

        <section class="account glass" aria-labelledby="inscrits-titre">
            <h2 id="inscrits-titre">Dernières inscriptions</h2>
            <?php if ($latest === []): ?>
                <p>Aucune inscription pour l’instant.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th scope="col">Nom</th><th scope="col">E-mail</th><th scope="col">Profil</th><th scope="col">Inscription</th></tr></thead>
                        <tbody>
                            <?php foreach ($latest as $client): ?>
                                <tr>
                                    <td><?= e($client['first_name'] . ' ' . $client['last_name']) ?></td>
                                    <td><?= e($client['email']) ?></td>
                                    <td><?= e($profiles[$client['profile']] ?? $client['profile']) ?></td>
                                    <td><?= e(date('d/m/Y H:i', strtotime($client['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
