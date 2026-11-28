<?php
/**
 * Fiche personne (/gestion/personnes/{id}/), étapes 2A et 2C (docs/gestion-phase-2.md) : identité, dossiers,
 * rendez-vous, comptes rendus et notes (2C), ouverture d’un dossier (2C).
 *
 * @var array       $personne
 * @var list<array> $dossiers   Dossiers de la personne DANS le périmètre du compte
 * @var list<array> $rendezVous
 * @var list<array> $notes, $modeles, $conseillers
 * @var bool        $peutEcrire, $peutOuvrir
 * @var string      $jetonOuverture
 */
use App\Models\Appointment;
use App\Models\Person;
use App\Models\ServiceRequest;

$p = $personne;
$services = array_column(config('services'), 'title', 'key');
$age = $p['birth_date'] ? (new DateTimeImmutable($p['birth_date']))->diff(new DateTimeImmutable('today'))->y : null;
$sous = array_filter([
    Person::PROFILS[$p['profile'] ?? ''] ?? null,
    $age !== null ? $age . ' ans' : null,
    $p['agency_name'] ?? null,
    'fuseau ' . $p['timezone'],
]);
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url('/gestion/personnes/')) ?>"><?= gestion_icone('collapse', 15) ?> Personnes</a></p>

<header class="g-case-head">
    <div>
        <div class="g-case-title">
            <h2><?= e($p['first_name'] . ' ' . $p['last_name']) ?></h2>
            <?= $p['account_id'] ? g_badge('Compte client', 'green') : g_badge('Sans compte', 'grey') ?>
            <?= g_badge('Origine : ' . (Person::ORIGINES[$p['origin']] ?? $p['origin']), 'grey') ?>
            <?php if ($p['to_reconcile']): ?><?= g_badge('À rapprocher', 'amber') ?><?php endif; ?>
            <?php if (!empty($p['erased_at'])): ?><?= g_badge('Anonymisée', 'grey') ?><?php endif; ?>
        </div>
        <p class="g-case-sub"><?= e(implode(' · ', $sous)) ?></p>
        <div class="g-owners">
            <?php if ($me['role'] === 'admin' && $p['account_id']): ?><span class="g-owner"><span class="g-avatar is-sm is-rdv" aria-hidden="true"><?= gestion_icone('users', 12) ?></span><span><small>Identifiant du compte</small><?= e($p['account_id']) ?></span></span><?php endif; ?>
            <?php if ($p['phone']): ?><span class="g-owner"><span class="g-avatar is-sm is-rdv" aria-hidden="true"><?= gestion_icone('phone', 12) ?></span><span><small>Téléphone</small><?= e($p['phone']) ?></span></span><?php endif; ?>
            <?php if ($p['email']): ?><span class="g-owner"><span class="g-avatar is-sm is-rdv" aria-hidden="true"><?= gestion_icone('send', 12) ?></span><span><small>E-mail</small><?= e($p['email']) ?></span></span><?php endif; ?>
            <?php if ($p['created_by_name']): ?><span class="g-owner is-empty"><span class="g-avatar is-sm" aria-hidden="true"><?= gestion_icone('users', 12) ?></span><span><small>Fiche créée par</small><?= e($p['created_by_name']) ?> · <?= e(g_date($p['created_at'])) ?></span></span><?php endif; ?>
        </div>
    </div>
    <?php if ($p['phone'] || $p['email']): ?>
        <div class="g-case-actions">
            <?php if ($p['phone']): ?>
                <a class="g-btn" href="tel:<?= e(str_replace(' ', '', $p['phone'])) ?>"><?= gestion_icone('phone', 16) ?> Appeler</a>
                <a class="g-btn" href="https://wa.me/<?= e(preg_replace('/\D/', '', $p['phone'])) ?>"><?= gestion_icone('chat', 16) ?> WhatsApp</a>
            <?php endif; ?>
            <?php if ($p['email']): ?><a class="g-btn" href="mailto:<?= e($p['email']) ?>"><?= gestion_icone('send', 16) ?> E-mail</a><?php endif; ?>
        </div>
    <?php endif; ?>
</header>
<div class="g-case-tools">
    <a class="g-btn g-btn-primary g-btn-sm" href="<?= e(url('/gestion/personnes/' . $personne['id'] . '/message/')) ?>"><?= gestion_icone('chat', 15) ?> Envoyer un message</a>
<?php if (\App\Services\Access::peut($me, 'donnees-personnelles')): ?>
    <a class="g-btn g-btn-sm" href="<?= e(url('/gestion/personnes/' . $personne['id'] . '/donnees/')) ?>"><?= gestion_icone('lock', 15) ?> Données personnelles</a>
<?php endif; ?>
<?php partial('gestion-task-form', ['me' => $me, 'personId' => $personne['id']]); ?>
</div>

<?php if ($p['to_reconcile']): ?>
    <p class="g-notice"><?= gestion_icone('alert', 16) ?> Cette adresse e-mail est déjà connue d’une autre fiche. Aucun rattachement automatique : le rapprochement des doublons arrive en phase 4.</p>
