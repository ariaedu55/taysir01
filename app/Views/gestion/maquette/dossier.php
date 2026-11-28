<?php
/**
 * Fiche dossier (docs/gestion-plan.md §12.2) : un écran mutualisé, chaque poste y a son volet.
 * Données fictives : le détail affiché est celui de TAY-2027-0142, l'en-tête suit le dossier choisi.
 *
 * @var string $poste
 * @var array  $dossier, $fiche, $statuts, $etapes, $equipe
 */
[$ref, $nom, $parcours, $service, $destination, $rentree, $etapeActuelle, $statut] = $dossier;
[$libelle, $ton] = $statuts[$statut];
$base = '/gestion/maquette';
// Brouillon de recommandation selon la destination souhaitée (fictif)
$propositions = [
    'France' => 'Licence MIASHS : Lyon 2, Grenoble Alpes, Toulouse Capitole. BUT Informatique : IUT de Lyon 1.',
    'Canada' => 'Baccalauréat en informatique : Université Laval, UQAM, Université de Sherbrooke. DEC Techniques de l’informatique : Cégep de Sainte-Foy.',
    'Belgique' => 'Bachelier en informatique : Université libre de Bruxelles, UCLouvain, Université de Liège.',
];
$aValider = count(array_filter($fiche['pieces'], static fn (array $p): bool => $p[1] === 'recue'));
$initiales = static fn (string $n): string => implode('', array_map(static fn (string $w): string => mb_substr($w, 0, 1), array_slice(explode(' ', $n), 0, 2)));
$postesLib = ['conseiller' => 'Conseiller', 'orientation' => 'Orientation', 'traitement' => 'Traitement', 'comptable' => 'Comptabilité'];
$actions = [
    'traitement' => [['Relancer le client', 'send', 'Relance envoyée : la liste des pièces manquantes part par e-mail au client.'], ['Marquer l’étape faite', 'check', 'Étape « Paiement des frais Campus France » marquée comme faite.']],
    'conseiller' => [['Noter', 'chat', 'Note ajoutée (démonstration).'], ['Planifier un suivi', 'calendar', 'Suivi planifié dans votre agenda.']],
    'orientation' => [['Planifier le rendez-vous d’orientation', 'calendar', 'Rendez-vous d’orientation proposé à la cliente.']],
    'comptable' => [['Enregistrer un règlement', 'wallet', 'Règlement enregistré : reçu DKR-2027-000231 généré.']],
    'secretaire' => [['Prendre un rendez-vous', 'calendar', 'Rendez-vous ajouté à l’agenda.']],
    'admin' => [['Réaffecter', 'swap', 'Dossier réaffecté.'], ['Noter', 'chat', 'Note ajoutée.']],
][$poste] ?? [['Noter', 'chat', 'Note ajoutée.']];
// Volets visibles et leur ordre selon la mission du poste (revue 1A, ajustement 2). Le premier s'ouvre par défaut.
$libellesOnglets = ['apercu' => 'Aperçu', 'pieces' => 'Pièces', 'procedure' => 'Procédure', 'orientation' => 'Orientation', 'paiements' => 'Paiements', 'notes' => 'Notes', 'historique' => 'Historique'];
$onglets = [
    'admin' => ['apercu', 'pieces', 'procedure', 'orientation', 'paiements', 'notes', 'historique'],
    'secretaire' => ['apercu', 'pieces', 'historique'],
    'conseiller' => ['apercu', 'notes', 'orientation', 'pieces', 'procedure', 'historique'],
    'orientation' => ['orientation', 'apercu', 'notes', 'historique'],
    'traitement' => ['pieces', 'procedure', 'apercu', 'notes', 'historique'],
    'comptable' => ['paiements', 'apercu', 'historique'],
][$poste] ?? [];
// La secrétaire voit l'état des pièces sans pouvoir les valider
$lectureSeulePieces = !in_array($poste, ['admin', 'traitement'], true);
$tonsPiece = ['valide' => ['Validée', 'succes'], 'recue' => ['À valider', 'alerte'], 'refusee' => ['Refusée', 'urgent'], 'manquante' => ['Manquante', 'neutre']];
if ($onglets === []):
    // Hors périmètre : aperçu de la règle 1B (refus 403 côté serveur)
?>
<section class="g-card g-denied" role="alert">
    <span class="g-denied-icon" aria-hidden="true"><?= gestion_icone('lock', 22) ?></span>
    <div>
        <h2>Ce dossier n’est pas dans votre périmètre</h2>
        <p>Votre poste ne donne pas accès aux dossiers clients. Si vous en avez besoin, demandez-le à l’administratrice ; chaque accès est journalisé.</p>
        <a class="g-btn g-btn-sm" href="<?= e(url($base . '/')) ?>">Revenir à Ma journée</a>
    </div>
