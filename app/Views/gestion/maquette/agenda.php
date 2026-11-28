<?php
/**
 * Maquette 2.0 : agenda (docs/gestion-phase-2.md, décisions D2 et D4, exigences C2 et C6).
 * - Secrétaire et administratrice : vue « jour » de toute l'agence (une colonne par membre, jamais de chevauchement
 *   visuel), puis vue « semaine » d'un membre ; demandes à placer.
 * - Conseiller, orientation : leur semaine.
 * - Autres postes : refus (l'agenda de la commerciale arrive en phase 6).
 * Téléphone : liste par jour. Données fictives.
 *
 * @var string $poste
 * @var array  $motifs, $membres, $horaires, $rendezVous, $exceptions, $demandes, $creneauxLibres
 * @var DateTimeImmutable $lundi
 */
$base = '/gestion/maquette';
$transversal = in_array($poste, ['admin', 'secretaire'], true);
$moi = ['conseiller' => 'conseiller', 'orientation' => 'orientation'][$poste] ?? null;
if (!$transversal && $moi === null) {
    http_response_code(403);
    ?>
    <section class="g-card g-denied" role="alert">
        <span class="g-denied-icon" aria-hidden="true"><?= gestion_icone('lock', 22) ?></span>
        <div>
            <h2>L’agenda n’est pas ouvert à votre poste</h2>
            <p><?= $poste === 'commerciale' ? 'Votre agenda (rendez-vous proposés aux prospects) arrive avec la prospection, en phase 6.' : 'Les rendez-vous sont tenus par l’accueil, les conseillers et l’orientation.' ?></p>
            <a class="g-btn g-btn-sm" href="<?= e(url($base . '/')) ?>">Revenir à Ma journée</a>
        </div>
    </section>
    <?php
    return;
}

$jours = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
$joursLongs = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$debutJour = 9 * 60;
$finJour = 18 * 60;
$pas = 15;
$minutes = static fn (string $h): int => (int) substr($h, 0, 2) * 60 + (int) substr($h, 3, 2);
$heure = static fn (int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
$aujourdhui = min(5, (int) (new DateTimeImmutable('now', new DateTimeZone('Africa/Dakar')))->format('N') - 1);
$statuts = ['demande' => ['À confirmer', 'ech'], 'confirme' => ['Confirmé', 'ok'], 'honore' => ['Honoré', 'neutre'], 'absent' => ['Absent', 'urgent'], 'annule' => ['Annulé', 'neutre']];
$modes = ['agence-dakar' => ['Agence de Dakar', 'building'], 'telephone' => ['Téléphone', 'phone'], 'visio' => ['Visio', 'eye'], 'whatsapp' => ['WhatsApp', 'chat']];
// Heure locale de la personne (C6 : stockage UTC, conversion en PHP ; Dakar = UTC+0)
$ailleurs = static function (DateTimeImmutable $date, string $h, ?string $fuseau): ?string {
    if ($fuseau === null) {
        return null;
    }
    $dakar = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $h, new DateTimeZone('Africa/Dakar'));
    $villes = ['Europe/Paris' => 'Paris', 'America/Toronto' => 'Toronto'];

    return $dakar->setTimezone(new DateTimeZone($fuseau))->format('H:i') . ' à ' . ($villes[$fuseau] ?? $fuseau);
};
$visibles = array_values(array_filter($rendezVous, static fn (array $r): bool => $transversal || $r[3] === $moi));

