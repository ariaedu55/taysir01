<?php
/**
 * Pilotage (/gestion/pilotage/), administrateur (phase 7, lot 7B) : peu d'indicateurs, tous vérifiables par un export.
 *
 * @var string                $mois       Début du mois courant (UTC)
 * @var list<array>           $mensuel    12 mois : mois, dossiers, encaisse
 * @var array<string, int>    $parService Dossiers ouverts ce mois, par service
 * @var array<string, string> $services
 * @var array<string, int>    $kpis       ouverts, enCours, termines, encaisse, reste
 * @var array<string, int>    $rdv        Rendez-vous du mois par statut
 * @var array<string, int>    $prospects  nouveaux, transmis
 */
$nombre = static fn (int|string $n): string => number_format((int) $n, 0, ',', ' ');
$moisFr = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$libelleMois = static fn (string $d): string => $moisFr[(int) substr($d, 5, 2) - 1] . ' ' . substr($d, 2, 2);
$maxDossiers = max(1, ...array_column($mensuel, 'dossiers'));
$maxEncaisse = max(1, ...array_column($mensuel, 'encaisse'));
$taux = $prospects['nouveaux'] > 0 ? (int) round(100 * $prospects['transmis'] / $prospects['nouveaux']) : 0;
$rdvLibelles = ['honore' => ['Honorés', 'green'], 'absent' => ['Absents', 'orange'], 'annule' => ['Annulés', 'grey'], 'reporte' => ['Reportés', 'blue']];
?>
<div class="g-kpis">
    <div class="g-kpi is-rdv"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('folder', 20) ?></span><strong><?= e($nombre($kpis['ouverts'])) ?></strong><span class="g-kpi-label">Dossiers ouverts ce mois</span></div>
    <div class="g-kpi is-neutre"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('clock', 20) ?></span><strong><?= e($nombre($kpis['enCours'])) ?></strong><span class="g-kpi-label">Dossiers en cours</span></div>
    <div class="g-kpi is-ok"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('check', 20) ?></span><strong><?= e($nombre($kpis['termines'])) ?></strong><span class="g-kpi-label">Terminés ce mois</span></div>
    <div class="g-kpi is-fin"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('chart', 20) ?></span><strong><?= e($nombre($kpis['encaisse'])) ?></strong><span class="g-kpi-label">Encaissé ce mois (FCFA)</span></div>
    <div class="g-kpi is-ech"><span class="g-kpi-icon" aria-hidden="true"><?= gestion_icone('alert', 20) ?></span><strong><?= e($nombre($kpis['reste'])) ?></strong><span class="g-kpi-label">Reste à encaisser (FCFA)</span></div>
</div>

<div class="g-grid g-grid-2">
    <section class="g-card is-rdv" aria-labelledby="pil-dossiers">
        <div class="g-card-head"><h3 id="pil-dossiers"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span>Dossiers ouverts, 12 mois</h3></div>
        <ol class="g-barres" aria-label="Dossiers ouverts par mois">
            <?php foreach ($mensuel as $m): ?>
                <li><span class="g-barre" style="--h: <?= (int) round(100 * $m['dossiers'] / $maxDossiers) ?>%"></span><strong><?= (int) $m['dossiers'] ?></strong><small><?= e($libelleMois($m['mois'])) ?></small></li>
            <?php endforeach; ?>
        </ol>
    </section>
    <section class="g-card is-fin" aria-labelledby="pil-encaisse">
        <div class="g-card-head"><h3 id="pil-encaisse"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('chart', 18) ?></span>Encaissé, 12 mois (milliers de FCFA)</h3></div>
        <ol class="g-barres" aria-label="Encaissements par mois">
            <?php foreach ($mensuel as $m): ?>
                <li><span class="g-barre" style="--h: <?= (int) round(100 * $m['encaisse'] / $maxEncaisse) ?>%"></span><strong><?= e($nombre(intdiv((int) $m['encaisse'], 1000))) ?></strong><small><?= e($libelleMois($m['mois'])) ?></small></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="g-card is-neutre" aria-labelledby="pil-services">
        <div class="g-card-head"><h3 id="pil-services"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span>Ce mois, par service</h3></div>
        <?php if ($parService === []): ?><p class="g-empty">Aucun dossier ouvert ce mois.</p><?php else: ?>
            <dl class="g-chiffres">
                <?php foreach ($parService as $cle => $n): ?><div><dt><?= e($services[$cle] ?? $cle) ?></dt><dd><?= (int) $n ?></dd></div><?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </section>
    <section class="g-card is-neutre" aria-labelledby="pil-rdv">
        <div class="g-card-head"><h3 id="pil-rdv"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('calendar', 18) ?></span>Rendez-vous du mois</h3></div>
        <dl class="g-chiffres">
            <?php foreach ($rdvLibelles as $cle => [$libelle, $couleur]): ?><div><dt><?= g_badge($libelle, $couleur) ?></dt><dd><?= (int) ($rdv[$cle] ?? 0) ?></dd></div><?php endforeach; ?>
        </dl>
    </section>
    <section class="g-card is-neutre" aria-labelledby="pil-prospects">
        <div class="g-card-head"><h3 id="pil-prospects"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('target', 18) ?></span>Prospection du mois</h3></div>
        <dl class="g-chiffres">
            <div><dt>Nouveaux prospects</dt><dd><?= (int) $prospects['nouveaux'] ?></dd></div>
            <div><dt>Transmis à un conseiller</dt><dd><?= (int) $prospects['transmis'] ?></dd></div>
            <div><dt>Taux de transmission</dt><dd><?= $taux ?> %</dd></div>
        </dl>
    </section>
    <section class="g-card is-rdv" aria-labelledby="pil-exports">
        <div class="g-card-head"><h3 id="pil-exports"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('download', 18) ?></span>Exports (tableur)</h3></div>
        <div class="g-case-tools">
            <a class="g-btn g-btn-primary" href="<?= e(url('/gestion/exports/dossiers/')) ?>">Dossiers</a>
            <a class="g-btn g-btn-primary" href="<?= e(url('/gestion/exports/finance/')) ?>">Finance</a>
            <a class="g-btn g-btn-primary" href="<?= e(url('/gestion/exports/prospects/')) ?>">Prospects</a>
        </div>
        <p class="g-muted g-form-note">Fichiers CSV pour Excel. Chaque export est inscrit au journal d’audit : ils contiennent des données personnelles, à ne pas transférer hors de l’agence.</p>
    </section>
</div>
