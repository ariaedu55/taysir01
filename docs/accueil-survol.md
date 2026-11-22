# Survol du bandeau d’accueil

## Plan

Créer un avion et un banc de nuages photoréalistes sur fond transparent. Composer une ouverture depuis les nuages à l’arrivée, puis un survol plus discret lorsque le bandeau revient à l’écran. Intégrer un petit avion sur le tracé pointillé existant. Vérifier la lisibilité, la répétition au scroll, le chargement des médias, le rythme des destinations et le mode mouvement réduit.

## Résultat

- À l’arrivée en haut de l’accueil, deux bancs de nuages s’écartent pendant qu’un avion traverse l’écran en diagonale. Le titre commence à apparaître après 930 ms environ, puis viennent les boutons et l’info destination. La séquence dure 2,05 s. Le carrousel démarre ensuite avec quatre secondes par destination.
- Lorsque le visiteur quitte presque entièrement le bandeau puis remonte, un survol de 1,51 s s’effectue en sens opposé, derrière les éléments interactifs. Deux seuils de visibilité empêchent le redéclenchement au moindre mouvement près du bord. La progression du carrousel ne redémarre pas à zéro lors du retour.
- Le petit avion de profil `public/assets/images/journey/avion-dkr.webp` suit la ligne rouge pointillée. Les deux ressources ImageGen `public/assets/images/hero-flight/survol.webp` et `nuages.webp` conservent leur transparence et pèsent environ 110 Ko au total.
- La séquence s’efface en cas de navigation au clavier ou de départ anticipé du bandeau. Les médias disposent de 470 ms pour se décoder avant que le contenu ordinaire prenne le relais. Le survol est désactivé en mode mouvement réduit.
- Ouverture et retour vérifiés dans le navigateur à 1 440 × 840 px et 390 × 844 px. Le passage de Calgary à Paris a été observé environ quatre secondes après l’ouverture. Deux retours successifs et le mouvement réduit ont été vérifiés par simulation du script. Syntaxe PHP et JavaScript vérifiée.

## Prompts ImageGen

- **jet** — One photorealistic polished commercial passenger jet isolated on fully transparent alpha, no sky or ground or cast shadow outside the object. Dynamic three-quarter angle viewed slightly from below and a little from the front, as a real airliner flying overhead. Nose points toward screen RIGHT and slightly UP, tail towards left, slight bank, wing and tail silhouette readable, underwing turbofan engines, wheels retracted. White warm silver neutral fuselage, subtle dark red tail and red wingtips, quiet shadow underneath, crisp detail, no characters, no text, no flags, no logos. Wide panorama composition, whole silhouette with 5 percent transparent margin on every side, full wing tips, not cropped.
- **cloud** — One wide expansive realistic bank of mid-altitude cumulus clouds with feathered wispy edges, isolated as a photographic visual effects element on genuine transparent alpha. Wide landscape canvas. Large continuous sculptural volume, bright ivory tops and realistic cool neutral gray base, soft directional daylight. Main cloud bank wide and dense on center left, two lighter overlapping lobes toward the right at different heights. Natural delicate translucent perimeter of dissipating mist; leave transparent margins all around. No sky background, no ground, no cutout hard outlines, no drop shadows outside image, no aircraft, no typography, no haze covering whole canvas. Cloud only. For a travel website opening atmospheric transition.

## Revue et refonte (21 novembre 2026)

### Constats sur la première version

Vérification image par image dans le navigateur, sur ordinateur (1440 px) et mobile (375 px). Ce qui fonctionne :
- déclenchement à l'arrivée, puis variante de retour au scroll ;
- mouvement réduit respecté ;
- secours si les images tardent ;
- environ 68 images/s.