/** Une colonne de grille : un jour, pour un membre (ou pour le membre seul de la vue personnelle). */
$colonne = static function (int $i, string $membre, bool $today) use ($lundi, $horaires, $exceptions, $visibles, $motifs, $modes, $statuts, $membres, $minutes, $heure, $debutJour, $finJour, $pas, $ailleurs, $jours): string {
    $date = $lundi->modify('+' . $i . ' days');
    $html = '<div class="g-week-day' . ($today ? ' is-today' : '') . '">';
    for ($m = $debutJour + 60; $m < $finJour; $m += 60) {
        $html .= '<i class="g-hline" style="--d:' . (($m - $debutJour) / $pas) . '"></i>';
    }
    // Heures fermées de l'agence : surface neutre
    $curseur = $debutJour;
    foreach (array_merge($horaires[$i] ?? [], [[$heure($finJour), $heure($finJour)]]) as [$o, $f]) {
        if ($minutes($o) > $curseur) {
            $html .= sprintf('<span class="g-closed" style="--d:%d;--h:%d"></span>', ($curseur - $debutJour) / $pas, ($minutes($o) - $curseur) / $pas);
        }
        $curseur = max($curseur, $minutes($f));
    }
    foreach ($exceptions as [$j, $qui, $d, $f, $motif]) {
        if ($j === $i && $qui === $membre) {
            $html .= sprintf('<span class="g-off" style="--d:%d;--h:%d">%s</span>', ($minutes($d) - $debutJour) / $pas, ($minutes($f) - $minutes($d)) / $pas, e($motif));
        }
    }
    foreach ($visibles as [$id, $j, $h, $qui, $nom, $motif, $mode, $statut, $fuseau]) {
        if ($j !== $i || $qui !== $membre) {
            continue;
        }
        [$libMotif, $duree, $fam] = $motifs[$motif];
        $loin = $ailleurs($date, $h, $fuseau);
        $court = $duree <= 30 ? ' is-short' : '';
        $html .= '<button type="button" class="g-event is-' . e($statut === 'demande' ? 'ech' : $fam) . ($statut === 'honore' ? ' is-past' : '') . $court . '"'
            . ' style="--d:' . (($minutes($h) - $debutJour) / $pas) . ';--h:' . ($duree / $pas) . '"'
            . ' data-g-event data-id="' . e($id) . '" data-nom="' . e($nom) . '" data-motif="' . e($libMotif) . '"'
            . ' data-heure="' . e($jours[$i] . ' ' . $h . '–' . $heure($minutes($h) + $duree)) . '" data-mode="' . e($modes[$mode][0]) . '"'
            . ' data-membre-nom="' . e($membres[$qui][0]) . '" data-statut="' . e($statuts[$statut][0]) . '" data-loin="' . e((string) $loin) . '">'
            . '<span class="g-event-time">' . e($h . '–' . $heure($minutes($h) + $duree)) . '</span>'
            . '<strong>' . e($nom) . '</strong>'
            . '<span class="g-event-meta">' . gestion_icone($modes[$mode][1], 12) . ' ' . e($libMotif) . '</span>'
            . ($loin ? '<span class="g-event-tz">' . gestion_icone('clock', 11) . ' ' . e($loin) . '</span>' : '')
            . ($statut === 'demande' ? '<span class="g-event-flag">À confirmer</span>' : '')
            . '</button>';
    }

    return $html . '</div>';
};
$heures = static function () use ($debutJour, $finJour, $heure): string {
    $html = '<div class="g-week-hours">';
    for ($m = $debutJour; $m < $finJour; $m += 60) {
        $html .= '<span>' . e($heure($m)) . '</span>';
    }

    return $html . '</div>';
};
$slots = intdiv($finJour - $debutJour, $pas);
?>
<div class="g-toolbar g-agenda-bar">
    <div class="g-week-nav">
        <button class="g-icon-btn" type="button" data-g-soon aria-label="Semaine précédente"><?= gestion_icone('collapse', 18) ?></button>
        <strong>Semaine du <?= e($lundi->format('d/m')) ?> au <?= e($lundi->modify('+5 days')->format('d/m')) ?></strong>
        <button class="g-icon-btn g-flip" type="button" data-g-soon aria-label="Semaine suivante"><?= gestion_icone('collapse', 18) ?></button>
    </div>
    <?php if ($transversal): ?>
        <div class="g-views" role="group" aria-label="Vue de l’agenda" data-g-agenda-vue>
            <button type="button" aria-pressed="true" data-vue="agence"><?= gestion_icone('users', 14) ?> Toute l’agence</button>
            <?php foreach ($membres as $cle => [$nom]): ?>
                <button type="button" aria-pressed="false" data-vue="<?= e($cle) ?>"><?= e(explode(' ', $nom)[0]) ?></button>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="g-muted g-agenda-who"><?= gestion_icone('calendar', 16) ?> Votre semaine · <?= e($membres[$moi][0]) ?></p>
    <?php endif; ?>
    <button class="g-btn" type="button" data-g-soon><?= gestion_icone('clock', 16) ?> Mes disponibilités</button>
