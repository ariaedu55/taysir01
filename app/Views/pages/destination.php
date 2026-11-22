<?php
/**
 * Fiche pays.
 *
 * @var array $destination Pays complet (voir App\Models\Destination::find)
 * @var array $others      Autres pays suggérés
 * @var array $guide       Repères éditoriaux
 */
use App\Models\Destination;

$d = $destination;
$image = Destination::guideImageUrl($d);
[$city, $lead, $sourceLabel, $source] = array_pad($guide, 4, null);
$sections = $d['sections'];
$whatsapp = config('site.contact.whatsapp');
$appointment = legacy_url('/rendez_vous_orientation/');
$admission = legacy_url('/demande-dadmission/');
$distance = Destination::distanceLabel($d);
[$originCode, $originCity] = Destination::ORIGIN;
?>
<main id="contenu" class="country">
    <?php partial('institution-hero', [
        'title' => 'Partir ' . $d['in'],
        'image' => $image,
        'portrait' => Destination::guideImageUrl($d, true),
        'context' => $d['region'],
        'lead' => $lead ?? 'Préparez votre départ avec un conseiller Taysir.',
        'trail' => [['Destinations', url('/destination/')]],
        'current' => $d['name'],
        'target' => 'presentation',
        'action' => 'Découvrir la destination',
        'caption' => $city ? $city . ' · Image d’illustration' : null,
    ]); ?>

    <?php if ($d['airport']): ?>
        <?php [$airportCode, $airportCity] = $d['airport']; ?>
        <div class="wrap country-flight">
            <dl class="country-flight-route" aria-label="Repères de voyage">
                <div><dt>Départ</dt><dd><?= e($originCity) ?> <small><?= e($originCode) ?></small></dd></div>
                <div class="country-flight-arrival">
                    <dt>Aéroport de référence</dt>
                    <dd><span class="country-flight-line" aria-hidden="true"><img src="<?= e(asset('images/journey/avion-route.webp')) ?>" alt="" width="24" height="24"></span><?= e($airportCity) ?> <small><?= e($airportCode) ?></small></dd>
                </div>
                <div class="country-flight-distance"><dt>À vol d’oiseau</dt><dd><?= e($distance) ?></dd></div>
            </dl>
            <a class="button primary" href="<?= e($appointment) ?>">Prendre rendez-vous</a>
        </div>
    <?php endif; ?>

    <?php partial('institution-profile', [
        'title' => 'Étudier ' . $d['in'],
        'image' => $d['image'] ? media_url($d['image']) : null,
        'intro' => $d['intro'] ?: '<p>Commencez par choisir une formation et vérifiez les conditions de candidature auprès de l’établissement. Un conseiller Taysir peut vous aider à comparer les parcours et à préparer votre dossier.</p>',
        'source' => $source,
        'sourceLabel' => $sourceLabel,
        'school' => false,
    ]); ?>

    <div class="wrap country-body">
        <?php if ($sections !== []): ?>
            <aside class="country-aside">
                <?php partial('institution-outline', ['sections' => $sections, 'prefix' => 'rubrique-']); ?>
                <div class="country-help">
                    <p><strong>Préparer votre dossier</strong> Un conseiller vous aide à choisir une formation et à réunir vos pièces.</p>
                    <div class="institution-help-links">
                        <a class="button primary" href="<?= e($admission) ?>">Demander une admission</a>
                        <a href="<?= e($whatsapp) ?>">Écrire sur WhatsApp</a>
                    </div>
                </div>
            </aside>
        <?php endif; ?>

        <div class="country-content">
            <?php if ($d['pending']): ?>
                <div class="country-notice glass">
                    <h2>Guide en cours de mise à jour</h2>
                    <p>Nos conseillers vous accompagnent dans le choix de l’établissement, l’admission, le visa et le logement.</p>
                    <div class="actions">
                        <a class="button primary" href="<?= e($admission) ?>">Demander une admission</a>
                        <a class="button light" href="<?= e($whatsapp) ?>">Écrire sur WhatsApp</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($sections !== []): ?>
                <h2 class="institution-section-title">Préparer votre départ</h2>
                <p class="country-guide-note">Vérifiez les conditions et les délais de vos démarches auprès des autorités compétentes avant de déposer votre dossier.</p>
                <?php partial('institution-topics', ['sections' => $sections, 'prefix' => 'rubrique-']); ?>
            <?php endif; ?>
        </div>
    </div>

    <?php partial('services', ['destination' => $d['in']]); ?>

    <section class="section country-others" aria-labelledby="autres-destinations">
        <div class="wrap">
            <div class="section-head">
                <h2 id="autres-destinations">Autres destinations</h2>
                <a href="<?= e(url('/destination/')) ?>">Toutes les destinations</a>
            </div>
            <?php partial('destination-cards', ['countries' => $others]); ?>
        </div>
    </section>

    <section class="wrap country-cta" aria-labelledby="pret" data-reveal="scale">
        <div>
            <h2 id="pret">Prêt à partir <?= e($d['in']) ?> ?</h2>
            <p>Parlons de votre projet : un premier rendez-vous permet de définir la formation, le budget et le calendrier.</p>
        </div>
        <div class="actions">
            <a class="button primary" href="<?= e($appointment) ?>">Prendre rendez-vous</a>
            <a class="button outline-light" href="<?= e($whatsapp) ?>">Écrire sur WhatsApp</a>
        </div>
    </section>
</main>
