<?php
/**
 * Données personnelles d'une fiche (/gestion/personnes/{id}/donnees/), administrateur (phase 7, lot 7C).
 *
 * @var array              $personne
 * @var array<string, int> $resume Nombre d'éléments par catégorie de l'export
 */
$errors = errors();
$libelles = [
    'fiches' => 'Fiches (avec les fiches fusionnées)', 'comptes' => 'Comptes client', 'dossiers' => 'Dossiers',
    'suivi_des_dossiers' => 'Étapes de suivi visibles', 'pieces' => 'Pièces déposées', 'resultats_admission' => 'Résultats d’admission',
    'reglements' => 'Règlements', 'rendez_vous' => 'Rendez-vous', 'visites_agence' => 'Visites en agence', 'prospection' => 'Prospection',
    'messages_contact' => 'Messages de contact', 'messages_equipe' => 'Messages de l’équipe', 'notes_equipe' => 'Notes de l’équipe',
];
$efface = !empty($personne['erased_at']);
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/personnes/' . $personne['id'] . '/')) ?>"><?= gestion_icone('collapse', 15) ?> Fiche</a></p>

<div class="g-grid g-grid-2">
    <section class="g-card is-rdv" aria-labelledby="don-titre">
        <div class="g-card-head"><h3 id="don-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('lock', 18) ?></span>Ce que Taysir garde</h3><?= $efface ? g_badge('Anonymisée', 'grey') : '' ?></div>
        <dl class="g-chiffres">
            <?php foreach ($libelles as $cle => $libelle): ?><div><dt><?= e($libelle) ?></dt><dd><?= (int) ($resume[$cle] ?? 0) ?></dd></div><?php endforeach; ?>
        </dl>
        <div class="g-case-tools" style="margin-top: 1rem">
            <a class="g-btn g-btn-primary" href="<?= e(url('/gestion/personnes/' . $personne['id'] . '/donnees/export/')) ?>"><?= gestion_icone('download', 16) ?> Exporter (JSON)</a>
        </div>
        <p class="g-muted g-form-note">Pour une demande d’accès : relisez le fichier (notes de l’équipe comprises) avant de l’envoyer à la personne. L’export est inscrit au journal d’audit.</p>
    </section>

    <section class="g-card is-<?= $efface ? 'neutre' : 'urgent' ?>" aria-labelledby="ano-titre">
        <div class="g-card-head"><h3 id="ano-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('alert', 18) ?></span>Anonymiser la fiche</h3></div>
        <?php if ($efface): ?>
            <p>Fiche anonymisée le <?= e(g_date($personne['erased_at'])) ?>. Les règlements et les reçus sont gardés pour la comptabilité.</p>
        <?php else: ?>
            <p>Remplace le nom, les coordonnées et les textes libres (notes, messages, motifs) de la fiche, de ses comptes client, dossiers, rendez-vous et visites, et supprime les fichiers des pièces. <strong>Irréversible.</strong></p>
            <p class="g-muted">Gardés : règlements et reçus (obligation comptable), statistiques. Refusé tant qu’un dossier est en cours.</p>
            <form method="post" action="<?= e(url('/gestion/personnes/' . $personne['id'] . '/anonymiser/')) ?>" class="g-grid" novalidate>
                <?= csrf_field() ?>
                <label class="g-inline-check"><input type="checkbox" name="comprend" value="1"> Je comprends que l’anonymisation est définitive.</label>
                <label class="g-form-row">Tapez « <?= e($personne['first_name'] . ' ' . $personne['last_name']) ?> » pour confirmer
                    <input class="g-input" name="confirmation" autocomplete="off" spellcheck="false">
                </label>
                <?php if (isset($errors['anonymiser'])): ?><p class="g-field-error"><?= e($errors['anonymiser']) ?></p><?php endif; ?>
                <div><button class="g-btn g-btn-danger" type="submit"><?= gestion_icone('alert', 16) ?> Anonymiser définitivement</button></div>
            </form>
        <?php endif; ?>
    </section>
</div>