</div>

<div class="g-agenda-layout<?= $transversal ? '' : ' is-solo' ?>">
    <?php if ($transversal): ?>
        <section class="g-card is-ech g-to-place" aria-labelledby="a-placer">
            <div class="g-card-head"><h3 id="a-placer"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('inbox', 18) ?></span>Demandes à placer <span class="g-count"><?= count($demandes) ?></span></h3></div>
            <ul class="g-tasks">
                <?php foreach ($demandes as [$id, $nom, $motif, $souhait, $origine]): ?>
                    <li class="g-task" data-g-item="<?= e($nom) ?>">
                        <div>
                            <strong><?= e($nom) ?></strong>
                            <p><?= e($motifs[$motif][0]) ?> · <?= (int) $motifs[$motif][1] ?> min · souhaité <?= e($souhait) ?></p>
                            <p class="g-muted">origine : <?= e($origine) ?></p>
                        </div>
                        <div class="g-task-actions"><button class="g-btn g-btn-sm g-btn-primary" type="button" data-g-placer="<?= e($id) ?>" data-nom="<?= e($nom) ?>" data-motif="<?= e($motifs[$motif][0]) ?>">Placer</button></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="g-legend-box">
                <span><i class="is-ori"></i>Orientation</span><span><i class="is-ok"></i>Admission</span><span><i class="is-rdv"></i>Renseignement</span><span><i class="is-pro"></i>Visa</span><span><i class="is-fin"></i>Logement</span><span><i class="is-ech"></i>À confirmer</span>
            </p>
        </section>
    <?php endif; ?>

    <div class="g-agenda-main">
        <?php if ($transversal): ?>
            <!-- Vue « toute l'agence » : un jour, une colonne par membre -->
            <div data-g-vue="agence">
                <div class="g-views g-day-picker" role="group" aria-label="Jour affiché">
                    <?php foreach ($jours as $i => $nomJour): ?>
                        <button type="button" aria-pressed="<?= $i === $aujourdhui ? 'true' : 'false' ?>" data-jour="<?= $i ?>"><?= e($nomJour) ?> <?= e($lundi->modify('+' . $i . ' days')->format('d')) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($jours as $i => $nomJour): ?>
                    <section class="g-week g-resource" aria-label="Agenda de l’agence, <?= e($joursLongs[$i]) ?>" data-g-jour="<?= $i ?>"<?= $i === $aujourdhui ? '' : ' hidden' ?> style="--g-cols: <?= count($membres) ?>">
                        <div class="g-week-head"><span></span>
                            <?php foreach ($membres as [$nom, $role]): ?><span class="g-res-head"><b><?= e($nom) ?></b><small><?= e($role) ?></small></span><?php endforeach; ?>
                        </div>
                        <div class="g-week-body" style="--g-slots: <?= $slots ?>">
                            <?= $heures() ?>
                            <?php foreach (array_keys($membres) as $cle) { echo $colonne($i, $cle, false); } ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Vue « semaine » d'un membre -->
        <?php foreach ($transversal ? array_keys($membres) : [$moi] as $cle): ?>
            <section class="g-week" aria-label="Semaine de <?= e($membres[$cle][0]) ?>" data-g-vue="<?= e($cle) ?>"<?= $transversal ? ' hidden' : '' ?> style="--g-cols: 6">
                <div class="g-week-head"><span></span>
                    <?php foreach ($jours as $i => $nomJour): ?>
                        <span class="<?= $i === $aujourdhui ? 'is-today' : '' ?>"><?= e($nomJour) ?> <b><?= e($lundi->modify('+' . $i . ' days')->format('d')) ?></b></span>
                    <?php endforeach; ?>
                </div>
                <div class="g-week-body" style="--g-slots: <?= $slots ?>">
                    <?= $heures() ?>
                    <?php foreach ($jours as $i => $nomJour) { echo $colonne($i, $cle, $i === $aujourdhui); } ?>
                </div>
            </section>
        <?php endforeach; ?>

        <!-- Téléphone : liste par jour -->
        <section class="g-week-list" aria-label="Agenda de la semaine, par jour">
            <?php foreach ($jours as $i => $nomJour): ?>
                <?php $duJour = array_filter($visibles, static fn (array $r): bool => $r[1] === $i); usort($duJour, static fn ($a, $b) => strcmp($a[2], $b[2])); ?>
                <div class="g-day-block<?= $i === $aujourdhui ? ' is-today' : '' ?>">
                    <p class="g-day-title"><?= e(ucfirst($joursLongs[$i])) ?> <?= e($lundi->modify('+' . $i . ' days')->format('d/m')) ?><?= $i === $aujourdhui ? ' · aujourd’hui' : '' ?></p>
                    <?php foreach ($exceptions as [$j, $qui, $d, $f, $motifException]): ?>
                        <?php if ($j === $i && ($transversal || $qui === $moi)): ?>
                            <p class="g-off-row" data-membre="<?= e($qui) ?>"><?= gestion_icone('lock', 14) ?> <?= e($d . '–' . $f) ?> · <?= e($motifException) ?><?= $transversal ? ' · ' . e($membres[$qui][0]) : '' ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($duJour === []): ?><p class="g-muted g-day-empty">Aucun rendez-vous</p><?php endif; ?>
                    <?php foreach ($duJour as [$id, $j, $h, $membre, $nom, $motif, $mode, $statut, $fuseau]): ?>
                        <?php [$libMotif, $duree, $fam] = $motifs[$motif]; $loin = $ailleurs($lundi->modify('+' . $i . ' days'), $h, $fuseau); ?>
                        <button type="button" class="g-event-row is-<?= e($statut === 'demande' ? 'ech' : $fam) ?>" data-membre="<?= e($membre) ?>"
                            data-g-event data-id="<?= e($id) ?>" data-nom="<?= e($nom) ?>" data-motif="<?= e($libMotif) ?>" data-heure="<?= e($nomJour . ' ' . $h . '–' . $heure($minutes($h) + $duree)) ?>"
                            data-mode="<?= e($modes[$mode][0]) ?>" data-membre-nom="<?= e($membres[$membre][0]) ?>" data-statut="<?= e($statuts[$statut][0]) ?>" data-loin="<?= e((string) $loin) ?>">
                            <time><?= e($h) ?></time>
                            <span><strong><?= e($nom) ?></strong><small><?= e($libMotif) ?> · <?= e($modes[$mode][0]) ?><?= $loin ? ' · ' . e($loin) : '' ?><?= $transversal ? ' · ' . e($membres[$membre][0]) : '' ?></small></span>
                            <?php if ($statut === 'demande'): ?><span class="g-level is-ech">À confirmer</span><?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
