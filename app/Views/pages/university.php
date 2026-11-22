<?php
/**
 * Fiche université.
 *
 * @var array $university Université complète (voir App\Models\University::find)
 * @var array $others     Autres universités suggérées
 */
use App\Models\University;

$u = $university;
$sections = $u['sections'];
[$visual, $focus, $lead, $source] = array_pad(config('institutions.' . $u['slug'], [
    'ucad', 'Université publique', 'Découvrez les formations de l’université et préparez votre admission.',
]), 4, null);
$links = University::links($u);
$at = University::at($u);
$whatsapp = config('site.contact.whatsapp');
$linkIcons = [
    'site' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z"/>',
    'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'facebook' => '<path d="M15 3h-2.5A3.5 3.5 0 0 0 9 6.5V10H6.5v3.5H9V21h3.5v-7.5H15l.5-3.5h-3V7a1 1 0 0 1 1-1H15Z"/>',
    'x' => '<path d="M4 4l16 16M20 4 4 20"/>',
    'youtube' => '<rect x="3" y="6" width="18" height="12" rx="3"/><path d="m10.5 9.5 4 2.5-4 2.5Z"/>',
    'instagram' => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.5"/>',
    'linkedin' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10v6M8 7.5v.01M12 16v-3.5a2 2 0 0 1 4 0V16M12 10v6"/>',
];
?>
<main id="contenu" class="country university institution-page">
    <?php partial('institution-hero', [
        'title' => $u['name'],
        'visual' => $visual,
        'context' => $u['acronym'] . ' · ' . $u['city'],
        'lead' => $lead,
        'trail' => [['Universités', url('/universites/')]],
        'current' => $u['acronym'],
        'target' => 'presentation',
        'action' => 'Découvrir l’université',
    ]); ?>

    <?php partial('institution-profile', [
        'title' => 'À propos de l’' . $u['acronym'],
        'focus' => $focus,
        'image' => University::imageUrl($u),
        'intro' => $u['intro'],
        'source' => $source,
        'former' => $u['former'],
        'school' => false,
    ]); ?>

    <div class="wrap country-body">
        <aside class="country-aside">
            <?php partial('institution-outline', ['sections' => $sections, 'prefix' => 'rubrique-']); ?>
            <?php if ($links !== []): ?>
                <div class="university-contacts glass" data-reveal="left" style="--delay: 120ms">
                    <p class="country-toc-title">Contacts de l’université</p>
                    <ul>
                        <?php foreach ($links as [$label, $href, $type]): ?>
                            <li>
                                <a href="<?= e($href) ?>"<?= $type === 'email' ? '' : ' rel="noopener" target="_blank"' ?>>
                                    <span class="university-contact-icon"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $linkIcons[$type] ?></svg></span>
                                    <span><?= e($label) ?><?php if ($type === 'email'): ?><small><?= e(substr($href, 7)) ?></small><?php endif; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="country-help">
                <p><strong>Une question sur l’admission ?</strong> Un conseiller vous répond du lundi au samedi.</p>
                <div class="institution-help-links">
                    <a class="button primary" href="<?= e(url('/demande-dadmission/')) ?>">Préparer mon admission</a>
                    <a href="<?= e($whatsapp) ?>">Écrire sur WhatsApp</a>
                </div>
            </div>
        </aside>

        <div class="country-content">
            <?php if ($sections !== []): ?>
                <h2 class="institution-section-title">Étudier à l’<?= e($u['acronym']) ?></h2>
                <?php partial('institution-topics', ['sections' => $sections, 'prefix' => 'rubrique-']); ?>
            <?php endif; ?>

            <p class="university-source">Informations reprises du site Taysir Orientation Voyage. Les dates et conditions d’admission évoluent chaque année : vérifiez-les sur le site officiel de l’université ou auprès de nos conseillers.</p>
        </div>
    </div>

    <?php partial('services', ['destination' => $at]); ?>

    <section class="section country-others" aria-labelledby="autres-universites">
        <div class="wrap">
            <div class="section-head">
                <h2 id="autres-universites">Autres universités</h2>
                <a href="<?= e(url('/universites/')) ?>">Toutes les universités</a>
            </div>
            <?php partial('university-cards', ['universities' => $others]); ?>
        </div>
    </section>

    <section class="wrap country-cta" aria-labelledby="pret" data-reveal="scale">
        <div>
            <h2 id="pret">Prêt à rejoindre <?= e('l’' . $u['acronym']) ?> ?</h2>
            <p>Créez votre espace pour préparer votre dossier, puis échangez avec un conseiller sur vos choix de filières.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
            <a class="button outline-light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
        </div>
    </section>
</main>
