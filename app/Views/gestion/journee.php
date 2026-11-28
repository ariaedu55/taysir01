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
$aujourdhui = ucfirst($jours[(int) $today->format('w')]) . ' ' . $today->format('j') . ' ' . $mois[(int) $today->format('n')];
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
    $kpis[] = [$me['role'] === 'commerciale' ? 'Propositions en attente' : 'Rendez-vous à confirmer', $compteurs['rendez-vous'], 'ori', 'calendar'];
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
<section class="g-hello g-day-brief is-<?= e($famille) ?>" aria-labelledby="journee-priorite">
    <span class="g-day-icon"><?= gestion_icone($priorite[2] ?? 'sun', 24) ?></span>
    <div class="g-day-copy">
        <p class="g-day-date"><?= e($aujourdhui) ?> · <?= e(Access::libelle($me['role'])) ?></p>
        <h2 id="journee-priorite"><?= e($priorite[0] ?? 'Bonjour ' . $me['first_name']) ?></h2>
    </div>
    <?php if ($priorite): ?><a class="g-btn g-btn-primary g-day-action" href="<?= e(url($priorite[3])) ?>"><?= e($priorite[1]) ?> <?= gestion_icone('arrow', 16) ?></a><?php endif; ?>
</section>

<?php if ($prospects !== null): ?>
    <div class="g-kpis">
        <?php $commercial = $me['role'] === 'commerciale'; ?>
        <a class="g-kpi g-prospect-kpi is-pro" href="<?= e(url('/gestion/prospects/')) ?>">
            <span class="g-kpi-icon"><?= gestion_icone('users') ?></span><strong><?= (int) $prospects['total'] ?></strong><span class="g-kpi-label"><?= $commercial ? 'Mes prospects' : 'Prospects collectés' ?></span>
        </a>
        <a class="g-kpi g-prospect-kpi is-ech" href="<?= e(g_filtre('/gestion/prospects/', ['vue' => $commercial ? 'relances' : 'libres'])) ?>">
            <span class="g-kpi-icon"><?= gestion_icone($commercial ? 'clock' : 'queue') ?></span><strong><?= (int) $prospects[$commercial ? 'relances' : 'libres'] ?></strong><span class="g-kpi-label"><?= $commercial ? 'Relances à faire' : 'Non attribués' ?></span>
        </a>
        <?php if ($commercial): ?>
            <a class="g-kpi g-prospect-kpi is-ok" href="<?= e(g_filtre('/gestion/prospects/', ['vue' => 'transmis'])) ?>">
                <span class="g-kpi-icon"><?= gestion_icone('check') ?></span><strong><?= $prospects['total'] ? (int) round(100 * $prospects['transmis'] / $prospects['total']) . '%' : '—' ?></strong><span class="g-kpi-label">Taux de transmission</span>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

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

<div class="g-grid<?= $dossiers || $agenda ? ' g-grid-2' : '' ?>">
    <?php if ($dossiers): ?>
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
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="g-grid">
        <?php partial('gestion-task-list', ['tasks' => $tasks, 'today' => $today->format('Y-m-d')]); ?>
        <?php if ($deadlines !== []): ?>
            <section class="g-card" aria-labelledby="echeances-journee">
                <div class="g-card-head"><h3 id="echeances-journee">Échéances des dossiers</h3></div>
                <ul class="g-agenda">
                    <?php foreach ($deadlines as $deadline): ?>
                        <li><time datetime="<?= e($deadline['due_date']) ?>"><?= e(g_date($deadline['due_date'])) ?></time>
                            <div><a href="<?= e(url('/gestion/dossiers/' . $deadline['reference'] . '/#suivi-dossier')) ?>"><?= e($deadline['title']) ?></a></div></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php partial('gestion-task-form', ['me' => $me]); ?>
        <?php if ($agenda): ?>
            <section class="g-card is-ori" aria-labelledby="rdv">
                <div class="g-card-head"><h3 id="rdv"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('calendar', 18) ?></span>Rendez-vous du jour</h3><a href="<?= e(url('/gestion/rendez-vous/?jour=' . $today->format('Y-m-d'))) ?>">Agenda</a></div>
                <?php if (!$rdv): ?><p class="g-empty">Aucun rendez-vous prévu aujourd’hui.</p><?php endif; ?>
                <ul class="g-agenda">
                    <?php foreach ($rdv as $a): ?>
                        <?php $agency = isset($a['agency_id']) ? (int) $a['agency_id'] : null; ?>
                        <li class="is-mine"><time><?= e(Appointment::local($a['starts_at_utc'], Appointment::zone($agency), 'H:i')) ?></time>
                            <div><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><span><?= e(Appointment::MOTIVES[$a['motive']] ?? '') ?> · <?= e(Appointment::city($agency)) ?></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</div>