</section>
<?php return; endif; ?>
<p style="margin:0 0 .8rem"><a class="g-btn g-btn-sm" href="<?= e(url($base . '/dossiers/')) ?>"><?= gestion_icone('collapse', 15) ?> Dossiers</a></p>

<header class="g-case-head">
    <div>
        <div class="g-case-title">
            <h2><?= e($nom) ?></h2>
            <span class="g-badge is-<?= e($ton) ?>"><span class="g-dot is-<?= e($ton) ?>"></span><?= e($libelle) ?></span>
            <span class="g-badge is-neutre"><?= $parcours === 'etudes' ? 'Parcours études' : 'Parcours voyage' ?></span>
        </div>
        <p class="g-case-sub"><?= e($ref) ?> · <?= e($service) ?> · <?= e($destination) ?><?= $rentree !== '—' ? ' · rentrée ' . e($rentree) : '' ?></p>
        <div class="g-owners" aria-label="Responsables par poste">
            <?php foreach ($fiche['responsables'] as $p => $qui): ?>
                <span class="g-owner"><span class="g-avatar is-sm" aria-hidden="true"><?= e($initiales($qui)) ?></span><span><small><?= e($postesLib[$p]) ?></small><span<?= $p === 'traitement' ? ' data-g-owner-traitement' : '' ?>><?= e($qui) ?></span></span></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="g-case-actions">
        <?php foreach ($actions as $i => [$label, $icone, $message]): ?>
            <button class="g-btn<?= $i === 0 ? ' g-btn-primary' : '' ?>" type="button" data-g-demo="action" data-g-message="<?= e($message) ?>"><?= gestion_icone($icone, 17) ?> <?= e($label) ?></button>
        <?php endforeach; ?>
    </div>
</header>

<ol class="g-steps" aria-label="Étapes du parcours">
    <?php foreach ($etapes[$parcours] as $i => $etape): ?>
        <li class="<?= $i < $etapeActuelle ? 'is-done' : ($i === $etapeActuelle ? 'is-current' : '') ?>"<?= $i === $etapeActuelle ? ' aria-current="step"' : '' ?>>
            <span class="g-step-dot"><?= $i < $etapeActuelle ? '✓' : $i + 1 ?></span><span><?= e($etape) ?><?php if ($i === $etapeActuelle): ?><small>en cours</small><?php elseif ($i < $etapeActuelle): ?><small class="sr-only">faite</small><?php endif; ?></span>
        </li>
    <?php endforeach; ?>
</ol>

<?php if ($ref !== 'TAY-2027-0142'): ?>
    <p class="g-mock-note">Maquette : l’en-tête et les étapes sont ceux de ce dossier ; le détail des volets reprend le dossier d’exemple TAY-2027-0142.</p>
<?php endif; ?>
<div class="g-tabs" role="tablist" aria-label="Volets du dossier" data-g-tabs>
    <?php foreach ($onglets as $cle): $label = $libellesOnglets[$cle]; ?>
        <button type="button" role="tab" id="tab-<?= $cle ?>" aria-controls="volet-<?= $cle ?>" data-name="<?= $cle ?>"><?= e($label) ?><?= $cle === 'pieces' && $aValider ? '<span class="g-count">' . $aValider . '</span>' : '' ?></button>
    <?php endforeach; ?>
</div>

<?php if (in_array('apercu', $onglets, true)): ?>
<section class="g-tabpanel" id="volet-apercu" role="tabpanel" aria-labelledby="tab-apercu">
    <div class="g-grid g-grid-2">
        <div class="g-card">
            <div class="g-card-head"><h3>Prochaines actions</h3></div>
            <ul class="g-tasks">
                <?php if (in_array('pieces', $onglets, true)): ?>
                <li class="g-task"><span class="g-dot is-alerte"></span><div><strong>Valider le relevé de notes de terminale</strong><p>Déposé par la cliente il y a 25 min.</p></div><div class="g-task-actions"><a class="g-btn g-btn-sm g-btn-primary" href="?onglet=pieces">Voir la pièce</a></div></li>
                <?php endif; ?>
                <li class="g-task"><span class="g-dot is-alerte"></span><div><strong>Frais Campus France : échéance dans 2 jours</strong><p>Un rappel automatique part à la cliente la veille.</p></div><?php if (in_array('paiements', $onglets, true)): ?><div class="g-task-actions"><a class="g-btn g-btn-sm" href="?onglet=paiements">Paiements</a></div><?php endif; ?></li>
                <li class="g-task"><span class="g-dot is-info"></span><div><strong>Point dossier aujourd’hui à 11:00</strong><p>Agence de Dakar, avec Cheikh Gueye.</p></div></li>
            </ul>
        </div>
        <div class="g-card">
            <div class="g-card-head"><h3>Personne</h3><a href="#" data-g-soon>Fiche complète</a></div>
            <dl class="g-dl">
                <dt>Nom</dt><dd><?= e($fiche['personne'][0]) ?></dd>
                <dt>Profil</dt><dd><?= e($fiche['personne'][1]) ?></dd>
                <dt>Téléphone</dt><dd><?= e($fiche['personne'][2]) ?></dd>
                <dt>E-mail</dt><dd><?= e($fiche['personne'][3]) ?></dd>
                <dt>Lien</dt><dd><?= e($fiche['personne'][4]) ?></dd>
            </dl>
            <p class="g-muted" style="margin:0;font-size:.8rem">Autre dossier : <strong>Admission Belgique</strong> (plan B, en attente).</p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (in_array('pieces', $onglets, true)): ?>
