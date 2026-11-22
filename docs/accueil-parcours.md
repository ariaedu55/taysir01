# Accueil : section « Votre parcours, étape par étape »

Demande du 20 novembre 2026 : rendre cette section plus moderne, élégante et futuriste, évoquer un voyage très dynamique, animer l'apparition et la disparition de chaque élément au scroll. À respecter : [charte-claire.md](charte-claire.md) (aucun dégradé, aucun fond bleu sauf boutons) et [polices.md](polices.md).

## Avant

Titre et bouton à gauche (collants), 6 étapes à droite le long d'une ligne verticale, cartes blanches simples. Script : une étape devient active quand elle passe aux 62 % de l'écran.

## Concept : un plan de vol

- **Tableau de bord collant** (`.journey-hud`), qui accompagne la lecture sous l'en-tête :
  - départ « DKR · Dakar », arrivée « Votre campus » ;
  - une piste en pointillés qui se remplit en rouge et un avion qui avance avec le scroll ;
  - un compteur « Étape 03 / 06 » qui bascule à chaque étape.
- **Route centrale** : piste en pointillés, trait rouge qui se dessine au scroll, avion qui descend le long de la route.
- **Les 6 étapes** (`config/site.php`, `services`) deviennent des **cartes d'embarquement** placées en zigzag de part et d'autre de la route. Chaque carte comporte :
  - un en-tête « Étape 01 » avec l'icône du service ;
  - le titre et le texte ;
  - une ligne perforée avec des encoches ;
  - un talon avec un code-barres et « En savoir plus ».
- **Station** sur la route pour chaque étape : un numéro dans un cercle. Quand l'avion la dépasse, le cercle devient rouge avec une onde, la carte se soulève et un tampon « Étape validée » s'y imprime (bleu nuit, pas de texte rouge).
- **Décor** : quadrillage de points très léger (motif SVG, pas de dégradé), 3 nuages blancs qui dérivent lentement avec un léger décalage au scroll.

## Animations (toutes réversibles)

- **Cartes** : elles arrivent de leur côté avec une rotation, une réduction et un flou, puis repartent quand elles sortent de l'écran.
- **Stations** : elles apparaissent en rebond.
- **Titre** : mot à mot, comme ailleurs. Tableau de bord et bouton apparaissent en fondu vers le haut.
- **Route, avions et compteur** : ils suivent la position du scroll dans les deux sens.
- **Mouvement réduit** : aucun déplacement, toutes les étapes sont affichées comme validées.

## Mobile

Route à gauche, cartes à droite, arrivée depuis la droite ; tableau de bord compact (codes seulement).

## Plan

1. `home.php` : nouvelle structure, icônes des 6 services, code-barres généré.
2. `home.css` : section, tableau de bord, route, stations, cartes, tampon, nuages, animations, mobile.
3. `home.js` : étapes actives, compteur, progression partagée avec le tableau de bord.
4. Vérifications : bureau et mobile, aller-retour au scroll, mouvement réduit, absence de dégradé et de fond bleu.

## Résultat (20 novembre 2026)

Fichiers :
- `app/Views/home.php` : nouvelle section (icônes des 6 services, code-barres généré à partir du nom du service, nuages SVG) ;
- `public/assets/css/home.css` : bloc « plan de vol » qui remplace l'ancien, avec ses règles mobiles ;
- `public/assets/js/home.js` : étapes validées, compteur, progression `--jp`.

Le titre a maintenant un mot d'accent manuscrit (« étape par étape »). Le bouton « Parler à un conseiller » est passé en bas de section, sous une phrase « Prêt à décoller ? ».

Vérifications :

- **Bureau (1280 px)** :
  - tableau de bord collant, avion et trait rouge synchronisés ;
  - compteur de 01 à 06 ;
  - tampon « Étape validée » sur les étapes dépassées ;
  - cartes en zigzag, connecteurs rouges sur les étapes actives.
