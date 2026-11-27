<?php
/**
 * « Ma journée » réelle (/gestion/) : ce qui attend le compte, sur son seul périmètre (docs/gestion-phase-1.md, 1B).
 *
 * @var array       $me
 * @var array       $compteurs   Module => nombre en attente (Coquille::compteurs)
 * @var array       $counts      Dossiers du périmètre par statut
 * @var bool        $dossiers    Accès au module Dossiers
 * @var bool        $affectable  Poste qui reçoit des affectations
 * @var list<array> $mesDossiers, $aPrendre, $recents, $rdv
 */
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Services\Access;

$jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$aujourdhui = ucfirst($jours[(int) date('w')]) . ' ' . date('j') . ' ' . $mois[(int) date('n')];
$famille = ['admin' => 'jour', 'secretaire' => 'rdv', 'prospection' => 'pro', 'commerciale' => 'pro', 'conseiller' => 'ori',
    'orientation' => 'ori', 'traitement' => 'ok', 'comptable' => 'fin'][$me['role']] ?? 'neutre';
$enCours = (int) ($counts['nouvelle'] ?? 0) + (int) ($counts['en-cours'] ?? 0) + (int) ($counts['attente-client'] ?? 0);

$kpis = [];
if ($dossiers) {
    $kpis[] = [$affectable ? 'Dossiers en cours (vous et à prendre)' : 'Dossiers en cours', $enCours, 'ok', 'folder'];
    $kpis[] = ['Nouveaux dossiers', (int) ($counts['nouvelle'] ?? 0), 'rdv', 'inbox'];
    $kpis[] = ['En attente du client', (int) ($counts['attente-client'] ?? 0), 'ech', 'clock'];
}
if (isset($compteurs['rendez-vous'])) {
    $kpis[] = ['Rendez-vous à confirmer', $compteurs['rendez-vous'], 'ori', 'calendar'];
}
if (isset($compteurs['messages'])) {
    $kpis[] = ['Messages à traiter', $compteurs['messages'], 'pro', 'chat'];
}
$ligne = static function (array $r): string {
    $service = ServiceRequest::service($r['service'])['title'] ?? $r['service'];

    return '<li class="g-task"><span class="g-doc-icon" aria-hidden="true">' . gestion_icone('folder', 17) . '</span>'
        . '<div><strong>' . e($r['first_name'] . ' ' . $r['last_name']) . ' · ' . e($service) . '</strong>'
        . '<p>' . e($r['reference']) . ($r['destination'] ? ' · ' . e($r['destination']) : '') . ' · ' . g_badge(ServiceRequest::statusLabel($r['status'], true), ServiceRequest::statusTone($r['status'])) . '</p></div>'
        . '<div class="g-task-actions"><a class="g-btn g-btn-sm" href="' . e(url('/gestion/dossiers/' . $r['reference'] . '/')) . '">Ouvrir ' . gestion_icone('arrow', 15) . '</a></div></li>';
};
?>
<section class="g-hello is-<?= e($famille) ?>">
    <div>
        <h2>Bonjour <?= e($me['first_name']) ?></h2>
        <p><?= e($aujourdhui) ?></p>
    </div>
    <p class="g-mission is-<?= e($famille) ?>"><span class="g-dot" aria-hidden="true"></span><?= e(Access::libelle($me['role'])) ?></p>
</section>

