<?php
/**
 * Une démarche (/gestion/modeles/nouveau/ et /gestion/modeles/{id}/), administrateur (phase 12 ; 2C, E8, N5).
 * Brouillon : modifiable ; version publiée ou retirée : lecture seule, revérification, nouvelle version, duplication.
 *
 * @var array|null            $modele   null à la création
 * @var array|null            $prefill  valeurs de départ à la création (service, pays, motif, pièces, étapes, sources)
 * @var array|null            $source   démarche dupliquée
 * @var array<string, string> $services
 * @var list<string>          $pays     Pays couverts actifs
 * @var bool|null             $aRevoir
 */
use App\Models\ProcedureTemplate;
use App\Services\Demarches;

$errors = errors();
$brouillon = $modele === null || $modele['status'] === 'brouillon';
$v = $modele ?? $prefill;
$service = old('service', $v['service'] ?? 'admission');
$destination = old('destination', $v['destination'] ?? '');
$motif = old('purpose', $v['purpose'] ?? '');
$pieces = $modele !== null ? Demarches::pieces($modele) : ($prefill['documents'] ?? []);
$pieces[] = ['titre' => '', 'aide' => null, 'obligatoire' => true, 'fournisseur' => 'etudiant']; // une ligne vide pour ajouter
$etapesTexte = implode("\n", array_map(static fn (array $e): string => trim($e['titre'] . (($e['pieces'] ?? []) !== [] ? ' | ' . implode(' ; ', $e['pieces']) : '') . ' | ' . ($e['delai'] ?? ''), ' |'), $v['steps'] ?? []));
$sourcesTexte = implode("\n", $v['sources'] ?? []);
$action = $modele === null ? url('/gestion/modeles/') : url('/gestion/modeles/' . $modele['id'] . '/');
$champ = static fn (string $nom): string => isset($errors[$nom]) ? '<p class="g-field-error">' . e($errors[$nom]) . '</p>' : '';
$motifsParService = array_filter(array_column(config('services'), 'purposes', 'key'));
$autrePays = $destination !== '' && !in_array($destination, $pays, true);
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/modeles/')) ?>"><?= gestion_icone('collapse', 15) ?> Démarches et pièces</a></p>

<?php if ($modele !== null): ?>
    <header class="g-case-head">
        <div>
            <div class="g-case-title">
                <h2><?= e($modele['title']) ?></h2>
                <?= g_badge('Version ' . (int) $modele['version'], 'grey') ?>
                <?= g_badge(ProcedureTemplate::STATUTS[$modele['status']][0], ProcedureTemplate::STATUTS[$modele['status']][1]) ?>
                <?php if (!empty($aRevoir)): ?><?= g_badge('À revérifier', 'amber') ?><?php endif; ?>
            </div>
            <p class="g-case-sub"><?= e(Demarches::libelleModele($modele)) ?> · <?= $modele['verified_at'] ? 'vérifiée le ' . e(g_date($modele['verified_at'])) : 'jamais vérifiée' ?></p>
        </div>
        <div class="g-case-actions">
            <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="verifier"><button class="g-btn" type="submit"><?= gestion_icone('check', 16) ?> Marquer vérifiée aujourd’hui</button></form>
            <?php if ($modele['status'] === 'brouillon'): ?>
                <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="publier"><button class="g-btn g-btn-success" type="submit"><?= gestion_icone('send', 16) ?> Publier</button></form>
            <?php else: ?>
                <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="nouvelle-version"><button class="g-btn g-btn-primary" type="submit"><?= gestion_icone('plus', 16) ?> Nouvelle version</button></form>
            <?php endif; ?>
            <a class="g-btn" href="<?= e(url('/gestion/modeles/nouveau/?depuis=' . (int) $modele['id'])) ?>"><?= gestion_icone('swap', 16) ?> Dupliquer pour un autre pays</a>
            <?php if ($modele['status'] === 'publie'): ?>
                <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="retirer"><button class="g-btn g-btn-warning" type="submit">Retirer</button></form>
            <?php endif; ?>
        </div>
    </header>
    <?php if (isset($errors['modele'])): ?><p class="g-notice"><?= gestion_icone('alert', 16) ?> <?= e($errors['modele']) ?></p><?php endif; ?>
    <?php if ($modele['status'] === 'brouillon'): ?><p class="g-muted g-form-note">Pour publier : faites vérifier le contenu par l’agence (« Marquer vérifiée »), indiquez au moins une source officielle, puis « Publier ». Les dossiers déjà ouverts gardent leur version.</p><?php endif; ?>