Défauts :
1. La ville se voit dès la première image, par un trou entre les nuages : pas de vrai dévoilement.
2. Une seule image de nuage, utilisée deux fois dont une retournée, avec un aspect de collage et un voile gris terne.
3. Aucune sensation de vol : les nuages glissent à plat, sans profondeur.
4. Le menu reste visible par-dessus les nuages.
5. L'avion est une image figée qui glisse : pas d'inclinaison ni de traînée, et il est énorme sur mobile.
6. Le petit avion de la ligne rouge mesure 54 × 18 px et ressemble à une flèche. Son ombre (`filter`) est recalculée à chaque image.
7. L'ensemble est trop court : 2 s.

### Plan

1. **Nuages** : trois nouvelles couches composées à partir de la photo de nuage (`bin/` → script de génération dans le scratchpad, sorties dans `images/hero-flight/`) :
   - deux murs denses, gauche et droite, qui couvrent tout l'écran ;
   - un banc intermédiaire qu'on traverse.

   Les flous de profondeur sont précalculés dans les images, pour ne pas animer de `filter`.
2. **Ouverture d'environ 3,2 s** :
   - écran entièrement dans les nuages, menu compris ;
   - les murs s'écartent depuis le centre en grossissant vers nous, le banc intermédiaire nous traverse, la brume blanche se dissipe ;
   - l'avion monte en s'inclinant le long d'une trajectoire courbe, en rapetissant, avec une traînée blanche qui se dessine puis s'efface (opacité, pas de dégradé) ;
   - le menu, le titre, les boutons et la carte apparaissent ensuite en cascade.
3. **Retour (≈ 1,3 s)** : un banc de nuages léger traversé derrière le contenu, et un petit avion qui croise dans l'autre sens.
4. **Petit avion de la ligne rouge** : silhouette vectorielle vue de dessus, environ 90 px, sans `filter`.
5. **Performance et vérifications** :
   - couches animées uniquement en `transform` et `opacity` ;
   - avion et traînée pilotés par `requestAnimationFrame`, seulement pendant l'animation ;
   - images décodées avant le départ ;
   - mesure de la fluidité ;
   - vérifications sur ordinateur, tablette et mobile, au retour et en mouvement réduit.

## Refonte : ouverture en vol à travers les nuages (21 novembre 2026)

Demande : rendre le survol époustouflant et dynamique, **sans nouvelle image**, en optimisant ce qui est en place.

### Constat sur la version précédente

- **Dévoilement raté** : dès la première image, la ville se voyait par un trou entre les deux nuages, et un voile gris ternissait l'ensemble.
- **Nuages en collage** : la même image était posée deux fois, sur un seul plan.
- **Pas de sensation de vol** : les nuages glissaient à plat, sans profondeur ni vitesse.
- **Menu visible** par-dessus les nuages pendant toute l'ouverture.
- **Avion figé**, sans inclinaison ni traînée.
- **Petit avion de la ligne rouge illisible** : image de profil de 54 × 18, perçue comme une flèche. Son ombre (`filter`) redessinait le SVG en continu.
- **Trop court** : 2 s.

### Plan

1. **Ouverture d'environ 3,2 s** :
   - **Brume** : un fond uni clair couvre tout l'écran au départ, menu compris, puis s'efface.
   - **Nuages** : trois plans (fond, milieu, avant) faits de la même image `nuages.webp`, répétée 7 fois avec des tailles, positions et retournements différents. Ils s'écartent depuis le centre en grossissant vers la caméra, l'avant plus vite que le fond : on traverse les nuages.
   - **Avion** : `survol.webp` passe entre le plan du milieu et celui de l'avant. Il monte en s'inclinant (trajectoire et roulis séparés), puis s'éloigne en rapetissant.
   - **Traînée** : une fine traînée blanche part de la queue de l'avion et s'estompe à son extrémité. C'est un masque de fondu, exception signalée à l'utilisateur, qui l'a acceptée.
   - **Vitesse** : de fines lignes de vent filent du centre vers les bords.
   - **Photo** : elle se rapproche (zoom 1,2 → 1), comme une descente vers la ville.
   - **Contenu** : il apparaît en cascade (titre, boutons, carte, menu), et la machine à écrire démarre après.
