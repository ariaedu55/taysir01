<?php
/**
 * Page d'un service (admission, logement, visa…) avec le formulaire de demande.
 * Le formulaire est réservé aux comptes connectés ; les visiteurs sont invités à se connecter puis reviennent ici.
 *
 * @var array        $service    Entrée de config/services.php
 * @var list<array>  $services
 * @var array|null   $user
 * @var list<string> $countries
 * @var array        $civilities
 */
use App\Services\DocumentStorage;

$returnTo = '/' . $service['slug'] . '/#formulaire';
$motive = in_array($service['key'], ['billet', 'hotel'], true) ? 'voyage' : $service['key'];
?>
<main id="contenu" class="destinations-page service-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li>Nos services</li>
                    <li aria-current="page"><?= e($service['short']) ?></li>
                </ol>
            </nav>
            <h1 data-split><?= e($service['title']) ?></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms"><?= e($service['lead']) ?></p>
        </div>
    </section>

    <div class="wrap service-layout">
        <section class="service-main" id="formulaire" aria-labelledby="formulaire-titre">
            <?php if ($user === null): ?>
                <div class="service-gate glass" data-reveal="up">
                    <span class="service-gate-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                    <h2 id="formulaire-titre">Faites votre demande depuis votre espace</h2>
                    <p>Votre compte Taysir garde vos informations et vos documents en sécurité, et vous permet de suivre l’avancement de votre dossier, étape par étape.</p>
                    <div class="actions">
                        <a class="button primary" href="<?= e(url('/inscription/?retour=' . rawurlencode($returnTo))) ?>">Créer mon compte</a>
                        <a class="button light" href="<?= e(url('/connexion/?retour=' . rawurlencode($returnTo))) ?>">J’ai déjà un compte</a>
                    </div>
                    <p class="service-gate-alt">Vous préférez en parler d’abord ? <a href="<?= e(url('/rendez_vous_orientation/?motif=' . $motive)) ?>">Prenez rendez-vous</a>, sans compte.</p>
                </div>
            <?php else: ?>
                <div class="service-form-head" data-reveal="up">
                    <p class="kicker">Formulaire</p>
                    <h2 id="formulaire-titre">Votre demande</h2>
                    <p>Les champs sans mention sont obligatoires. Vos informations sont reprises de votre compte : vérifiez-les ou modifiez-les pour cette demande.</p>
                </div>
                <?php partial('flash'); ?>

                <form class="portal-form" method="post" action="<?= e(url('/' . $service['slug'] . '/')) ?>" enctype="multipart/form-data" novalidate data-portal-form>
                    <?= csrf_field() ?>

                    <fieldset class="form-section glass" data-reveal="up">
                        <legend><span>1</span> Vos coordonnées</legend>
                        <div class="field-grid">
                            <?php partial('form-field', ['name' => 'civility', 'label' => 'Civilité', 'type' => 'select', 'options' => $civilities, 'required' => false]); ?>
                            <?php partial('form-field', ['name' => 'birth_date', 'label' => 'Date de naissance', 'type' => 'date', 'max' => date('Y-m-d'), 'autocomplete' => 'bday']); ?>
                            <?php partial('form-field', ['name' => 'first_name', 'label' => 'Prénom', 'required' => true, 'autocomplete' => 'given-name', 'value' => $user['first_name']]); ?>
                            <?php partial('form-field', ['name' => 'last_name', 'label' => 'Nom', 'required' => true, 'autocomplete' => 'family-name', 'value' => $user['last_name']]); ?>
                            <?php partial('form-field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'value' => $user['email']]); ?>
                            <?php partial('form-field', ['name' => 'phone', 'label' => 'Téléphone (WhatsApp)', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+221 77 000 00 00', 'value' => (string) ($user['phone'] ?? '')]); ?>
                            <?php if ($service['identity']): ?>
                                <?php partial('form-field', ['name' => 'identity_number', 'label' => 'Numéro de passeport ou de CNI', 'hint' => 'Utile pour préparer le dossier ; vous pourrez aussi le donner à votre conseiller.']); ?>
                            <?php endif; ?>
                        </div>
                    </fieldset>

                    <fieldset class="form-section glass" data-reveal="up">
                        <legend><span>2</span> Votre projet</legend>
                        <div class="field-grid">
                            <?php foreach ($service['fields'] as [$name, $label, $type, $required, $extra]): ?>
                                <?php if ($type === 'country' && $coveredCountries !== []): ?>
                                    <?php // Phase 12 : pays couverts par l'agence, puis « Autre pays » avec saisie libre ?>
                                    <?php partial('form-field', ['name' => $name, 'label' => $label, 'type' => 'select', 'required' => $required,
                                        'options' => array_combine($coveredCountries, $coveredCountries) + ['__autre' => 'Autre pays…']]); ?>
                                    <div data-autre-pays hidden>
                                        <?php partial('form-field', ['name' => $name . '_autre', 'label' => 'Précisez le pays', 'required' => false, 'autocomplete' => 'country-name']); ?>
                                    </div>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <?php if ($type === 'purpose'): ?>
                                    <?php partial('form-field', ['name' => $name, 'label' => $label, 'type' => 'select', 'required' => $required, 'options' => $purposes]); ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <?php partial('form-field', [
                                    'name' => $name, 'label' => $label, 'type' => $type, 'required' => $required,
                                    'options' => $type === 'select' ? $extra : null,
                                    'hint' => $type !== 'select' ? $extra : null,
                                    'min' => match ($type) { 'date' => date('Y-m-d'), 'number' => 1, default => null },
                                    'max' => $type === 'number' ? 20 : null,
                                    'value' => $type === 'number' ? '1' : null,
                                ]); ?>
                            <?php endforeach; ?>
                            <?php partial('form-field', ['name' => 'message', 'label' => 'Précisions', 'type' => 'textarea', 'hint' => 'Votre situation, vos questions, vos contraintes de calendrier ou de budget.']); ?>
                        </div>
                        <datalist id="liste-pays">
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <?php // Phase 12 : pièces de la démarche correspondante, mises à jour selon le pays et le motif ?>
                        <div class="demarche-pieces" data-demarche-pieces data-url="<?= e(url('/demarches/pieces/')) ?>" data-service="<?= e($service['key']) ?>" aria-live="polite">
                            <?php partial('demarche-pieces', ['pieces' => $generalPieces, 'libelle' => null]); ?>
                        </div>
                    </fieldset>

                    <?php if ($service['documents']): ?>
                        <?php $documentError = errors()['documents'] ?? null; ?>
                        <fieldset class="form-section glass" data-reveal="up">
                            <legend><span>3</span> Vos documents</legend>
                            <div class="field<?= $documentError ? ' has-error' : '' ?>">
                                <label class="dropzone" for="champ-documents" data-dropzone>
                                    <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                                    <strong>Ajouter des documents <span class="field-optional">(facultatif)</span></strong>
                                    <span id="champ-documents-aide">Relevés de notes, diplômes, passeport… PDF, JPG ou PNG, <?= DocumentStorage::MAX_FILES ?> fichiers de 5 Mo au maximum. Vous pourrez en ajouter plus tard depuis votre espace.</span>
                                    <input id="champ-documents" name="documents[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                        data-max-files="<?= DocumentStorage::MAX_FILES ?>" data-max-size="<?= DocumentStorage::MAX_SIZE ?>"
                                        aria-describedby="champ-documents-aide<?= $documentError ? ' champ-documents-erreur' : '' ?>">
                                </label>
                                <ul class="dropzone-files" data-dropzone-files aria-live="polite"></ul>
                                <?php if ($documentError): ?>
                                    <p class="field-error" id="champ-documents-erreur"><?= e($documentError) ?></p>
                                <?php endif; ?>
                            </div>
                        </fieldset>
                    <?php endif; ?>

                    <div class="form-submit" data-reveal="up">
                        <p>En envoyant cette demande, vous acceptez que Taysir utilise ces informations pour traiter votre dossier.</p>
                        <button class="button primary" type="submit">Envoyer ma demande</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <aside class="service-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">Accompagnement</p>
                <h2>Comment ça se passe ?</h2>
                <ol class="orientation-steps">
                    <?php foreach ($service['steps'] as $i => $step): ?>
                        <li><span><?= $i + 1 ?></span><div><?= e($step) ?></div></li>
                    <?php endforeach; ?>
                </ol>
                <a class="button light" href="<?= e(url('/rendez_vous_orientation/?motif=' . $motive)) ?>">Prendre rendez-vous</a>
                <a class="button outline" href="<?= e(config('site.contact.whatsapp')) ?>">Écrire sur WhatsApp</a>
            </div>
            <?php if ($generalPieces !== []): ?>
                <?php // Phase 12 : une seule source, la démarche générale publiée du service ?>
                <div class="article-aside-card glass" data-reveal="right">
                    <p class="kicker">À préparer</p>
                    <h2>Documents habituellement demandés</h2>
                    <ul class="demarche-liste">
                        <?php foreach ($generalPieces as $p): ?>
                            <li><span class="demarche-coche" aria-hidden="true"></span><span><?= e($p['titre']) ?><?= $p['obligatoire'] ? '' : ' <em>(si possible)</em>' ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="demarche-note">La liste exacte dépend du pays et de votre situation : elle s’affiche dans le formulaire.</p>
                </div>
            <?php endif; ?>
            <nav class="article-aside-card glass service-others" aria-label="Nos autres services" data-reveal="right">
                <p class="kicker">Nos autres services</p>
                <ul>
                    <?php foreach ($services as $other): ?>
                        <?php if ($other['key'] !== $service['key']): ?>
                            <li><a href="<?= e(url('/' . $other['slug'] . '/')) ?>"><?= e($other['title']) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>
    </div>
</main>