<?php elseif ($source !== null): ?>
    <p class="g-notice"><?= gestion_icone('swap', 16) ?> Copie de « <?= e($source['title']) ?> » : choisissez le pays (et le motif) de la nouvelle démarche, puis ajustez les pièces.</p>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" novalidate class="g-grid">
    <?= csrf_field() ?>
    <?php if ($modele !== null): ?><input type="hidden" name="action" value="enregistrer"><?php endif; ?>
    <fieldset class="g-fieldset g-grid"<?= $brouillon ? '' : ' disabled' ?>>
        <section class="g-card is-ok" aria-labelledby="portee-titre">
            <div class="g-card-head"><h3 id="portee-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('target', 18) ?></span>À quels dossiers s’applique-t-elle ?</h3></div>
            <div class="g-form-grid g-form-2">
                <label class="g-form-row">Service
                    <select class="g-select" name="service" data-service-select>
                        <?php foreach ($services as $cle => $titre): ?><option value="<?= e($cle) ?>"<?= $service === $cle ? ' selected' : '' ?>><?= e($titre) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="g-form-row">Pays
                    <select class="g-select" name="destination">
                        <option value="">Tous les pays (modèle général)</option>
                        <?php foreach ($pays as $nomPays): ?><option value="<?= e($nomPays) ?>"<?= $destination === $nomPays ? ' selected' : '' ?>><?= e($nomPays) ?></option><?php endforeach; ?>
                        <option value="__autre"<?= $autrePays ? ' selected' : '' ?>>Autre pays…</option>
                    </select>
                </label>
                <label class="g-form-row">Autre pays <span class="g-muted">(si « Autre pays »)</span><input class="g-input" name="destination_autre" value="<?= e($autrePays ? $destination : '') ?>" maxlength="120"></label>
                <label class="g-form-row">Motif
                    <select class="g-select" name="purpose">
                        <option value="">Tous les motifs</option>
                        <?php foreach ($motifsParService as $cleService => $motifsService): ?>
                            <optgroup label="<?= e($services[$cleService] ?? $cleService) ?>">
                                <?php foreach ($motifsService as $cleMotif => $libelleMotif): ?><option value="<?= e($cleMotif) ?>"<?= $motif === $cleMotif ? ' selected' : '' ?>><?= e($libelleMotif) ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="g-form-row g-form-wide">Titre affiché <span class="g-muted">(facultatif : « Visa · France · Études » par défaut)</span><input class="g-input" name="title" value="<?= e(old('title', $modele['title'] ?? '')) ?>" maxlength="190"></label>
            </div>
            <?= $champ('service') . $champ('purpose') . $champ('destination') . $champ('title') . $champ('template_key') ?>
        </section>

        <section class="g-card is-rdv" aria-labelledby="pieces-titre">
            <div class="g-card-head"><h3 id="pieces-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('file', 18) ?></span>Pièces à fournir</h3></div>
            <p class="g-muted g-form-note">Une pièce par ligne. Le client voit le nom et l’aide ; il les dépose une par une dans son espace.</p>
            <div class="g-doc-rows" data-doc-rows>
                <div class="g-doc-row g-doc-head" aria-hidden="true"><span>Pièce</span><span>Aide pour le client</span><span>Demandée</span><span>Fournie par</span><span></span></div>
                <?php foreach ($pieces as $i => $p): ?>
                    <div class="g-doc-row" data-doc-row>
                        <input class="g-input" name="doc_titre[]" value="<?= e($p['titre']) ?>" maxlength="190" placeholder="Copie du passeport" aria-label="Pièce">
                        <input class="g-input" name="doc_aide[]" value="<?= e((string) $p['aide']) ?>" maxlength="255" placeholder="Pages d’identité, en couleur" aria-label="Aide pour le client">
                        <select class="g-select" name="doc_obligatoire[]" aria-label="Demandée"><option value="1"<?= $p['obligatoire'] ? ' selected' : '' ?>>Obligatoire</option><option value="0"<?= $p['obligatoire'] ? '' : ' selected' ?>>Facultative</option></select>
                        <select class="g-select" name="doc_fournisseur[]" aria-label="Fournie par">
                            <?php foreach (['etudiant' => 'L’étudiant', 'parent' => 'Le parent', 'garant' => 'Le garant'] as $cleF => $libF): ?><option value="<?= e($cleF) ?>"<?= $p['fournisseur'] === $cleF ? ' selected' : '' ?>><?= e($libF) ?></option><?php endforeach; ?>
                        </select>
                        <button class="g-btn g-btn-sm g-btn-warning" type="button" data-doc-remove>Retirer</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($brouillon): ?><div><button class="g-btn g-btn-sm" type="button" data-doc-add><?= gestion_icone('plus', 15) ?> Ajouter une pièce</button></div><?php endif; ?>
        </section>

        <section class="g-card is-neutre" aria-labelledby="etapes-titre">
            <div class="g-card-head"><h3 id="etapes-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('route', 18) ?></span>Étapes suivies par l’équipe et sources</h3></div>
            <label class="g-form-row">Étapes, une par ligne : « Titre | délai en jours » (délai facultatif)
                <textarea class="g-input" name="steps" rows="6"><?= e(old('steps', $etapesTexte)) ?></textarea>
            </label>
            <?= $champ('steps') ?>
            <label class="g-form-row">Sources officielles, une adresse par ligne (au moins une pour publier)
                <textarea class="g-input" name="sources" rows="3"><?= e(old('sources', $sourcesTexte)) ?></textarea>
            </label>
            <?= $champ('sources') ?>
        </section>

        <?php if ($brouillon): ?>
            <div><button class="g-btn g-btn-success" type="submit"><?= gestion_icone('check', 16) ?> <?= $modele === null ? 'Créer la démarche (brouillon)' : 'Enregistrer le brouillon' ?></button></div>
        <?php endif; ?>
    </fieldset>
</form>
<script>
    // Lignes de pièces : ajouter une ligne vide, retirer une ligne (au moins une reste)
    (function () {
        var rows = document.querySelector('[data-doc-rows]');
        if (!rows) { return; }
        document.querySelector('[data-doc-add]')?.addEventListener('click', function () {
            var all = rows.querySelectorAll('[data-doc-row]');
            var copy = all[all.length - 1].cloneNode(true);
            copy.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            copy.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
            rows.appendChild(copy);
            copy.querySelector('input').focus();
        });
        rows.addEventListener('click', function (event) {
            var bouton = event.target.closest('[data-doc-remove]');
            if (!bouton) { return; }
            var row = bouton.closest('[data-doc-row]');
            if (rows.querySelectorAll('[data-doc-row]').length > 1) { row.remove(); } else { row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
        });
    })();
</script>
