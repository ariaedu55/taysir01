<?php
/**
 * « Ma journée » : première page de chaque poste (docs/gestion-plan.md §12.2). Données fictives.
 * La composition suit les priorités du poste (docs/gestion-phase-1.md, 1A bis) :
 * secrétaire = rendez-vous à confirmer et accueil ; traitement = pièces à vérifier et échéances ;
 * comptable = échéances financières et règlements du jour.
 *
 * @var string $poste
 * @var array  $equipe, $journee, $rdvJour
 */
[$prenom] = $equipe[$poste];
$j = $journee[$poste];
$base = '/gestion/maquette';
$jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$now = new DateTimeImmutable('now', new DateTimeZone('Africa/Dakar'));
$aujourdhui = ucfirst($jours[(int) $now->format('w')]) . ' ' . $now->format('j') . ' ' . $mois[(int) $now->format('n')];
$onglet = ['traitement' => 'pieces', 'orientation' => 'orientation', 'comptable' => 'paiements'][$poste] ?? 'apercu';
$fiche = static fn (string $ref, ?string $vers = null): string => url($base . '/dossiers/' . $ref . '/') . '?onglet=' . ($vers ?? $onglet);
$cible = static fn (?string $ref): string => $ref === 'prospects' ? url($base . '/prospects/') : ($ref ? $fiche($ref) : '#');
$mesRdv = ['secretaire' => null, 'admin' => null][$poste] ?? $poste;
// Libellé explicite de chaque niveau (la couleur n'est jamais seule)
$niveaux = ['urgent' => 'En retard', 'alerte' => 'À traiter', 'info' => 'Pour information'];
// Le plus urgent d'abord : en retard, puis à traiter, puis pour information
if (isset($j['taches'])) {
    $ordre = ['urgent' => 0, 'alerte' => 1, 'info' => 2];
    usort($j['taches'], static fn (array $a, array $b): int => $ordre[$a[0]] <=> $ordre[$b[0]]);
}
$missions = [
    'admin' => ['Pilotage', 'jour'], 'secretaire' => ['Accueil et rendez-vous', 'rdv'], 'prospection' => ['Prospection', 'pro'],
    'commerciale' => ['Suivi commercial', 'pro'], 'conseiller' => ['Conseil', 'ori'], 'orientation' => ['Orientation', 'ori'],
    'traitement' => ['Traitement des dossiers', 'ok'], 'comptable' => ['Finance', 'fin'],
];
[$mission, $missionFam] = $missions[$poste];
?>
<section class="g-hello is-<?= e($missionFam) ?>">
    <div>
        <h2>Bonjour <?= e($prenom) ?></h2>
        <p><?= e($aujourdhui) ?></p>
    </div>
    <p class="g-mission is-<?= e($missionFam) ?>"><span class="g-dot" aria-hidden="true"></span><?= e($mission) ?></p>
</section>