<?php endif; ?>

<div class="g-grid g-grid-2">
    <section class="g-card is-ok" aria-labelledby="dossiers-titre">
        <div class="g-card-head"><h3 id="dossiers-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('folder', 18) ?></span>Dossiers <span class="g-count"><?= count($dossiers) ?></span></h3></div>
        <?php if ($dossiers === []): ?>
            <p class="g-empty">Aucun dossier dans votre périmètre.</p>
        <?php else: ?>
            <ul class="g-docs">
                <?php foreach ($dossiers as $d): ?>
                    <li class="g-doc">
                        <span class="g-doc-icon" aria-hidden="true"><?= gestion_icone('folder', 17) ?></span>
                        <div><strong><?= e($services[$d['service']] ?? $d['service']) ?><?= $d['destination'] ? ' · ' . e($d['destination']) : '' ?></strong>
                            <p><?= e($d['reference']) ?> · ouvert <?= e(g_date($d['created_at'])) ?> <?= g_badge(ServiceRequest::statusLabel($d['status'], true), ServiceRequest::statusTone($d['status'])) ?></p></div>
                        <div class="g-doc-actions"><a class="g-btn g-btn-sm g-btn-primary" href="<?= e(url('/gestion/dossiers/' . $d['reference'] . '/')) ?>">Ouvrir</a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($peutVoirRendezVous): ?>
    <section class="g-card is-rdv" aria-labelledby="rdv-titre">
        <div class="g-card-head"><h3 id="rdv-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('calendar', 18) ?></span>Rendez-vous <span class="g-count"><?= count($rendezVous) ?></span></h3></div>
        <?php if ($rendezVous === []): ?>
            <p class="g-empty">Aucun rendez-vous.</p>
        <?php else: ?>
            <ul class="g-agenda">
                <?php foreach ($rendezVous as $r): ?>
                    <?php $heureLocale = $r['scheduled_at'] ? Appointment::local($r['scheduled_at'], Appointment::zone($r['agency_id'] ? (int) $r['agency_id'] : null), 'Y-m-d H:i:s') : null; ?>
                    <?php $quand = $heureLocale ? g_date($heureLocale, true) : g_date($r['preferred_date']) . ' · ' . (Appointment::SLOTS[$r['preferred_slot']] ?? ''); ?>
                    <li><time><?= e($heureLocale ? substr($heureLocale, 11, 5) : '—') ?></time>
                        <div><strong><?= e(Appointment::MOTIVES[$r['motive']] ?? $r['motive']) ?> <?= g_badge(Appointment::statusLabel($r['status']), Appointment::statusTone($r['status'])) ?></strong>
                            <span><?= e($quand) ?> · <?= e(Appointment::MODES[$r['mode']] ?? $r['mode']) ?> · <?= e($r['reference']) ?></span></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>

<?php
/*
 * 2C : notes et comptes rendus (lus par Access::notesVisiblesSql), ouverture d'un dossier.
 * Choix interne ou partagé OBLIGATOIRE, sans valeur par défaut (E1) ; aucune modification ni suppression (N4).
 */