2. **Retour au scroll (environ 1,4 s)** : un banc de nuages léger et un petit avion traversent derrière le contenu, qui reste lisible et cliquable, avec une légère respiration de la photo.
3. **Petit avion de la ligne rouge** : la vue de dessus existante (`avion-route.webp`), à 60 unités, orientée dans le sens de la courbe. Il n'y a plus de `filter`.
4. **Performances** :
   - uniquement `transform` et `opacity`, sans flou animé ;
   - images décodées avant le départ ;
   - images par seconde mesurées sur ordinateur et sur mobile ;
   - mouvement réduit inchangé (survol désactivé).

### Résultat (21 novembre 2026)

Aucune nouvelle image : `nuages.webp` (7 fois), `survol.webp` et `journey/avion-route.webp` existaient déjà.

**Fichiers :**
- `app/Views/home.php` : nouvelle scène `.hero-flight` composée de la brume, de 3 plans de nuages, de l'avion avec sa traînée et de 10 lignes de vent. Le petit avion de la ligne rouge passe en vue de dessus (60 unités, `rotate(90)` pour suivre la courbe).
- `public/assets/css/home.css` :
  - bloc « Survol du bandeau » réécrit ;
  - anciennes règles `.hero-flight-cloud--*` retirées, ainsi que l'ombre du texte du menu pendant l'ouverture ;
  - le `filter` du petit avion est supprimé.
- `public/assets/js/home.js` : délai de secours adapté à la durée (3,7 s à l'ouverture, 1,9 s au retour).

**Chronologie de l'ouverture (3,2 s) :**

| Temps | Ce qui se passe |
| --- | --- |
| 0 à 0,25 s | écran entièrement couvert de nuages et de brume, menu caché |
| 0,12 à 1,8 s | les nuages s'écartent depuis le centre : l'avant en 1,65 s, le milieu en 2,15 s, le fond en 2,6 s |
| — | la brume s'efface entre 0,25 et 2 s ; les lignes de vent filent entre 0,25 et 1,6 s |
| 0 à 3,2 s | l'avion part du bas à gauche, passe au centre vers 1,5 s en s'inclinant, puis sort en haut à droite en rapetissant, avec sa traînée |
| — | la photo se rapproche (zoom 1,22 → 1) |
| 1,85 s | titre |
| 2,05 s | boutons |
| 2,2 s | carte de la destination et noms des pays |
| 2,25 s | menu |
| après le titre | la machine à écrire démarre |

**Réglage clé :** chaque nuage grandit depuis son bord intérieur. Un décalage de (s − 1) × 50 % de sa largeur compense l'agrandissement. Sans lui, les nuages grossissaient plus vite qu'ils ne s'écartaient et masquaient encore tout à 1,2 s.

**Retour au scroll (1,4 s), derrière le contenu :**
- deux nuages traversent de droite à gauche, à 80 % d'opacité au plus fort ;
- un petit avion vole en sens inverse avec sa traînée ;
- la photo respire (zoom 1,06 → 1).

**Mobile :** nuages 2,5 fois plus grands et écart plus large, avion à 82 % de la largeur.

**Vérifications :**
- **Images clés** : ouverture contrôlée à 0, 0,7, 1,15, 1,75 et 2,4 s, sur ordinateur (1440 × 900) et mobile (375 × 812). Retour contrôlé à 0,5 et 0,65 s.
- **Fluidité** :

  | Version | Images par seconde | Pire image | Saccades de plus de 25 ms |
  | --- | --- | --- | --- |
  | Avant (2 s, ouverture) | 68 | 44 ms | 4 |
  | Ouverture | 90 (maximum de l'écran) | 11 ms | 0 |
  | Retour | 84 | 22 ms | 0 |

- **Arrivée réelle** : l'ouverture se lance seule, puis le menu et le titre sont visibles et la machine à écrire affiche « au Canada ».
- **Technique** : `home.css` lu en entier (431 règles), les 53 ressources de la page répondent 200, et le mouvement réduit désactive toujours le survol.