- **Aller-retour au scroll** : en remontant, la progression, les étapes actives, le compteur et l'affichage des cartes reviennent en arrière jusqu'à zéro.
- **Mobile (375 px)** : route à gauche, cartes à droite, tableau de bord compact, aucun débordement.
- **Défaut corrigé pendant les tests** : les stations partaient de `scale: 0`. Un élément de taille nulle n'est jamais vu par l'IntersectionObserver, donc elles ne s'affichaient pas. Elles partent maintenant de `scale: .3`.
- **Mouvement réduit** (prévu dans le CSS et le script, pas testé en émulation) : nuages et ondes arrêtés, stations à taille normale, toutes les étapes validées.
- **Charte** : aucun `gradient(` hors des masques des logos, aucun fond bleu ; les 301 règles de `home.css` sont lues par le navigateur.

## Plan de la refonte des six escales

Donner à chaque démarche une composition inspirée d’un objet du voyage ou du dossier étudiant. Créer six images éditoriales, les exporter en WebP, puis associer chaque image et chaque composition à son service. Conserver la route, le compteur, les points d’étape et les animations réversibles. Vérifier les liens, les états au défilement et la lisibilité sur ordinateur et téléphone.

### Résultat

Les six compositions sont associées aux URL des services dans `app/Views/home.php`. Les images WebP sont chargées à l’approche du défilement et pèsent environ 571 Ko au total dans `public/assets/images/journey/`.

| Étape | Composition |
| --- | --- |
| Orientation | Carte routière et boussole, avec un cadre de carte postale |
| Admission | Portrait sur le campus à côté du texte |
| Prise en charge | Chemise de dossier, bandeau sauge et documents illustrés |
| Visa | Carnet de voyage avec une reliure bordeaux |
| Logement | Photo du studio sur toute la largeur et action en pilule |
| Billet d’avion | Aile au-dessus des nuages, talon perforé et code-barres |

La logique de défilement reste dans `home.js`. Les cartes reprennent les classes et attributs qui déclenchent les arrivées, la progression et les tampons. Le focus clavier rend la carte immédiatement lisible. Sur téléphone, les images latérales passent au-dessus du texte et le rail se resserre.

Contrôles sur l’aperçu local à 1 440, 375 et 320 px. Les six images ont été décodées et les six liens correspondent à leur service. Le compteur a avancé au défilement puis est revenu de 05 à 03 en remontant. Le lien Admission a ouvert la page « Demande d’admission » avec la touche Entrée. La syntaxe PHP est valide.

### Prompts des illustrations

Les visuels ont été produits avec ImageGen. Chaque scène reprend cette consigne commune.

> Photorealistic editorial travel and student life photography for a Dakar-based study abroad agency. Elegant independent travel magazine composition. Strong immediate focal subject, believable physical details, clean daylight or well-controlled shadows, neutral contrast, real material texture, understated warm color. No graphic design, text overlays, lettering, brand names, signatures, watermarks, or collage. Create one continuous photograph only, with enough clearance to support cropping.

- `orientation` — Wide horizontal 2:1 composition. Overhead view of a large folded geographical map on a white oak table, a round brass compass in the left-middle, a hand of a young Black Senegalese adult holding a dark pencil and tracing a route on the map at the right-middle, cropped sleeve in subtle warm red. Editorial travel planning, crisp map folds, soft late afternoon sunlight. Printed details of map very fine and unreadable. Compass and hands near horizontal middle band, no faces.
- `admission` — Portrait 3:4 composition. A young Black Senegalese university student with short elegant natural hair, age 22, carrying a closed burgundy notebook in front of a contemporary university building with tall cream limestone arches. Chest-up candid profile, optimistic and thoughtful, backpack strap, clean warm stone and diffused sun. Student in the center with safe crop space above and both sides, no readable signage.
- `prise` — Wide horizontal 2:1 composition. A carefully assembled application dossier photographed overhead on a sage linen desk. Clean ivory papers, cocoa brown document folder, brushed silver binder clip, simple black pen, one corner of an understated pocket calculator to suggest budgeting, all artfully arranged. No legible words, no cash or bank logos, tactile paper grain, controlled window shadow. Documents arranged around middle band of frame so this can be used in a very wide strip.
- `visa` — Portrait 3:4 composition. An abstract unbranded deep burgundy travel booklet with a simple gold embossed globe symbol only, lying on a stack of off-white travel papers with subtle blank lines. One elegant brass pen alongside. Top-down still life on muted pale stone, diagonal midday light and precise paper detail. No real passports, no official emblems, no IDs, no text, no personal data. Booklet entirely within central half of image.
- `logement` — Wide horizontal 2:1 composition, warm documentary interior of a welcoming realistic student apartment in a university city, from just inside the open entrance. Neatly made small bed with white linen, olive throw, wooden desk with notebook by a broad light-filled window, a compact plant and a simple reading lamp. Design-conscious, lived-in, modest and attainable, soft sun, no people. Show recognizable window, bed and desk across the middle of the panorama.
- `billet` — Ultra-wide horizontal photo, view from passenger cabin above thick soft clouds, gracefully swept commercial airplane wing and raised wingtip facing sunset, realistic aircraft surface and edge lighting. Wing lies across central horizontal band, quiet wide view, warm light reflecting on its unmarked metal, ivory clouds and a muted rose sky. Wing and horizon remain clear in a shallow landscape crop. No boarding pass, no readable marks, no logos.