</div>

<!-- Panneau : détail d'un rendez-vous, ou placement d'une demande -->
<div class="g-panel-scrim" data-g-panel-scrim></div>
<aside class="g-panel" data-g-panel aria-label="Détail" role="dialog">
    <div class="g-panel-head">
        <div><p class="g-muted" data-p="sur" style="margin:0;font-size:.76rem"></p><h2 data-p="titre"></h2></div>
        <button class="g-icon-btn" type="button" data-g-panel-close aria-label="Fermer"><?= gestion_icone('close', 18) ?></button>
    </div>
    <div class="g-panel-body" data-p="corps"></div>
</aside>

<template id="tpl-rdv">
    <dl class="g-dl">
        <dt>Quand</dt><dd data-v="heure"></dd>
        <dt>Heure de la personne</dt><dd data-v="loin"></dd>
        <dt>Motif</dt><dd data-v="motif"></dd>
        <dt>Mode</dt><dd data-v="mode"></dd>
        <dt>Avec</dt><dd data-v="membre"></dd>
        <dt>Statut</dt><dd data-v="statut"></dd>
    </dl>
    <div class="g-panel-actions">
        <button class="g-btn g-btn-success" type="button" data-g-demo="action" data-g-message="Rendez-vous marqué honoré. Rédigez le compte rendu depuis la fiche de la personne.">Honoré</button>
        <button class="g-btn" type="button" data-g-demo="action" data-g-message="Absence notée. La personne reçoit une proposition de nouveau créneau.">Absent</button>
        <button class="g-btn" type="button" data-g-demo="action" data-g-message="Le créneau est libéré ; le nouveau rendez-vous garde le lien avec celui-ci.">Reporter</button>
        <a class="g-btn" href="<?= e(url($base . '/personnes/')) ?>#compte-rendu"><?= gestion_icone('chat', 15) ?> Compte rendu</a>
    </div>
