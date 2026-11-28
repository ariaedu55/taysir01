<?php
/**
 * Maquette 2.0 : fiche personne (docs/gestion-phase-2.md, étapes 2A et 2C ; exigences C2 et C5).
 * - Notes internes jamais affichées à la vue transversale (secrétaire, comptable) ni hors affectation.
 * - Compte rendu : choix « interne » ou « partagé » obligatoire, sans valeur par défaut.
 * - Ouverture d'un dossier : modèle publié, brouillon signalé (non proposé en production), ou sans modèle.
 * Données fictives.
 *
 * @var string $poste
 * @var array  $personne, $modeles
 */
$base = '/gestion/maquette';
$p = $personne;
if (in_array($poste, ['prospection', 'commerciale'], true)) {
    http_response_code(403);
    ?>
    <section class="g-card g-denied" role="alert">
        <span class="g-denied-icon" aria-hidden="true"><?= gestion_icone('lock', 22) ?></span>
        <div>
            <h2>Cette personne n’est pas dans votre périmètre</h2>
            <p>Vos fiches (prospects) arrivent avec la prospection, en phase 6. Chaque accès hors périmètre est journalisé.</p>
            <a class="g-btn g-btn-sm" href="<?= e(url($base . '/')) ?>">Revenir à Ma journée</a>
        </div>
    </section>
    <?php
    return;
}
$transversal = in_array($poste, ['secretaire', 'comptable'], true);
// Postes affectables : lire et écrire les notes (en 2C, seulement pour les personnes qui leur sont affectées)
$peutEcrire = in_array($poste, ['admin', 'conseiller', 'orientation', 'traitement'], true);
$peutOuvrir = in_array($poste, ['admin', 'conseiller'], true);
$notes = array_values(array_filter($p['notes'], static fn (array $n): bool => !$transversal || $n[2] === 'partagee'));
$masquees = count($p['notes']) - count($notes);
$statuts = ['confirme' => ['Confirmé', 'ok'], 'honore' => ['Honoré', 'neutre'], 'demande' => ['À confirmer', 'ech']];
$modes = ['agence-dakar' => 'Agence de Dakar', 'telephone' => 'Téléphone', 'visio' => 'Visio'];
$badge = static fn (array $s): string => '<span class="g-badge is-' . e($s[1]) . '"><span class="g-dot"></span>' . e($s[0]) . '</span>';
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="#" data-g-soon><?= gestion_icone('collapse', 15) ?> Personnes</a></p>

<header class="g-case-head">
    <div>
        <div class="g-case-title">
            <h2><?= e($p['nom']) ?></h2>
            <span class="g-badge is-ok"><span class="g-dot"></span>Compte client actif</span>
            <span class="g-badge is-neutre"><span class="g-dot"></span>Origine : <?= e($p['origine']) ?></span>
        </div>
        <p class="g-case-sub"><?= e($p['profil']) ?> · <?= e($p['agence']) ?> · fuseau <?= e($p['fuseau']) ?></p>
        <div class="g-owners">
            <span class="g-owner"><span class="g-avatar is-sm is-rdv" aria-hidden="true"><?= gestion_icone('phone', 12) ?></span><span><small>Téléphone</small><?= e($p['telephone']) ?></span></span>
            <span class="g-owner"><span class="g-avatar is-sm is-rdv" aria-hidden="true"><?= gestion_icone('send', 12) ?></span><span><small>E-mail</small><?= e($p['email']) ?></span></span>
            <span class="g-owner is-empty"><span class="g-avatar is-sm" aria-hidden="true"><?= gestion_icone('users', 12) ?></span><span><small>Lien familial</small><?= e($p['lien']) ?></span></span>
        </div>
    </div>
    <div class="g-case-actions">
        <a class="g-btn" href="<?= e(url($base . '/agenda/')) ?>"><?= gestion_icone('calendar', 16) ?> Nouveau rendez-vous</a>
        <?php if ($peutOuvrir): ?><button class="g-btn g-btn-primary" type="button" data-g-ouvrir-dossier><?= gestion_icone('folder', 16) ?> Ouvrir un dossier</button><?php endif; ?>
    </div>
</header>