## Itération sur les visuels et la trajectoire

Recréer les visuels Orientation, Prise en charge et Visa. Générer deux avions transparents, puis faire suivre au grand avion une route sinueuse entre les escales. Garder la jauge DKR horizontale et vérifier la synchronisation avec le compteur dans les deux sens, sur ordinateur et téléphone.

### Résultat

- Orientation montre une étudiante en entretien avec un conseiller à Dakar. Prise en charge montre un conseiller qui présente un dossier validé. Visa montre un passeport ouvert avec un cachet illustratif « VISA VALIDÉ ».
- Les cinq nouveaux fichiers ImageGen sont intégrés en WebP dans `public/assets/images/journey/`. Le jet de profil `avion-dkr.webp` (168 × 56 px) et l’avion vu du dessus `avion-route.webp` (256 × 256 px) conservent leur canal alpha et pèsent ensemble moins de 10 Ko.
- `home.js` dessine les courbes à partir de la géométrie des stations et de l’espace laissé par les cartes. La trajectoire est recalculée lorsque la disposition change, puis échantillonnée pour limiter les calculs pendant le défilement. L’avion s’oriente selon la tangente. Le tracé rouge, la jauge DKR, les tampons et le compteur suivent la même progression réversible. En mouvement réduit, la route est affichée à l’arrivée.
- Vérifié dans le navigateur à 1 440 et 375 px. Les images se chargent, les deux avions ont des tailles distinctes et la courbe s’adapte à la colonne mobile sans débordement. Le défilement vers le haut a ramené le compteur de 03 à 01 et la progression d’environ 42 % à 6 %. La syntaxe PHP et JavaScript est valide.

### Prompts ImageGen de cette itération