<div class="g-kpis">
    <?php foreach ($j['kpis'] as [$label, $valeur, $fam, $icone]): ?>
        <div class="g-kpi is-<?= e($fam) ?>">
            <span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone($icone, 20) ?></span>
            <strong><?= e($valeur) ?></strong>
            <span class="g-kpi-label"><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="g-grid g-grid-2">
    <div class="g-grid">
        <?php if ($poste === 'secretaire'): ?>
            <section class="g-card is-rdv" aria-labelledby="a-confirmer">
                <div class="g-card-head">
                    <h3 id="a-confirmer"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('inbox', 18) ?></span>Rendez-vous à confirmer <span class="g-count" data-g-confirm-count><?= count($j['confirmer']) ?></span></h3>
                    <a href="#" data-g-soon>Tout l’agenda</a>
                </div>
                <ul class="g-tasks">
                    <?php foreach ($j['confirmer'] as [$nom, $objet, $quand, $avec, $origine]): ?>
                        <li class="g-task is-rdv" data-g-item="<?= e($nom . ', ' . $quand) ?>">
                            <span class="g-avatar is-sm" aria-hidden="true"><?= e(mb_substr($nom, 0, 1)) ?></span>
                            <div>
                                <strong><?= e($nom) ?> · <?= e($objet) ?></strong>
                                <div class="g-task-meta"><span><?= gestion_icone('clock', 14) ?> <?= e($quand) ?></span><span>avec <?= e($avec) ?></span><span>origine : <?= e($origine) ?></span></div>
                            </div>
                            <div class="g-task-actions">
                                <button class="g-btn g-btn-sm" type="button" data-g-soon>Déplacer</button>
                                <button class="g-btn g-btn-sm g-btn-primary" type="button" data-g-demo="confirmer"><?= gestion_icone('check', 16) ?> Confirmer</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="g-card is-ech" aria-labelledby="file">
                <div class="g-card-head"><h3 id="file"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('queue', 18) ?></span>Accueil : file d’attente</h3><button class="g-btn g-btn-sm" type="button" data-g-soon><?= gestion_icone('plus', 16) ?> Visite</button></div>
                <ul class="g-tasks">
                    <?php foreach ($j['file'] as $i => [$nom, $motif, $attente]): ?>
                        <li class="g-task">
                            <span class="g-avatar is-sm is-num" aria-hidden="true"><?= $i + 1 ?></span>
                            <div><strong><?= e($nom) ?></strong><p><?= e($motif) ?> · attend depuis <?= e($attente) ?></p></div>
                            <div class="g-task-actions"><button class="g-btn g-btn-sm<?= $i === 0 ? ' g-btn-dark' : '' ?>" type="button" data-g-demo="appel" data-g-message="<?= e($nom) ?> est appelé(e) à l’accueil."><?= $i === 0 ? 'Appeler' : 'Recevoir' ?></button></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php elseif ($poste === 'commerciale'): ?>
            <section class="g-card is-pro" aria-labelledby="appels">
                <div class="g-card-head"><h3 id="appels"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('phone', 18) ?></span>À appeler aujourd’hui</h3><a href="<?= e(url($base . '/prospects/')) ?>">Mes prospects</a></div>
                <ul class="g-tasks">
                    <?php foreach ($j['appels'] as [$nom, $ou, $note]): ?>
                        <li class="g-task is-pro" data-g-item="<?= e($nom) ?>">
                            <span class="g-avatar is-sm" aria-hidden="true"><?= gestion_icone('phone', 14) ?></span>
                            <div><strong><?= e($nom) ?></strong><p><?= e($ou) ?> · <?= e($note) ?></p></div>
                            <div class="g-task-actions">
                                <button class="g-btn g-btn-sm" type="button" data-g-demo="appel" data-g-message="Appel noté pour <?= e($nom) ?> : choisissez le résultat (joignable, intéressé, rappeler, pas intéressé).">Appelé</button>
                                <button class="g-btn g-btn-sm g-btn-primary" type="button" data-g-demo="rdv" data-g-message="Proposition de rendez-vous envoyée à l’accueil pour confirmation.">Proposer un RDV</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php elseif ($poste === 'comptable'): ?>
            <section class="g-card is-ech" aria-labelledby="echeances-fin">
                <div class="g-card-head"><h3 id="echeances-fin"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('clock', 18) ?></span>Échéances financières</h3><a href="#" data-g-soon>Toutes les échéances</a></div>
                <ul class="g-tasks">
                    <?php foreach ($j['echeances'] as [$quand, $nom, $objet, $montant, $ton, $ref]): ?>
                        <li class="g-task is-<?= e($ton) ?>" data-g-item="<?= e($nom) ?>">
                            <div>
                                <span class="g-level is-<?= e($ton) ?>"><?= e($niveaux[$ton]) ?></span>
                                <strong><?= e($nom) ?> · <span class="g-amount"><?= e($montant) ?></span></strong>
                                <p><?= e($objet) ?> · dû <?= e($quand) ?></p>
                            </div>
                            <div class="g-task-actions">
                                <button class="g-btn g-btn-sm" type="button" data-g-demo="relance" data-g-message="Relance envoyée à <?= e($nom) ?> (SMS et e-mail), avec le montant et les moyens de paiement.">Relancer</button>
                                <button class="g-btn g-btn-sm g-btn-primary" type="button" data-g-demo="action" data-g-message="Règlement de <?= e($nom) ?> enregistré : reçu généré et envoyé."><?= gestion_icone('wallet', 15) ?> Enregistrer</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="g-card" aria-labelledby="afaire">
                <div class="g-card-head"><h3 id="afaire"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('check', 18) ?></span>À faire maintenant</h3></div>
                <?php require __DIR__ . '/_taches.php'; ?>
            </section>
        <?php elseif ($poste === 'traitement'): ?>
            <section class="g-card is-ok" aria-labelledby="a-verifier">
                <div class="g-card-head"><h3 id="a-verifier"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('file', 18) ?></span>Pièces à vérifier <span class="g-count"><?= count($j['verifier']) ?></span></h3><a href="<?= e(url($base . '/dossiers/')) ?>">Mes dossiers</a></div>
                <ul class="g-tasks">
                    <?php foreach ($j['verifier'] as [$nom, $piece, $depuis, $ref]): ?>
                        <li class="g-task is-ok">
                            <span class="g-doc-icon" aria-hidden="true"><?= gestion_icone('file', 17) ?></span>
                            <div><strong><?= e($piece) ?></strong><p><?= e($nom) ?> · déposée <?= e($depuis) ?> · <span class="g-nowrap"><?= e($ref) ?></span></p></div>
                            <div class="g-task-actions"><a class="g-btn g-btn-sm g-btn-primary" href="<?= e($fiche($ref, 'pieces')) ?>">Vérifier <?= gestion_icone('arrow', 15) ?></a></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="g-card" aria-labelledby="afaire">
                <div class="g-card-head"><h3 id="afaire"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('check', 18) ?></span>À faire maintenant</h3></div>
                <?php require __DIR__ . '/_taches.php'; ?>
            </section>
        <?php else: ?>
            <section class="g-card" aria-labelledby="afaire">
                <div class="g-card-head"><h3 id="afaire"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('check', 18) ?></span>À faire maintenant</h3></div>
                <?php require __DIR__ . '/_taches.php'; ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="g-grid">
        <?php if ($poste === 'traitement'): ?>
            <section class="g-card is-ech" aria-labelledby="echeances">
                <div class="g-card-head"><h3 id="echeances"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('clock', 18) ?></span>Échéances des 7 jours</h3></div>
                <ul class="g-deadlines">
                    <?php foreach ($j['echeances'] as [$quand, $nom, $quoi, $ton, $ref]): ?>
                        <li class="is-<?= e($ton) ?>">
                            <span class="g-level is-<?= e($ton) ?>"><?= e($quand) ?></span>
                            <a href="<?= e($fiche($ref, 'procedure')) ?>"><strong><?= e($nom) ?></strong><span><?= e($quoi) ?></span></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php if ($poste === 'comptable'): ?>
            <section class="g-card is-fin" aria-labelledby="reglements">
                <div class="g-card-head"><h3 id="reglements"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('receipt', 18) ?></span>Règlements du jour</h3><a href="#" data-g-soon>Journal</a></div>
                <ul class="g-agenda">
                    <?php foreach ($j['reglements'] as [$heure, $nom, $mode, $montant, $recu]): ?>
                        <li><time><?= e($heure) ?></time><div><strong><?= e($nom) ?> · <span class="g-amount"><?= e($montant) ?></span></strong><span><?= e($mode) ?> · reçu <?= e($recu) ?></span></div></li>
                    <?php endforeach; ?>
                </ul>
                <p class="g-total"><span>Total encaissé aujourd’hui</span><strong>850 000 FCFA</strong></p>
            </section>
        <?php else: ?>
            <section class="g-card is-rdv" aria-labelledby="rdv-jour">
                <div class="g-card-head"><h3 id="rdv-jour"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('calendar', 18) ?></span>Rendez-vous du jour</h3><a href="#" data-g-soon>Agenda</a></div>
                <ul class="g-agenda">
                    <?php foreach ($rdvJour as [$heure, $nom, $objet, $concerne]): ?>
                        <li class="<?= $mesRdv === null || $mesRdv === $concerne ? 'is-mine' : '' ?>">
                            <time><?= e($heure) ?></time>
                            <div><strong><?= e($nom) ?></strong><span><?= e($objet) ?></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="g-legend"><span class="g-legend-dot" aria-hidden="true"></span>vos rendez-vous</p>
            </section>
        <?php endif; ?>
        <section class="g-card" aria-labelledby="nouveau">
            <div class="g-card-head"><h3 id="nouveau"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('bell', 18) ?></span>Nouveautés</h3></div>
            <ul class="g-timeline">
                <li>Awa Sène a déposé une pièce<small>il y a 25 min</small></li>
                <li>Rokhaya Thiam a demandé un rendez-vous sur le site<small>il y a 1 h</small></li>
                <li>Rappel automatique envoyé à Coumba Diallo (paiement)<small>ce matin</small></li>
            </ul>
        </section>
    </div>
</div>