<div class="g-grid g-grid-2">
    <div class="g-grid">
        <section class="g-card is-ori" aria-labelledby="notes-titre" id="compte-rendu">
            <div class="g-card-head"><h3 id="notes-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('chat', 18) ?></span>Comptes rendus et notes</h3></div>
            <?php if ($peutEcrire): ?>
                <form class="g-note-form" onsubmit="return false" data-g-note-form>
                    <label class="g-form-row">Compte rendu de l’entretien <?= $p['rdv'][0][1] === 'aujourd’hui' ? 'd’aujourd’hui' : 'du ' . e($p['rdv'][0][1]) ?> à <?= e($p['rdv'][0][2]) ?> (<?= e($p['rdv'][0][3]) ?>)
                        <textarea class="g-textarea" placeholder="Ce qui a été dit, ce qui est décidé, la prochaine étape…" required></textarea>
                    </label>
                    <fieldset class="g-choice" aria-describedby="choix-aide">
                        <legend>Qui peut lire ce compte rendu ? <span class="g-muted">(choix obligatoire)</span></legend>
                        <label class="g-choice-card is-ori"><input type="radio" name="visibilite" value="interne" required>
                            <span><strong><?= gestion_icone('lock', 15) ?> Interne</strong><small>L’équipe affectée seulement. Jamais la personne, ni l’accueil, ni la comptabilité.</small></span></label>
                        <label class="g-choice-card is-ok"><input type="radio" name="visibilite" value="partagee" required>
                            <span><strong><?= gestion_icone('eye', 15) ?> Partagé</strong><small>Visible dans l’espace de la personne ; un e-mail l’en avertit.</small></span></label>
                    </fieldset>
                    <p id="choix-aide" class="g-field-error" hidden data-g-choix-erreur>Choisissez qui peut lire ce compte rendu.</p>
                    <button class="g-btn g-btn-success" type="submit"><?= gestion_icone('check', 16) ?> Enregistrer le compte rendu</button>
                </form>
            <?php endif; ?>
            <ul class="g-notes" style="margin-top:<?= $peutEcrire ? '1rem' : '0' ?>">
                <?php foreach ($notes as [$auteur, $role, $visibilite, $texte, $date, $rdv]): ?>
                    <li class="g-note<?= $visibilite === 'interne' ? ' is-interne' : '' ?>">
                        <header>
                            <strong><?= e($auteur) ?></strong><small><?= e($role) ?> · <?= e($date) ?><?= $rdv ? ' · ' . e($rdv) : '' ?></small>
                            <?php if ($visibilite === 'interne'): ?><span class="g-badge is-ori"><span class="g-dot"></span>Interne</span><?php else: ?><span class="g-badge is-ok"><span class="g-dot"></span>Partagé avec la personne</span><?php endif; ?>
                        </header>
                        <p><?= e($texte) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($masquees > 0): ?>
                <p class="g-locked" style="margin-top:.7rem"><?= gestion_icone('lock', 16) ?> Les notes internes de l’équipe affectée ne sont pas visibles depuis votre poste.</p>
            <?php endif; ?>
        </section>

        <section class="g-card is-rdv" aria-labelledby="rdv-titre">
            <div class="g-card-head"><h3 id="rdv-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('calendar', 18) ?></span>Rendez-vous</h3><a href="<?= e(url($base . '/agenda/')) ?>">Agenda</a></div>
            <ul class="g-agenda">
                <?php foreach ($p['rdv'] as [$id, $quand, $h, $motif, $avec, $statut, $mode]): ?>
                    <li><time><?= e($h) ?></time><div><strong><?= e($quand) ?> · <?= e($motif) ?> <?= $badge($statuts[$statut]) ?></strong><span><?= e($avec) ?> · <?= e($modes[$mode]) ?> · <?= e($id) ?></span></div></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <div class="g-grid">
        <section class="g-card is-ok" aria-labelledby="dossiers-titre">
            <div class="g-card-head"><h3 id="dossiers-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span>Dossiers <span class="g-count"><?= count($p['dossiers']) ?></span></h3></div>
            <ul class="g-docs">
                <?php foreach ($p['dossiers'] as [$ref, $service, $destination, $rentree, $etape, $total, $statut, $ton, $modele]): ?>
                    <li class="g-doc">
                        <span class="g-doc-icon" aria-hidden="true"><?= gestion_icone('folder', 17) ?></span>
                        <div><strong><?= e($service) ?> · <?= e($destination) ?></strong><p><?= e($ref) ?> · rentrée <?= e($rentree) ?> · modèle <?= e($modele) ?></p>
                            <div class="g-progress"><span class="g-progress-bar"><i style="width:<?= round($etape / $total * 100) ?>%"></i></span><small>étape <?= (int) $etape ?> sur <?= (int) $total ?></small></div></div>
                        <div class="g-doc-actions"><a class="g-btn g-btn-sm" href="<?= e(url($base . '/dossiers/' . $ref . '/')) ?>">Ouvrir</a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($peutOuvrir): ?>
            <section class="g-card is-pro" aria-labelledby="ouvrir-titre" data-g-ouvrir-carte hidden>
                <div class="g-card-head"><h3 id="ouvrir-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('plus', 18) ?></span>Ouvrir un dossier</h3></div>
                <form onsubmit="return false" data-g-ouvrir-form>
                    <div class="g-form-grid">
                        <label class="g-form-row">Service<select class="g-select"><option>Admission</option><option>Admission et visa études</option><option>Visa</option><option>Logement</option></select></label>
                        <label class="g-form-row">Destination<select class="g-select"><option>France</option><option>Canada</option><option>Belgique</option></select></label>
                        <label class="g-form-row">Rentrée<select class="g-select"><option>Septembre 2027</option><option>Janvier 2028</option></select></label>
                    </div>
                    <fieldset class="g-choice">
                        <legend>Modèle de procédure</legend>
                        <?php foreach ($modeles as [$cle, $libelle, $version, $statut, $verifie, $par]): ?>
                            <label class="g-choice-card <?= $statut === 'brouillon' ? 'is-ech is-draft' : 'is-ok' ?>">
                                <input type="radio" name="modele" value="<?= e($cle) ?>"<?= $statut === 'brouillon' ? ' disabled' : '' ?>>
                                <span><strong><?= e($libelle) ?></strong>
                                    <small><?= $statut === 'brouillon' ? 'Brouillon · à faire vérifier par l’agence · non proposé pour un dossier réel' : 'Version ' . (int) $version . ' · vérifié le ' . e($verifie) . ' par ' . e($par) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="g-choice-card is-neutre"><input type="radio" name="modele" value="aucun"><span><strong>Sans modèle</strong><small>Le dossier s’ouvre sans étapes ; elles se suivent dans le fil du dossier.</small></span></label>
                    </fieldset>
                    <p class="g-muted" style="font-size:.76rem;margin:0 0 .8rem">Le dossier vous sera affecté comme conseiller, puis suivra la file de l’orientation et du traitement.</p>
                    <button class="g-btn g-btn-success" type="submit"><?= gestion_icone('folder', 16) ?> Ouvrir le dossier</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="g-card is-ech" aria-labelledby="taches-titre">
            <div class="g-card-head"><h3 id="taches-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('check', 18) ?></span>Tâches</h3><?php if ($peutEcrire): ?><button class="g-btn g-btn-sm" type="button" data-g-soon><?= gestion_icone('plus', 15) ?> Tâche</button><?php endif; ?></div>
            <ul class="g-checklist">
                <?php foreach ($p['taches'] as [$tache, $echeance, $qui, $fait]): ?>
                    <li class="<?= $fait ? 'is-done' : '' ?>"><span class="g-tick" aria-hidden="true"><?= $fait ? '✓' : '' ?></span><span><?= e($tache) ?><br><small><?= e($qui) ?></small></span><small><?= e($echeance) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>

