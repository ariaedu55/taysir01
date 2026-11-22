<?php
/**
 * Accueil.
 *
 * @var array       $content      config/home.php
 * @var list<array> $destinations Pays du registre (App\Models\Destination::all)
 * @var list<array> $sectors      Secteurs d'avenir (config/sectors.php)
 * @var array<string, list<array>> $careers Fiches métiers par secteur
 */
use App\Models\Destination;
use App\Models\Sector;

$slides = $content['heroSlides'];
$cities = e(asset('images/destinations/'));
$panoramas = e(asset('images/destinations/hero/'));
$flightMedia = e(asset('images/hero-flight/'));
$contact = config('site.contact');
$arrow = '<span class="arrow" aria-hidden="true"><svg viewBox="0 0 16 16" width="14" height="14"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
$icon = static fn (string $paths): string => '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
$plane = '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>';
// Initiales d'un établissement sans logo : « Institut national du pétrole et du gaz » → « INP ».
$initials = static function (string $name): string {
    $words = preg_split('/\s+/', trim(preg_replace('/\b(de|du|des|et|la|le|l’)\b/iu', ' ', $name)));

    return strtoupper(implode('', array_map(static fn (string $word): string => $word[0], array_slice($words, 0, 3))));
};
$partnerRows = array_chunk($content['partners'], (int) ceil(count($content['partners']) / 2));
?>
<main id="contenu" class="home">

    <!-- Bandeau : diaporama des villes -->
    <section class="hero" data-hero aria-roledescription="carrousel" aria-label="Destinations à la une">
        <div class="hero-media" aria-hidden="true">
            <?php foreach ($slides as $index => [, , , $photo]): ?>
                <picture>
                    <source media="(max-aspect-ratio: 4/5)" srcset="<?= $panoramas . e($photo) ?>-portrait.webp">
                    <img class="hero-slide<?= $index === 0 ? ' is-current' : '' ?>" data-slide="<?= e($photo) ?>" src="<?= $panoramas . e($photo) ?>.webp" alt=""
                        <?= $index === 0 ? 'fetchpriority="high"' : 'fetchpriority="low" loading="lazy" decoding="async"' ?>>
                </picture>
            <?php endforeach; ?>
        </div>
        <div class="hero-veil" aria-hidden="true"></div>
        <svg class="hero-routes" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true" data-routes>
            <path id="route-a" class="route route-red" d="M-60 760C260 560 520 820 820 560S1260 250 1520 150"/>
            <path class="route route-white" d="M-60 860C380 720 700 880 1000 660S1340 430 1520 400"/>
            <path class="route route-faint" d="M-60 300C300 380 600 180 900 260S1300 120 1520 60"/>
            <g class="hero-route-jet">
                <image href="<?= e(asset('images/journey/avion-route.webp')) ?>" x="-30" y="-30" width="60" height="60" transform="rotate(90)"/>
                <animateMotion dur="16s" repeatCount="indefinite" rotate="auto"><mpath href="#route-a"/></animateMotion>
            </g>
        </svg>
        <!-- Survol (docs/accueil-survol.md) : brume, trois plans de nuages (une seule image), avion et lignes de vent. -->
        <div class="hero-flight" aria-hidden="true" data-hero-flight>
            <span class="flight-mist"></span>
            <div class="flight-layer flight-layer--back">
                <img class="flight-cloud is-left c1" src="<?= $flightMedia ?>nuages.webp" alt="" width="1152" height="576" decoding="async">
                <img class="flight-cloud is-right is-flipped c2" src="<?= $flightMedia ?>nuages.webp" alt="" width="1152" height="576" decoding="async">
            </div>
            <div class="flight-layer flight-layer--mid">
                <img class="flight-cloud is-left is-flipped c3" src="<?= $flightMedia ?>nuages.webp" alt="" width="1152" height="576" decoding="async">
                <img class="flight-cloud is-right c4" src="<?= $flightMedia ?>nuages.webp" alt="" width="1152" height="576" decoding="async">
                <img class="flight-cloud is-right is-flipped c5" src="<?= $flightMedia ?>nuages.webp" alt="" width="1152" height="576" decoding="async">
            </div>
            <div class="flight-plane">
                <div class="flight-body">
                    <span class="flight-trail"></span>
                    <img class="flight-jet" src="<?= $flightMedia ?>survol.webp" alt="" width="1080" height="540" decoding="async">
                </div>
            </div>
            <div class="flight-layer flight-layer--front">
                <img class="flight-cloud is-left c6" src="<?= $flightMedia ?>nuages.webp" alt="" width="1152" height="576" decoding="async">
                <img class="flight-cloud is-right is-flipped c7" src="<?= $flightMedia ?>nuages.webp" alt="" width="1152" height="576" decoding="async">
            </div>
            <span class="flight-streaks"><i style="--a: -8deg; --d: 0ms"></i><i style="--a: 22deg; --d: 140ms"></i><i style="--a: 158deg; --d: 60ms"></i><i style="--a: 196deg; --d: 220ms"></i><i style="--a: -150deg; --d: 300ms"></i><i style="--a: -32deg; --d: 380ms"></i><i style="--a: 64deg; --d: 260ms"></i><i style="--a: 118deg; --d: 420ms"></i><i style="--a: -110deg; --d: 180ms"></i><i style="--a: -66deg; --d: 460ms"></i></span>
        </div>

        <div class="wrap hero-inner">
            <div class="hero-copy">
                <h1 class="hero-title">
                    <span class="hero-title-line">Votre avenir s’écrit</span>
                    <span class="hero-title-place" data-typewriter-line>
                        <span class="typewriter" data-typewriter aria-hidden="true"><?= e($slides[0][6]) ?></span><span class="caret" aria-hidden="true"></span>
                        <span class="sr-only"><?= e(implode(', ', array_column($slides, 6))) ?>.</span>
                    </span>
                </h1>
                <div class="actions hero-actions">
                    <a class="button primary" data-magnetic href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous<?= $arrow ?></a>
                    <a class="button glass-button hero-ghost" href="#destinations">Explorer les destinations</a>
                </div>
            </div>
        </div>

        <div class="wrap hero-bottom">
            <div class="hero-dots" role="group" aria-label="Choisir une ville" hidden data-slide-dots>
                <?php foreach ($slides as $index => [$city, $country, , $asset]): ?>
                    <button class="hero-dot<?= $index === 0 ? ' is-current' : '' ?>" type="button" aria-controls="ville-<?= e($asset) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="<?= e($country) ?>">
                        <span class="hero-dot-label"><?= e($country) ?></span>
                        <span class="hero-dot-bar"><i></i></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <aside class="hero-card" aria-label="Ville à la une">
                <p class="hero-card-label">Destination à la une</p>
                <div class="hero-card-stack">
                    <?php foreach ($slides as $index => [$city, $country, $flag, $asset, $headline, $path, $place]): ?>
                        <article class="hero-card-slide<?= $index === 0 ? ' is-current' : '' ?>" id="ville-<?= e($asset) ?>" data-slide-info data-place="<?= e($place) ?>" <?= $index === 0 ? '' : 'inert aria-hidden="true"' ?>>
                            <h2 class="hero-card-name"><img src="<?= $cities . e($flag) ?>.webp" alt="" width="30" height="20"><?= e($country) ?></h2>
                            <p><?= e($headline) ?></p>
                            <a href="<?= e(legacy_url($path)) ?>">Découvrir la destination<?= $arrow ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
            <a class="hero-scroll" href="#parcours"><span>Découvrir</span><i aria-hidden="true"></i></a>
        </div>
        <p class="sr-only" aria-live="polite" data-slide-announcement></p>
    </section>

    <!-- Deux parcours : scène centrée, réversible au scroll -->
    <section class="section paths" id="parcours" aria-labelledby="parcours-titre">
        <div class="wrap">
            <div class="paths-head">
                <p class="kicker" data-reveal="up">Deux parcours</p>
                <h2 id="parcours-titre" data-reveal="up">Étudier ou voyager, <em>nous traçons la route.</em></h2>
                <p class="paths-intro" data-type-scroll>De la première question au premier jour sur le campus, nous guidons chaque élève, étudiant et voyageur vers <em>le bon choix, au bon endroit, au bon moment.</em></p>
            </div>

            <div class="paths-stage" data-reveal-toggle>
                <svg class="stage-route" viewBox="0 0 1200 600" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M40 420C220 560 420 560 600 470S980 300 1160 380" pathLength="400"/>
                </svg>

                <div class="stage-center">
                    <span class="stage-orbit stage-orbit-1" aria-hidden="true"></span>
                    <span class="stage-orbit stage-orbit-2" aria-hidden="true"><i></i></span>
                    <figure class="stage-person">
                        <img src="<?= e(media_url($content['intro'])) ?>" alt="Étudiant accompagné par Taysir" loading="lazy">
                    </figure>
                    <span class="stage-chip glass chip-1" style="--c: 0"><i></i>Orientation</span>
                    <span class="stage-chip glass chip-2" style="--c: 1"><i></i>Admission</span>
                    <span class="stage-chip glass chip-3" style="--c: 2"><i></i>Visa</span>
                    <span class="stage-chip glass chip-4" style="--c: 3"><i></i>Logement</span>
                    <span class="stage-plane" aria-hidden="true"><?= $icon($plane) ?></span>
                </div>

                <div class="stage-side stage-left">
                    <a class="stage-card glass" id="etudes" style="--s: 0" href="<?= e(legacy_url('/apres-le-bac/')) ?>">
                        <span class="path-num" aria-hidden="true">01</span>
                        <h3>Études et orientation</h3>
                        <p>Choisir sa filière, trouver l’établissement et réussir sa demande d’admission.</p>
                        <span class="path-tags"><span>Orientation</span><span>Choix de filière</span><span>Admission</span></span>
                        <span class="path-go" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="M4 12 12 4M6 4h6v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </a>
                    <p class="stage-badge glass" style="--s: 1">
                        <span class="stage-badge-icon"><?= $icon('<path d="M20 6 9 17l-5-5"/>') ?></span>
                        <span><strong>Un conseiller dédié</strong>de l’orientation jusqu’à l’arrivée</span>
                    </p>
                </div>

                <div class="stage-side stage-right">
                    <a class="stage-card glass" id="voyage" style="--s: 0" href="<?= e(legacy_url('/partir-a-letranger/')) ?>">
                        <span class="path-num" aria-hidden="true">02</span>
                        <h3>Voyage et démarches</h3>
                        <p>Un départ préparé sereinement, du dossier consulaire jusqu’au billet.</p>
                        <span class="path-tags"><span>Visa</span><span>Prise en charge</span><span>Logement</span><span>Billet d’avion</span></span>
                        <span class="path-go" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="M4 12 12 4M6 4h6v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </a>
                    <figure class="stage-photo" style="--s: 1">
                        <img src="<?= $panoramas ?>paris.webp" alt="" loading="lazy" style="object-position: 23% 50%">
                        <figcaption>Paris · France</figcaption>
                    </figure>
                </div>
            </div>
        </div>
    </section>

    <?php partial('services'); ?>

    <!-- Destinations : carrousel en profondeur (docs/accueil-destinations.md) -->
    <section class="destinations" id="destinations" aria-labelledby="destinations-titre">
        <div class="wrap destinations-head">
            <div>
                <p class="kicker" data-reveal="up">Destinations</p>
                <h2 id="destinations-titre" data-split>Le monde est votre campus.</h2>
            </div>
            <div class="destinations-aside" data-reveal="up" style="--delay: 250ms">
                <p><?= count($destinations) ?> pays où nous préparons vos études et votre départ.</p>
                <a class="button light" href="<?= e(url('/destination/')) ?>">Toutes les destinations<?= $arrow ?></a>
            </div>
        </div>

        <div class="coverflow" data-coverflow tabindex="0" role="region" aria-roledescription="carrousel" aria-label="Destinations : flèches gauche et droite pour changer de pays" data-reveal="up" style="--delay: 200ms">
            <ul class="coverflow-track">
                <?php foreach ($destinations as $i => $country): ?>
                    <li class="cover-item" data-cover-item aria-roledescription="diapositive" aria-label="<?= $i + 1 ?> sur <?= count($destinations) ?> : <?= e($country['name']) ?>">
                        <a class="dest-card" href="<?= e(url('/' . $country['slug'] . '/')) ?>" draggable="false">
                            <img src="<?= e(Destination::imageUrl($country, true)) ?>" alt="" loading="<?= $i < 3 || $i > count($destinations) - 3 ? 'eager' : 'lazy' ?>" decoding="async" draggable="false">
                            <span class="dest-info">
                                <span class="dest-region"><?= e($country['region']) ?></span>
                                <span class="dest-name"><?= e($country['name']) ?></span>
                                <span class="dest-go" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="M4 12 12 4M6 4h6v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="coverflow-controls" data-reveal="up" style="--delay: 300ms">
            <button class="cover-btn" type="button" data-cover-step="-1" aria-label="Destination précédente"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <button class="cover-btn" type="button" data-cover-step="1" aria-label="Destination suivante"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="m6 3 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </div>
        <p class="sr-only" aria-live="polite" data-cover-announce></p>
    </section>

    <!-- Secteurs d’avenir : panneaux photo qui s’ouvrent (docs/accueil-secteurs.md) -->
    <?php
    $careerTotal = array_sum(array_map('count', $careers));
    // « Expert immobilier (Real Estate Appraiser) : » → « Expert immobilier »
    $jobName = static fn (string $title): string => trim(preg_replace('/\s*(\([^)]*\))?\s*:?\s*$/u', '', $title));
    ?>
    <section class="section sectors" id="secteurs" aria-labelledby="secteurs-titre">
        <div class="wrap">
            <div class="section-head">
                <div>
                    <p class="kicker" data-reveal="up">Secteurs d’avenir</p>
                    <h2 id="secteurs-titre" data-split>Choisissez un secteur qui recrute.</h2>
                    <p class="sectors-stats" data-reveal="up" style="--delay: 200ms"><strong><?= count($sectors) ?></strong> secteurs <span aria-hidden="true">·</span> <strong><?= $careerTotal ?></strong> fiches métiers à explorer</p>
                </div>
                <a class="button light" data-reveal="up" href="<?= e(legacy_url('/quel-secteur/')) ?>">Trouver mon secteur<?= $arrow ?></a>
            </div>

            <div class="sector-panels" data-sector-panels data-stagger>
                <?php foreach ($sectors as $i => $sector):
                    $jobs = $careers[$sector['key']] ?? [];
                    $open = $i === 0;
                    $id = 'secteur-' . $sector['key']; ?>
                    <article class="sector-panel<?= $open ? ' is-open' : '' ?>" data-sector-panel data-reveal="up">
                        <img class="sector-photo" src="<?= e(media_url($sector['image'])) ?>" alt="" loading="lazy" decoding="async">
                        <button class="sector-toggle" type="button" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $id ?>">
                            <span class="sector-icon"><?= Sector::icon($sector['key'], 22) ?></span>
                            <span class="sector-label"><?= e($sector['name']) ?></span>
                        </button>
                        <?php if ($jobs): ?>
                            <p class="sector-count"><strong><?= count($jobs) ?></strong> fiches métiers</p>
                        <?php endif; ?>
                        <div class="sector-body" id="<?= $id ?>"<?= $open ? '' : ' inert' ?>>
                            <h3 class="sector-name"><?= e($sector['name']) ?></h3>
                            <p class="sector-text"><?= e($sector['description']) ?></p>
                            <ul class="sector-jobs" aria-label="Exemples de métiers">
                                <?php foreach (array_slice($jobs, 0, 3) as $job): ?>
                                    <li><a href="<?= e(url('/' . $job['slug'] . '/')) ?>"><?= e($jobName($job['title'])) ?></a></li>
                                <?php endforeach; ?>
                                <li><a class="sector-more" href="<?= e(url('/' . $sector['slug'] . '/')) ?>">Explorer le secteur<?= $arrow ?></a></li>
                            </ul>
                        </div>
                        <span class="sector-progress" aria-hidden="true"></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Témoignages -->
    <section class="section stories" id="temoignages" aria-labelledby="temoignages-titre">
        <div class="wrap section-head">
            <div>
                <p class="kicker" data-reveal="up">Témoignages</p>
                <h2 id="temoignages-titre" data-split>Ils ont franchi le cap.</h2>
                <p data-reveal="up">Les parcours d’étudiants accompagnés par Taysir, au Sénégal et à l’étranger.</p>
            </div>
            <div class="actions" data-reveal="up">
                <a class="button light" href="<?= e(legacy_url('/temoignage/')) ?>">Tous les témoignages</a>
                <a class="button primary" href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Écrire mon histoire<?= $arrow ?></a>
            </div>
        </div>
        <div class="marquee" data-reveal="fade">
            <div class="marquee-track">
                <?php foreach ([false, true] as $copy): ?>
                    <?php foreach ($content['stories'] as $image): ?>
                        <figure class="story"<?= $copy ? ' aria-hidden="true"' : '' ?>>
                            <img src="<?= e(media_url($image)) ?>" alt="<?= $copy ? '' : 'Témoignage d’un étudiant accompagné par Taysir' ?>" loading="lazy" decoding="async">
                        </figure>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Écoles et partenaires -->
    <section class="section network" aria-labelledby="reseau-titre">
        <div class="wrap">
            <div class="section-head">
                <div>
                    <p class="kicker" data-reveal="up">Notre réseau</p>
                    <h2 id="reseau-titre" data-split>Des écoles et des partenaires de confiance.</h2>
                </div>
                <a class="button light" data-reveal="up" href="<?= e(legacy_url('/journal-ecoles/')) ?>">Découvrir les écoles<?= $arrow ?></a>
            </div>
            <ul class="schools" data-stagger>
                <?php foreach ($content['schools'] as [$name, $path, $image]): ?>
                    <li data-reveal="scale">
                        <a class="school glass" data-glow href="<?= e(legacy_url($path)) ?>">
                            <?php if ($image): ?>
                                <img src="<?= e(media_url($image)) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="school-initials" aria-hidden="true"><?= e($initials($name)) ?></span>
                            <?php endif; ?>
                            <span><?= e($name) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="logo-marquee" aria-label="Nos partenaires" role="region" data-reveal="fade">
            <?php foreach ($partnerRows as $row => $partners): ?>
                <div class="logo-row<?= $row === 1 ? ' is-reverse' : '' ?>">
                    <ul class="logo-track">
                        <?php foreach ([false, true] as $copy): ?>
                            <?php foreach ($partners as [$name, $image]): ?>
                                <li<?= $copy ? ' aria-hidden="true"' : '' ?>><img src="<?= e(media_url($image)) ?>" alt="<?= $copy ? '' : e($name) ?>" loading="lazy" decoding="async"></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="wrap">
            <a class="review glass" data-reveal="up" href="https://www.kiyoh.com/retrieve-widget.html?allowTransparency=false&amp;button=true&amp;color=white&amp;lang=fr&amp;locationId=1078227&amp;tenantId=98">
                <img src="<?= e(media_url($content['review'])) ?>" alt="" loading="lazy" width="64" height="64">
                <span><strong>Les avis de nos clients</strong> Consulter les avis vérifiés sur Kiyoh</span>
                <?= $arrow ?>
            </a>
        </div>
    </section>

    <!-- Appel final -->
    <section class="finale" aria-labelledby="finale-titre">
        <div class="finale-orbits" aria-hidden="true">
            <span class="orbit orbit-1"><i></i></span>
            <span class="orbit orbit-2"><i></i></span>
            <span class="orbit orbit-3"></span>
        </div>
        <div class="wrap finale-inner">
            <p class="kicker" data-reveal="up">Prochain départ</p>
            <h2 id="finale-titre" data-split>Prêt à écrire la suite de <em>votre histoire ?</em></h2>
            <p class="finale-lead" data-reveal="up" style="--delay: 300ms">Un premier rendez-vous suffit pour définir votre projet, votre budget et votre calendrier.</p>
            <div class="actions finale-actions" data-reveal="up" style="--delay: 450ms">
                <a class="button primary" data-magnetic href="<?= e(legacy_url('/rendez_vous_orientation/')) ?>">Prendre rendez-vous<?= $arrow ?></a>
                <a class="button glass-button" href="<?= e($contact['whatsapp']) ?>">Écrire sur WhatsApp</a>
            </div>
            <div class="finale-cards" data-stagger>
                <article class="glass" data-glow data-reveal="up">
                    <h3>L’équipe est à votre écoute</h3>
                    <p>Du lundi au vendredi de 9 h à 18 h, le samedi de 9 h à 14 h.</p>
                    <a href="tel:<?= e(str_replace(' ', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a>
                </article>
                <article class="glass" data-glow data-reveal="up">
                    <h3>La newsletter Taysir</h3>
                    <p>Bourses, salons et nouvelles des études au Sénégal et à l’étranger.</p>
                    <a href="<?= e(legacy_url('/#:~:text=Abonnez-vous%20au%20courriel')) ?>">S’inscrire</a>
                </article>
                <article class="glass" data-glow data-reveal="up">
                    <h3>Nous rendre visite</h3>
                    <p><?= e($contact['address']) ?></p>
                    <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>
                </article>
            </div>
        </div>
    </section>
</main>
