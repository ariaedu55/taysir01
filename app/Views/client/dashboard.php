<?php
/**
 * Tableau de bord de l'espace client.
 *
 * @var array  $user
 * @var string $profileLabel
 * @var int    $requestCount, $openCount, $waitingCount, $appointmentCount
 */
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);
$modules = [
    ['Prendre rendez-vous', 'Rencontrez un conseiller en agence, par téléphone ou en visio.', url('/rendez_vous_orientation/'), $appointmentCount > 0 ? $plural($appointmentCount, 'à venir', 'à venir') : null, '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>'],
    ['Faire une demande', 'Admission, visa, logement, prise en charge, billet d’avion, hôtel.', url('/mon-espace/demandes/#nouvelle-titre'), null, '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M12 18v-6M9 15h6"/>'],
    ['Suivre mes demandes', $requestCount === 0 ? 'L’avancement de chaque démarche, étape par étape.' : $plural($openCount, 'demande en cours', 'demandes en cours') . ' sur ' . $requestCount . '.', url('/mon-espace/demandes/'), $waitingCount > 0 ? $plural($waitingCount, 'action attendue', 'actions attendues') : null, '<path d="M3 12h4l3 7 4-14 3 7h4"/>'],
    ['Mes documents', 'Joignez relevés, diplômes ou passeport à vos demandes, en toute sécurité.', url('/mon-espace/demandes/'), null, '<path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/>'],
];
?>
<main id="contenu" class="dashboard">
    <section class="wrap dashboard-head">
        <div class="dashboard-identity" data-reveal="up">
            <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
            <div>
                <p class="kicker">Mon espace</p>
                <h1>Bonjour <em><?= e($user['first_name']) ?></em></h1>
                <p class="dashboard-profile"><?= e($profileLabel) ?></p>
            </div>
        </div>
        <form method="post" action="<?= e(url('/deconnexion/')) ?>" data-reveal="up">
            <?= csrf_field() ?>
            <button class="button light" type="submit">Se déconnecter</button>
        </form>
    </section>

    <div class="wrap">
        <?php partial('flash'); ?>

        <ul class="dashboard-modules" data-stagger>
            <?php foreach ($modules as [$name, $text, $href, $badge, $icon]): ?>
                <li data-reveal="up">
                    <?php if ($href): ?>
                        <a class="module glass" data-glow href="<?= e($href) ?>">
                    <?php else: ?>
                        <div class="module glass is-soon">
                    <?php endif; ?>
                            <span class="module-icon"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg></span>
                            <h2><?= e($name) ?></h2>
                            <p><?= e($text) ?></p>
                            <?php if ($badge): ?><span class="module-badge"><?= e($badge) ?></span><?php endif; ?>
                    <?php if ($href): ?>
                        </a>
                    <?php else: ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <section class="account glass" aria-labelledby="compte-titre" data-reveal="up">
            <h2 id="compte-titre">Mon compte</h2>
            <dl>
                <div><dt>Nom</dt><dd><?= e($user['first_name'] . ' ' . $user['last_name']) ?></dd></div>
                <div><dt>E-mail</dt><dd><?= e($user['email']) ?></dd></div>
                <div><dt>Téléphone</dt><dd><?= e($user['phone'] ?? 'Non renseigné') ?></dd></div>
                <div><dt>Membre depuis</dt><dd><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></dd></div>
            </dl>
        </section>
    </div>
</main>
