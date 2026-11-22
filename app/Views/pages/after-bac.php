<?php
/** Page d'aiguillage « Après le bac ». */
use App\Models\Sector;

$icon = static fn (string $paths): string => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
$paths = [
    ['Universités publiques', 'Les 8 universités du Sénégal : facultés, admissions et contacts.', url('/universites/'), '<path d="M3 10 12 5l9 5-9 5-9-5Z"/><path d="M7 12v4c0 1.2 2.2 2.5 5 2.5s5-1.3 5-2.5v-4"/>'],
    ['Écoles professionnelles', 'BTS, licences et masters professionnels, souvent en alternance.', url('/formations-profesionnelles/'), '<path d="M4 20V8l8-4 8 4v12"/><path d="M9 20v-6h6v6M4 20h16"/>'],
    ['Fiches métiers', 'Missions, compétences et formations de 39 métiers d’avenir.', url('/metiers/'), '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>'],
    ['Quelle filière ?', 'Choisir une formation adaptée à votre profil et à vos résultats.', url('/quelle-filiere/'), '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5Z"/>'],
    ['Étudier à l’étranger', 'Canada, France, Maroc, Turquie… : 12 destinations accompagnées.', url('/destination/'), '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z"/>'],
    ['Bilan d’orientation', 'Un rendez-vous avec un conseiller pour construire votre projet.', legacy_url('/rendez_vous_orientation/'), '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>'],
];
?>
<main id="contenu" class="destinations-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li aria-current="page">Après le bac</li>
                </ol>
            </nav>
            <h1 data-split>Après le bac, <span class="accent">quelle suite ?</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">Université, école professionnelle ou études à l’étranger : explorez les pistes, puis construisez votre projet avec un conseiller Taysir.</p>
        </div>
    </section>

    <section class="wrap destinations-list" aria-label="Pistes après le bac">
        <ul class="hub-cards" data-stagger>
            <?php foreach ($paths as [$name, $text, $href, $paths_]): ?>
                <li data-reveal="up">
                    <a class="hub-card glass" data-glow href="<?= e($href) ?>">
                        <span class="hub-card-icon"><?= $icon($paths_) ?></span>
                        <strong><?= e($name) ?></strong>
                        <span><?= e($text) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wrap sector-switch" aria-labelledby="secteurs-avenir">
        <p class="kicker" id="secteurs-avenir">Secteurs d’avenir</p>
        <ul>
            <?php foreach (Sector::all() as $sector): ?>
                <li><a class="chip sector-link" href="<?= e(url('/' . $sector['slug'] . '/')) ?>"><?= Sector::icon($sector['key'], 16) ?><?= e($sector['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wrap country-cta" aria-labelledby="projet" data-reveal="scale">
        <div>
            <h2 id="projet">Construisons votre projet ensemble</h2>
            <p>Créez votre espace Taysir pour suivre vos démarches, ou prenez rendez-vous pour un premier bilan.</p>
        </div>
        <div class="actions">
            <a class="button light" href="<?= e(url('/inscription/')) ?>">Créer mon compte</a>
            <a class="button outline-light" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous</a>
        </div>
    </section>
</main>