<section class="g-tabpanel" id="volet-pieces" role="tabpanel" aria-labelledby="tab-pieces" hidden>
    <ul class="g-docs">
        <?php foreach ($fiche['pieces'] as [$piece, $etat, $detail]): ?>
            <?php [$etatLib, $etatTon] = $tonsPiece[$etat]; ?>
            <li class="g-doc<?= $etat === 'recue' ? ' is-recue' : '' ?>" data-g-item="<?= e($piece) ?>">
                <span class="g-doc-icon" aria-hidden="true"><?= gestion_icone('file', 18) ?></span>
                <div><strong><?= e($piece) ?></strong><p><?= e($detail) ?></p></div>
                <div class="g-doc-actions">
                    <?php if ($etat === 'recue' && $lectureSeulePieces): ?>
                        <span class="g-badge is-alerte"><span class="g-dot is-alerte"></span>À valider par le traitement</span>
                    <?php elseif ($etat === 'recue'): ?>
                        <button class="g-btn g-btn-sm" type="button" data-g-demo="apercu" data-g-message="Aperçu de la pièce (journalisé dans l’historique des accès)."><?= gestion_icone('eye', 15) ?> Voir</button>
                        <button class="g-btn g-btn-sm g-btn-danger" type="button" data-g-demo="refuser">Refuser</button>
                        <button class="g-btn g-btn-sm g-btn-success" type="button" data-g-demo="valider"><?= gestion_icone('check', 15) ?> Valider</button>
                    <?php elseif ($etat === 'manquante'): ?>
                        <span class="g-badge is-neutre"><span class="g-dot is-neutre"></span><?= e($etatLib) ?></span>
                        <button class="g-btn g-btn-sm" type="button" data-g-demo="relance" data-g-message="Relance envoyée au client pour cette pièce.">Relancer</button>
                    <?php else: ?>
                        <span class="g-badge is-<?= e($etatTon) ?>"><span class="g-dot is-<?= e($etatTon) ?>"></span><?= e($etatLib) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($etat === 'recue' && !$lectureSeulePieces): ?>
                    <div class="g-refuse" hidden>
                        <label class="g-form-row" style="margin:0">Motif du refus (envoyé à la cliente)
                            <textarea class="g-textarea" placeholder="Ex. : relevé illisible, merci d’envoyer un scan en couleur." aria-describedby="err-<?= e(md5($piece)) ?>"></textarea>
                        </label>
                        <p class="g-field-error" id="err-<?= e(md5($piece)) ?>" hidden>Indiquez le motif : la cliente le reçoit pour corriger sa pièce.</p>
                        <div><button class="g-btn g-btn-sm g-btn-danger" type="button" data-g-demo="refuser-envoyer">Refuser et prévenir la cliente</button></div>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (in_array('procedure', $onglets, true)): ?>
<section class="g-tabpanel" id="volet-procedure" role="tabpanel" aria-labelledby="tab-procedure" hidden>
    <div class="g-card">
        <div class="g-card-head"><h3>France : études en France (Campus France)</h3><span class="g-badge is-neutre">Modèle version 3</span></div>
        <ul class="g-checklist">
            <?php foreach ($fiche['procedure'] as [$etape, $fait, $quand]): ?>
                <li class="<?= $fait ? 'is-done' : '' ?>"><span class="g-tick" aria-hidden="true"><?= $fait ? '✓' : '' ?></span><span><?= e($etape) ?></span><small><?= e($quand) ?></small></li>
            <?php endforeach; ?>
        </ul>
        <p class="g-source"><?= gestion_icone('route', 18) ?><span>Source officielle : <a href="<?= e($fiche['source'][1]) ?>" target="_blank" rel="noopener noreferrer"><?= e($fiche['source'][0]) ?></a><br><span class="g-muted"><?= e($fiche['source'][2]) ?></span></span></p>
    </div>
