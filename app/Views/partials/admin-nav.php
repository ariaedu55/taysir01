<?php
/**
 * Navigation du back-office.
 *
 * @var string $active  tableau, demandes, rendez-vous, messages, e-mails ou equipe
 */
use App\Core\Auth;

$links = ['tableau' => ['/admin/', 'Tableau de bord'], 'demandes' => ['/admin/demandes/', 'Demandes'], 'rendez-vous' => ['/admin/rendez-vous/', 'Rendez-vous'], 'messages' => ['/admin/messages/', 'Messages'], 'e-mails' => ['/admin/e-mails/', 'E-mails']];
if (Auth::hasRole('admin')) {
    $links['equipe'] = ['/admin/equipe/', 'Équipe'];
}
?>
<nav class="admin-nav" aria-label="Back-office">
    <?php foreach ($links as $key => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