$errors = errors();
$postes = App\Services\Access::POSTES;
// Phase 12 : pays couverts et motifs par service pour l'ouverture d'un dossier
$paysCouverts = App\Services\Demarches::pays();
$motifsParService = array_filter(array_column(config('services'), 'purposes', 'key'));
?>
<div class="g-grid g-grid-2 g-fiche-2c">
    <section class="g-card is-ori" aria-labelledby="notes-titre" id="notes">
        <div class="g-card-head"><h3 id="notes-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('chat', 18) ?></span>Comptes rendus et notes <span class="g-count"><?= count($notes) ?></span></h3></div>
        <?php if ($peutEcrire): ?>
            <form class="g-note-form" method="post" action="<?= e(url('/gestion/personnes/' . $p['id'] . '/notes/')) ?>" novalidate>
                <?= csrf_field() ?>
                <label class="g-form-row">Nouvelle note
                    <textarea class="g-input" name="body" rows="3" maxlength="<?= App\Models\Note::LONGUEUR_MAX ?>" placeholder="Ce qui a été dit, décidé, la prochaine étape…"><?= e(old('body')) ?></textarea>
                </label>
                <fieldset class="g-choice">
                    <legend>Qui peut lire cette note ? <span class="g-muted">(choix obligatoire)</span></legend>
                    <label class="g-choice-card is-ori"><input type="radio" name="visibility" value="interne"<?= old('visibility') === 'interne' ? ' checked' : '' ?>><span><strong><?= gestion_icone('lock', 14) ?> Interne</strong><small>L’équipe qui suit la personne. Jamais la personne, ni l’accueil, ni la comptabilité.</small></span></label>
                    <label class="g-choice-card is-ok"><input type="radio" name="visibility" value="partagee"<?= old('visibility') === 'partagee' ? ' checked' : '' ?>><span><strong><?= gestion_icone('send', 14) ?> Partagée</strong><small>Visible dans l’espace de la personne ; un e-mail l’en avertit, sans le texte.</small></span></label>
                </fieldset>
                <?php if (isset($errors['note'])): ?><p class="g-field-error"><?= e($errors['note']) ?></p><?php endif; ?>
                <button class="g-btn g-btn-success" type="submit"><?= gestion_icone('check', 16) ?> Enregistrer la note</button>
                <p class="g-muted g-form-note">Une note ne se modifie pas : pour corriger, ajoutez une nouvelle note.</p>
            </form>
        <?php endif; ?>
        <?php if ($notes === []): ?>
            <p class="g-empty">Aucune note visible pour vous.</p>
        <?php else: ?>
            <ul class="g-notes">
                <?php foreach ($notes as $n): ?>
                    <li class="g-note is-<?= e($n['visibility']) ?>">
                        <header>
                            <strong><?= e($n['author_first_name'] . ' ' . $n['author_last_name']) ?></strong>
                            <small><?= e($postes[$n['author_poste']] ?? $n['author_poste']) ?> · <?= e(g_date($n['created_at'], true)) ?></small>
                            <?= $n['visibility'] === 'interne' ? g_badge('Interne', 'violet') : g_badge('Partagée', 'green') ?>
                            <?php if ($n['appointment_reference']): ?><?= g_badge('Compte rendu ' . $n['appointment_reference'], 'blue') ?><?php endif; ?>
                        </header>
                        <p><?= nl2br(e($n['body'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($peutOuvrir): ?>
        <section class="g-card is-pro" aria-labelledby="ouvrir-titre" id="ouvrir">
            <div class="g-card-head"><h3 id="ouvrir-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('plus', 18) ?></span>Ouvrir un dossier</h3></div>
            <form method="post" action="<?= e(url('/gestion/personnes/' . $p['id'] . '/dossier/')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="open_token" value="<?= e($jetonOuverture) ?>">
                <div class="g-form-grid g-form-2">
                    <label class="g-form-row">Service
                        <select class="g-select" name="service" required>
                            <?php foreach ($services as $cle => $titre): ?><option value="<?= e($cle) ?>"<?= old('service', 'admission') === $cle ? ' selected' : '' ?>><?= e($titre) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <?php // Phase 12 : pays couverts + « Autre pays », motif selon le service ; la démarche suit ces trois choix ?>
                    <label class="g-form-row">Destination
                        <select class="g-select" name="destination">
                            <option value="">Non précisée</option>
                            <?php foreach ($paysCouverts as $nomPays): ?><option value="<?= e($nomPays) ?>"<?= old('destination') === $nomPays ? ' selected' : '' ?>><?= e($nomPays) ?></option><?php endforeach; ?>
                            <option value="__autre"<?= old('destination') === '__autre' ? ' selected' : '' ?>>Autre pays…</option>
                        </select>
                    </label>
                    <label class="g-form-row">Autre pays <span class="g-muted">(si « Autre pays »)</span><input class="g-input" name="destination_autre" value="<?= e(old('destination_autre')) ?>" maxlength="120"></label>
                    <label class="g-form-row">Motif
                        <select class="g-select" name="purpose">
                            <option value="">Sans motif particulier</option>
                            <?php foreach ($motifsParService as $cleService => $motifsService): ?>
                                <optgroup label="<?= e($services[$cleService] ?? $cleService) ?>">
                                    <?php foreach ($motifsService as $cleMotif => $libelleMotif): ?><option value="<?= e($cleMotif) ?>"<?= old('purpose') === $cleMotif ? ' selected' : '' ?>><?= e($libelleMotif) ?></option><?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="g-form-row">Rentrée visée <input class="g-input" name="intake" value="<?= e(old('intake')) ?>" maxlength="40" placeholder="Septembre 2027"></label>
                    <?php if ($conseillers !== []): ?>
                        <label class="g-form-row">Conseiller
                            <select class="g-select" name="conseiller_id">
                                <option value="">File des conseillers</option>
                                <?php foreach ($conseillers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                </div>
                <fieldset class="g-choice">
                    <legend>Étapes et pièces à fournir</legend>
                    <label class="g-choice-card is-ok"><input type="radio" name="template_id" value="auto" checked><span><strong>Démarche automatique (recommandé)</strong><small>La démarche publiée qui correspond au service, au pays et au motif fixe les étapes et les pièces attendues.</small></span></label>
                    <label class="g-choice-card is-neutre"><input type="radio" name="template_id" value=""><span><strong>Sans démarche</strong><small>Le dossier s’ouvre sans étapes ni pièces ; vous les demanderez une par une.</small></span></label>
                </fieldset>
                <?php if (isset($errors['ouvrir'])): ?><p class="g-field-error"><?= e($errors['ouvrir']) ?></p><?php endif; ?>
                <button class="g-btn g-btn-primary" type="submit"><?= gestion_icone('folder', 16) ?> Ouvrir le dossier</button>
                <p class="g-muted g-form-note"><?= $conseillers === [] ? 'Le dossier vous sera affecté.' : 'Sans conseiller choisi, le dossier va dans la file des conseillers.' ?></p>
            </form>
        </section>
    <?php endif; ?>
</div>