</section>
<?php endif; ?>

<?php if (in_array('orientation', $onglets, true)): ?>
<section class="g-tabpanel" id="volet-orientation" role="tabpanel" aria-labelledby="tab-orientation" hidden>
    <form class="g-card" onsubmit="return false">
        <div class="g-card-head"><h3>Recommandation d’orientation</h3><span class="g-muted" style="font-size:.8rem">Compte rendu du conseiller : <a href="?onglet=notes">voir les notes</a></span></div>
        <div class="g-grid" style="grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:1rem">
            <label class="g-form-row">Destination recommandée<select class="g-select"><?php foreach (['France', 'Belgique', 'Canada'] as $pays): ?><option<?= $pays === $destination ? ' selected' : '' ?>><?= e($pays) ?></option><?php endforeach; ?></select></label>
            <label class="g-form-row">Rentrée visée<select class="g-select"><option>Septembre 2027</option><option<?= str_starts_with($rentree, 'Janv') ? ' selected' : '' ?>>Janvier 2028</option></select></label>
        </div>
        <label class="g-form-row">Établissements et formations recommandés<textarea class="g-textarea"><?= e($propositions[$destination] ?? '') ?></textarea></label>
        <label class="g-form-row">Transmettre à l’agent de traitement
            <select class="g-select" id="agent-traitement"><option>Cheikh Gueye</option><option>Aminata Kane</option><option>Babacar Ly</option><option>Mame Diarra Fall</option></select>
        </label>
        <button class="g-btn g-btn-primary" type="button" data-g-demo="affecter" data-g-who="#agent-traitement"><?= gestion_icone('send', 17) ?> Affecter à l’agent de traitement</button>
    </form>
</section>
<?php endif; ?>

<?php if (in_array('paiements', $onglets, true)): ?>
<section class="g-tabpanel" id="volet-paiements" role="tabpanel" aria-labelledby="tab-paiements" hidden>
    <div class="g-card">
        <div class="g-card-head"><h3>Échéancier</h3><button class="g-btn g-btn-sm g-btn-success" type="button" data-g-demo="reglement" data-g-message="Règlement enregistré : reçu DKR-2027-000231 généré et envoyé.">Enregistrer un règlement</button></div>
        <table class="g-pay">
            <?php foreach ($fiche['paiements'] as [$libelle, $montant, $quand, $etat]): ?>
                <?php [$t, $l] = ['paye' => ['succes', 'Payé'], 'du' => ['alerte', 'Dû'], 'avenir' => ['neutre', 'À venir']][$etat]; ?>
                <tr><td><?= e($libelle) ?><br><small class="g-muted"><?= e($quand) ?></small></td><td><?= e($montant) ?></td><td style="text-align:right"><span class="g-badge is-<?= $t ?>"><span class="g-dot is-<?= $t ?>"></span><?= $l ?></span></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if (in_array('notes', $onglets, true)): ?>
<section class="g-tabpanel" id="volet-notes" role="tabpanel" aria-labelledby="tab-notes" hidden>
    <ul class="g-notes">
        <?php foreach ($fiche['notes'] as [$auteur, $role, $type, $texte, $quand]): ?>
            <li class="g-note<?= $type === 'interne' ? ' is-interne' : '' ?>">
                <header><strong><?= e($auteur) ?></strong><span class="g-muted"><?= e($role) ?></span>
                    <span class="g-badge <?= $type === 'interne' ? 'is-neutre' : 'is-succes' ?>"><?= $type === 'interne' ? gestion_icone('lock', 13) . ' Interne' : gestion_icone('eye', 13) . ' Partagée avec la cliente' ?></span>
                    <small><?= e($quand) ?></small></header>
                <p><?= e($texte) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
    <form class="g-card" style="margin-top:1rem" onsubmit="return false">
        <label class="g-form-row">Nouvelle note<textarea class="g-textarea" placeholder="Votre note…"></textarea></label>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:1rem">
            <label><input type="radio" name="visibilite" checked> Interne (équipe)</label>
            <label><input type="radio" name="visibilite"> Partagée avec la cliente</label>
            <button class="g-btn g-btn-primary" type="button" data-g-demo="note" data-g-message="Note ajoutée au dossier.">Ajouter</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if (in_array('historique', $onglets, true)): ?>
<section class="g-tabpanel" id="volet-historique" role="tabpanel" aria-labelledby="tab-historique" hidden>
    <div class="g-card">
        <ul class="g-timeline">
            <?php foreach ($fiche['historique'] as [$quand, $quoi]): ?>
                <li><?= e($quoi) ?><small><?= e($quand) ?></small></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