- `orientation` — Realistic editorial travel-agency photography, Senegalese contemporary life, physically accurate, warm natural light, crisp, no logos, no watermark, visually readable even at card size. Landscape 2:1. A young adult Black Senegalese woman student sits face-to-face across a table with an attentive Black Senegalese male career counselor in his thirties. The young woman has a notebook and asks a question, he gestures at her study options. Warm modern Dakar counseling office, daylight through a side window, simple woven detail. Show both faces in profile or three-quarter view, centered vertically in the central half, with enough headroom for crop and relaxed candid expressions.
- `prise` — Realistic editorial travel-agency photography, Senegalese contemporary life, physically accurate, warm natural light, crisp, no logos, no watermark, visually readable even at card size. Panoramic 2:1. A friendly Black Senegalese male advisor at a Dakar study agency in a softly lit professional office, clearly presenting a physical open document folder angled toward camera, as if just approved. The first page has only a clearly visible large dark green circle with a check mark, blank lines, no names or formal markings. Position face and raised folder beside each other rather than stacked, both in the central horizontal band with wide clearance and ample margins to survive a 3:1 crop. Face, hand and check mark clearly readable.
- `visa` — Realistic editorial travel-agency photography, Senegalese contemporary life, physically accurate, warm natural light, crisp, no logos, no watermark, visually readable even at card size. Portrait 3:4. Close-up of an open fictional burgundy passport booklet on a clean stone desk, clearly passport-like pages. Fine generic lines and guilloche texture, no government emblems, country names, numbers, photos, dates, readable personal information or official border marks. A large illustrative ink stamp is the primary focal point, exact French wording “VISA VALIDÉ” on a single page, decorative border and check mark. The open passport and stamp fill most of the composition, centered so a wide mobile crop still sees stamp. A believable paper and ink texture, warm studio sunlight.
- `avion-dkr` — Photorealistic product cutout of a real-looking commercial passenger jet alone on genuine transparent alpha, no ground plane, no cast shadow outside the object, no background, no lettering, no airline logos. Both wings, tail and nose fully inside the frame. Wide landscape. Strict side profile, nose pointing exactly to the RIGHT and level, silver-white narrow-body twin-engine jet, red tail accent and red stripe, natural studio highlights, gear retracted, right-facing clean silhouette readable in a very small website tracker. Keep the wings low foreshortened profile and the whole plane in frame.
- `avion-route` — Photorealistic product cutout of a real-looking commercial passenger jet alone on genuine transparent alpha, no ground plane, no cast shadow outside the object, no background, no lettering, no airline logos. Both wings, tail and nose fully inside the frame. Square canvas. Photorealistic twin engine passenger aircraft from above at a slight rear three-quarter banking angle, with both swept wings clearly visible and a cream-white fuselage with a vermilion tail fin and fine red wingtip accents. The aircraft nose points exactly UP toward top center of canvas and tail toward bottom, wings span left and right; axial top view aligned to screen so it can be rotated along a web flightpath. In flight, landing gear retracted. Sharp silhouette, directional daylight from upper left, colored details remain visible when displayed at 70px. Center the plane so rotation pivots on fuselage.

## Extension aux fiches pays et universités

### Plan

Partager le parcours de vol de l’accueil avec les fiches qui affichent la grille d’accompagnement. Extraire la vue, les styles et l’animation en conservant les six services, leurs liens, les illustrations et les ancres existantes. Vérifier le tracé et la progression dans les deux sens sur les fiches, ainsi que le rendu de l’accueil et des cartes de destinations.

### Résultat

- L’accueil, les 12 fiches pays et les 8 fiches d’universités utilisent la même vue `app/Views/partials/services.php`. Les fiches gardent leur texte d’introduction contextualisé et l’ancre `#accompagnement`. L’accueil conserve `#etapes-titre`.
- Les styles et l’animation résident dans `public/assets/css/journey.css` et `public/assets/js/journey.js`. Les pages qui affichent le parcours chargent ces fichiers. Les six illustrations, les deux avions et les liens vers les services restent communs. Les classes du pied des escales sont propres au parcours pour préserver l’affichage des cartes d’embarquement de l’annuaire et des suggestions.
- Vérification HTTP réussie sur les 21 adresses. Sur la fiche Tunisie à 1 440 px, la jauge reste visible et le compteur est revenu de 03 à 01 en remontant. À 390 px, les six cartes suivent le rail à gauche sans débordement et la carte Admission ouvre le formulaire local au clavier. Le parcours a aussi été contrôlé sur la fiche UCAD à 768 px et sur l’accueil. Les contrôles de syntaxe PHP et JavaScript passent.

## Fluidité du parcours au scroll (22 novembre 2026)

Demande : les animations de la section « parcours » (route, avion, cartes, tableau de bord) ont des saccades ; il faut les optimiser pour une fluidité maximale.

### Mesure avant

- **Méthode** : défilement automatique de 14 px par image sur toute la section, à 1440 × 900, sur un écran à 90 Hz (11 ms par image). Deux passages.
- **Résultat** :
  - moyenne de 12,8 à 14,3 ms par image ;
  - 37 à 51 images sur 244 dépassent 20 ms, et jusqu'à 10 dépassent 33 ms ;
  - pire image : 56 ms.

### Causes trouvées (journey.js, journey.css)