<?php if ($kpis !== []): ?>
    <div class="g-kpis">
        <?php foreach ($kpis as [$label, $valeur, $fam, $icone]): ?>
            <div class="g-kpi is-<?= e($fam) ?>">
                <span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone($icone, 20) ?></span>
                <strong><?= (int) $valeur ?></strong>
                <span class="g-kpi-label"><?= e($label) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="g-grid g-grid-2">
    <div class="g-grid">
        <?php if ($affectable): ?>
            <section class="g-card is-ok" aria-labelledby="mes-dossiers">
                <div class="g-card-head"><h3 id="mes-dossiers"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span>Mes dossiers</h3><a href="<?= e(url('/gestion/dossiers/?vue=mes')) ?>">Tous mes dossiers</a></div>
                <?php if ($mesDossiers === []): ?>
                    <p class="g-empty">Aucun dossier ne vous est affecté pour l’instant. Prenez-en un dans la liste « À prendre ».</p>
                <?php else: ?>
                    <ul class="g-tasks"><?php foreach ($mesDossiers as $r) { echo $ligne($r); } ?></ul>
                <?php endif; ?>
            </section>
            <section class="g-card is-rdv" aria-labelledby="a-prendre">
                <div class="g-card-head"><h3 id="a-prendre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('inbox', 18) ?></span>À prendre</h3><a href="<?= e(url('/gestion/dossiers/?vue=a-prendre')) ?>">Voir tout</a></div>
                <?php if ($aPrendre === []): ?>
                    <p class="g-empty">Aucun dossier en attente d’un responsable à votre poste.</p>
                <?php else: ?>
                    <ul class="g-tasks"><?php foreach ($aPrendre as $r) { echo $ligne($r); } ?></ul>
                <?php endif; ?>
            </section>
        <?php elseif ($dossiers): ?>
            <section class="g-card is-rdv" aria-labelledby="nouveaux">
                <div class="g-card-head"><h3 id="nouveaux"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('inbox', 18) ?></span>Nouveaux dossiers</h3><a href="<?= e(url('/gestion/dossiers/?statut=nouvelle')) ?>">Voir tout</a></div>
                <?php if ($recents === []): ?>
                    <p class="g-empty">Aucun nouveau dossier.</p>
                <?php else: ?>
                    <ul class="g-tasks"><?php foreach ($recents as $r) { echo $ligne($r); } ?></ul>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="g-card is-pro" aria-labelledby="bientot">
                <div class="g-card-head"><h3 id="bientot"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('target', 18) ?></span>Votre espace arrive</h3></div>
                <p class="g-muted" style="margin:0">Les écrans de votre poste (prospects, établissements, campagnes, appels) sont prévus en phase 6 du plan. Votre compte est prêt : vous les retrouverez ici dès leur mise en service.</p>
            </section>
        <?php endif; ?>
    </div>

    <div class="g-grid">
        <?php if ($rdv !== []): ?>
            <section class="g-card is-ori" aria-labelledby="rdv">
                <div class="g-card-head"><h3 id="rdv"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('calendar', 18) ?></span>Rendez-vous à confirmer</h3><a href="<?= e(url('/gestion/rendez-vous/?statut=demande')) ?>">Agenda</a></div>
                <ul class="g-agenda">
                    <?php foreach ($rdv as $a): ?>
                        <li class="is-mine"><time><?= e(date('d/m', strtotime($a['preferred_date']))) ?></time>
                            <div><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><span><?= e(Appointment::MOTIVES[$a['motive']] ?? '') ?> · <?= $a['preferred_slot'] === 'matin' ? 'matin' : 'après-midi' ?></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <section class="g-card" aria-labelledby="perimetre">
            <div class="g-card-head"><h3 id="perimetre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('lock', 18) ?></span>Votre périmètre</h3></div>
            <p class="g-muted" style="margin:0;font-size:.84rem">
                <?php if (in_array($me['role'], ['admin'], true)): ?>
                    Vous voyez tous les dossiers et toutes les pièces. Chaque téléchargement est inscrit au journal d’audit.
                <?php elseif (in_array($me['role'], ['secretaire', 'comptable'], true)): ?>
                    Vous voyez tous les dossiers (en-tête et suivi), sans accès aux pièces des clients.
                <?php elseif ($affectable): ?>
                    Vous voyez les dossiers qui vous sont affectés et ceux qui attendent un responsable à votre poste. Les pièces s’ouvrent une fois le dossier pris.
                <?php else: ?>
                    Votre poste n’a pas accès aux dossiers clients.
                <?php endif; ?>
            </p>
        </section>
    </div>
</div>