<script>
// Maquette : choix de visibilité obligatoire ; ouverture d'un dossier.
(() => {
    const note = document.querySelector('[data-g-note-form]');
    note?.addEventListener('submit', () => {
        const choix = note.querySelector('input[name="visibilite"]:checked');
        const erreur = note.querySelector('[data-g-choix-erreur]');
        if (!choix) {
            erreur.hidden = false;
            note.querySelector('.g-choice').classList.add('is-invalid');
            note.querySelector('input[name="visibilite"]').focus();
            return;
        }
        erreur.hidden = true;
        note.querySelector('.g-choice').classList.remove('is-invalid');
        window.gestionToast?.(choix.value === 'partagee'
            ? 'Compte rendu partagé : visible dans l’espace de la personne, e-mail en file.'
            : 'Compte rendu interne enregistré : visible de l’équipe affectée seulement.');
    });
    const carte = document.querySelector('[data-g-ouvrir-carte]');
    document.querySelector('[data-g-ouvrir-dossier]')?.addEventListener('click', () => {
        carte.hidden = false;
        carte.scrollIntoView({ behavior: 'smooth', block: 'start' });
        carte.querySelector('select')?.focus();
    });
    document.querySelector('[data-g-ouvrir-form]')?.addEventListener('submit', (event) => {
        const modele = event.target.querySelector('input[name="modele"]:checked');
        window.gestionToast?.(modele ? 'Dossier TAY-2027-0156 ouvert et affecté à vous. Étapes copiées du modèle.' : 'Choisissez un modèle publié, ou « Sans modèle ».');
        if (modele) { carte.hidden = true; }
    });
})();
</script>