</template>

<template id="tpl-placer">
    <p class="g-muted" style="margin:0 0 .7rem;font-size:.82rem">Créneaux libres selon les disponibilités, la durée du motif et les exceptions.</p>
    <p class="g-flash is-ech" data-v="pris" hidden role="alert"><?= gestion_icone('alert', 16) ?> Ce créneau vient d’être pris par une autre réservation. Voici les suivants.</p>
    <ul class="g-slot-list">
        <?php $joursCourts = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam']; ?>
        <?php foreach ($creneauxLibres as [$dateLibre, $h, $membre, $pris]): ?>
            <li><button type="button" class="g-slot" data-pris="<?= $pris ? '1' : '0' ?>">
                <strong><?= e($joursCourts[(int) $dateLibre->format('N')]) ?> <?= e($dateLibre->format('d/m')) ?> · <?= e($h) ?></strong>
                <span><?= e($membres[$membre][0]) ?></span>
            </button></li>
        <?php endforeach; ?>
    </ul>
    <p class="g-muted" style="margin:.8rem 0 0;font-size:.76rem">La confirmation envoie l’e-mail à la personne (dès que l’envoi est configuré).</p>
</template>

<script>
// Maquette : vues (agence par jour, semaine d'un membre), détail, placement (dont « créneau déjà pris »).
(() => {
    const ouvrir = (titre, sur, remplir, depuis) => window.gestionPanel.open((panel) => {
        panel.querySelector('[data-p="titre"]').textContent = titre;
        panel.querySelector('[data-p="sur"]').textContent = sur;
        const corps = panel.querySelector('[data-p="corps"]');
        corps.replaceChildren();
        remplir(corps);
    }, depuis);
    document.querySelectorAll('[data-g-event]').forEach((b) => b.addEventListener('click', () => ouvrir(b.dataset.nom, b.dataset.id, (corps) => {
        corps.append(document.getElementById('tpl-rdv').content.cloneNode(true));
        const v = (k, t) => { corps.querySelector(`[data-v="${k}"]`).textContent = t; };
        v('heure', b.dataset.heure + ' (heure de Dakar)');
        v('loin', b.dataset.loin || 'même fuseau que l’agence');
        v('motif', b.dataset.motif); v('mode', b.dataset.mode); v('membre', b.dataset.membreNom); v('statut', b.dataset.statut);
    }, b)));
    document.querySelectorAll('[data-g-placer]').forEach((b) => b.addEventListener('click', () => ouvrir('Placer : ' + b.dataset.nom, b.dataset.motif, (corps) => {
        corps.append(document.getElementById('tpl-placer').content.cloneNode(true));
        corps.querySelectorAll('.g-slot').forEach((slot) => slot.addEventListener('click', () => {
            if (slot.dataset.pris === '1') {
                corps.querySelector('[data-v="pris"]').hidden = false;
                slot.closest('li').remove();
                return;
            }
            window.gestionPanel.close();
            b.closest('li').classList.add('is-done');
            setTimeout(() => b.closest('li').remove(), 350);
            window.gestionToast?.(`${b.dataset.nom} : rendez-vous confirmé ${slot.querySelector('strong').textContent}. E-mail de confirmation en file.`);
        }));
    }, b)));
    const vues = [...document.querySelectorAll('[data-g-agenda-vue] button')];
    vues.forEach((v) => v.addEventListener('click', () => {
        vues.forEach((o) => o.setAttribute('aria-pressed', String(o === v)));
        document.querySelectorAll('[data-g-vue]').forEach((el) => { el.hidden = el.dataset.gVue !== v.dataset.vue; });
        document.querySelectorAll('.g-event-row, .g-off-row').forEach((r) => { r.hidden = v.dataset.vue !== 'agence' && r.dataset.membre !== v.dataset.vue; });
    }));
    const joursBtn = [...document.querySelectorAll('.g-day-picker button')];
    joursBtn.forEach((j) => j.addEventListener('click', () => {
        joursBtn.forEach((o) => o.setAttribute('aria-pressed', String(o === j)));
        document.querySelectorAll('[data-g-jour]').forEach((s) => { s.hidden = s.dataset.gJour !== j.dataset.jour; });
    }));
})();
</script>