1. **Variables CSS** : `--jp` et `--route-offset` sont écrites à chaque image sur la section et sur la route. Ces variables s'héritent, donc le navigateur recalcule les styles de toutes les cartes à chaque image.
2. **Barre du tableau de bord** : le remplissage (`width`) et le petit avion (`left`) bougent par propriétés de mise en page, ce qui provoque un recalcul de mise en page à chaque image.
3. **Fond flouté** : le tableau de bord collant a un `backdrop-filter: blur(18px) saturate(170%)`, recalculé à chaque image puisque le contenu défile dessous.
4. **Apparition des cartes** : elle anime un `filter: blur(8px)` sur de grandes cartes, ce qui les redessine entièrement pendant la transition.
5. **Lecture de mise en page** : `getBoundingClientRect()` est lu à chaque image dans la boucle de scroll, après des écritures de style, ce qui force une mise en page synchrone.
6. **Calques** : l'avion de la route et les nuages ont une ombre (`filter`) sans calque de composition déclaré.

### Plan

- **Variables** : n'écrire que sur les éléments qui les utilisent.
  - Le tracé rouge reçoit son `stroke-dashoffset` directement.
  - La barre du tableau de bord passe à `transform: scaleX()`, et son avion à `translateX()`, positionnés en pixels.
- **Lecture de mise en page** : la position de la route est mise en cache (recalculée au redimensionnement et quand la page change de hauteur). Au scroll, seul `scrollY` est lu.
- **Classes** : on ne touche aux classes des étapes que lorsque l'étape active change.
- **Tableau de bord** : fond blanc presque opaque à la place du flou, même rendu visuel.
- **Apparition des cartes** : uniquement opacité et transformation, sans flou.
- **Calques** : `will-change: transform` sur l'avion, le petit avion du tableau de bord et les nuages.
- **Mesure après**, dans les mêmes conditions.

### Résultat (22 novembre 2026)

Fichiers modifiés :
- `public/assets/js/journey.js` :
  - position de la route et largeur de la barre mises en cache, recalculées par `ResizeObserver` sur la route, les étapes et `body`, et au chargement ;
  - au scroll, `stroke-dashoffset` est écrit sur le tracé, `scaleX()` sur la barre et `translate3d()` sur le petit avion ;
  - les classes `is-active` ne sont touchées que lorsque l'étape atteinte change ;
  - les 8 photos des cartes sont chargées et décodées après le chargement de la page.
- `public/assets/css/journey.css` :
  - plus de `--jp` ni de `--route-offset` ;
  - `.hud-fill` et `.hud-plane` en `transform` ;
  - plus de `backdrop-filter` sur le tableau de bord, dont le fond `#fffffff0` reste presque opaque ;
  - cartes qui apparaissent sans `filter: blur` ;
  - `will-change: transform` sur l'avion de la route, le petit avion du tableau de bord et les nuages ;
  - 105 règles, toutes lues.

Le rendu est inchangé : barre rouge, petit avion, tracé, stations, tampons « étape validée », cartes en zigzag.

**Mesure après**, mêmes conditions (1440 × 900, écran à 90 Hz, 14 px par image, 244 images par passage) :

| | Avant | Après |
| --- | --- | --- |
| Moyenne par image | 12,8 à 14,3 ms | **11,2 ms** (90 images/s) |
| 95e centile | 22,3 ms | **11,2 ms** |
| Images de plus de 20 ms | 37 à 51 | **1** (sur trois passages) |
| Images de plus de 33 ms | 1 à 10 | **0** |
| Pire image | 56 ms | 22 ms |

Le même parcours est utilisé sur les fiches de service : il profite des mêmes corrections.

### Mise en ligne (22 novembre 2026)

- **Déploiement** : commit `3c66088`, déployé sur https://taysir.goo-bridge.com avec `bin/deploy-goo-bridge.sh`.
- **Cache des navigateurs** : les CSS et JS sont gardés 7 jours par les navigateurs, et les images 180 jours (`deploy/nginx-goo-bridge.conf`). Sans version dans l'adresse, un visiteur déjà venu aurait gardé l'ancien `journey.js` jusqu'à une semaine.
- **Correction** : `asset()` (`app/Helpers/functions.php`) ajoute `?v=<date de modification>` à tout fichier qui existe. `git pull` ne réécrit que les fichiers modifiés, donc seule leur version change à chaque déploiement.
- **Limite connue** : les images dont l'adresse est assemblée à la main dans `home.php` (dossier `$cities`, `$panoramas`, `hero-flight/`) restent sans version. Si l'une d'elles est remplacée sous le même nom, il faut la renommer.
