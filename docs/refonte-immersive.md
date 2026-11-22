# Refonte immersive de l'accueil et des composants communs

## Demande (20 novembre 2026)

Rendre le site très impactant, élégant et épuré, dans l'esprit du voyage et de l'orientation, en respectant les couleurs de Taysir :
- barre de navigation posée sur l'image de la première section, dynamique ;
- animations poussées mais optimisées, animations au scroll variées ;
- effets de verre (glassmorphism).

L'utilisateur a mentionné une image d'inspiration, mais elle n'est pas parvenue dans la conversation. La direction ci-dessous a été choisie en attendant (carte blanche).

## Direction

- **Couleurs** : bleu marine `#041B51` (et un marine nuit `#020C26` pour les sections sombres), rouge Taysir `#F11515`, rouge d'action `#CE0F0F`, ivoire `#FCFBF8`.
- **Motif** : la traînée rouge et l'avion du logo, repris sous forme de trajectoires qui se dessinent au scroll.
- **Verre** : panneaux translucides floutés, bordure claire fine, reflet qui suit le pointeur.
- **Rythme** : alternance de sections claires et de sections marine nuit.
- **Logo** : versions marine et blanche générées depuis le logo WordPress (`public/assets/images/brand/`).

## Plan

1. **En-tête commun** : barre de verre flottante, posée sur le bandeau. Elle passe du thème transparent au thème clair au scroll, se masque quand on descend et réapparaît quand on remonte. Menus déroulants en verre sur ordinateur, menu plein écran animé sur mobile.
2. **Pied de page commun** : fond marine nuit, grand mot-symbole, colonnes de liens, coordonnées, crédits.
3. **Moteur d'animation** (`app.js`, sans bibliothèque) :
   - apparitions au scroll de plusieurs types (montée, fondu, flou, échelle, rideau, mots un à un, cascade) ;
   - effets liés à la position de scroll (parallaxe, progression, défilement horizontal épinglé) ;
   - reflet au pointeur et boutons magnétiques.
   Le tout avec une seule boucle `requestAnimationFrame`, des propriétés `transform` et `opacity` uniquement, et le respect de `prefers-reduced-motion`.
4. **Accueil** :
   - bandeau plein écran (diaporama des villes en fondu, zoom lent, carte de verre de la ville active, barres de progression) ;
   - manifeste dont les mots s'éclairent au scroll ;
   - deux parcours (Études et Voyage) ;
   - parcours en 6 étapes, avec une trajectoire d'avion qui se dessine ;
   - destinations en défilement horizontal épinglé (les 12 pays) ;
   - secteurs en mosaïque ;
   - témoignages en bandeau défilant ;
   - écoles et partenaires ;
   - actualités ;
   - appel final.
5. **Fiches pays** : adapter le bandeau au nouvel en-tête.
6. **Vérifications** : syntaxe, ordinateur et mobile, mouvement réduit, absence de débordement, fluidité.

## Résultat

### Composants communs

| Fichier | Contenu |
| --- | --- |
| `app/Views/layouts/main.php` | Ajout de `html.js` (classe posée avant tout rendu), `theme-color`, et de la variable `$heroHeader` : la page commence par un bandeau sombre, l'en-tête s'y pose en blanc. Les pages sans bandeau sombre reçoivent `has-light-header`, et leur `main` est décalé sous l'en-tête. |
| `app/Views/partials/header.php` | Barre de verre flottante : logo marine ou blanc selon le fond, lien Destinations, 5 menus déroulants en verre (deux colonnes au-delà de 8 liens), Mon dossier, bouton Rendez-vous. Sous 78rem : bouton menu et menu plein écran (ouverture circulaire, liens en cascade, sous-menus repliables, actions et contacts). |
| `app/Views/partials/footer.php` | Fond marine nuit avec lueur rouge. Logo, présentation, coordonnées, WhatsApp, quatre colonnes (6 destinations, accompagnement, agence, réseau), grand mot-symbole « Taysir », mentions et crédits photo. |
| `public/assets/css/app.css` | Réécrit : variables (`--night`, `--ink-soft`, polices, courbes), boutons (dégradé rouge avec reflet, `light`, `glass-button`, flèche animée), `.glass` et `.glass-dark`, `.kicker`, reflet au pointeur, en-tête, menus, pied de page, système d'apparition et mouvement réduit. |
| `public/assets/js/app.js` | Réécrit : boucle de scroll unique (`window.Taysir.addScrollTask`), en-tête (fond selon la position, masquage à la descente, menus au clic, Échap, clic extérieur), menu plein écran, découpage des titres en mots, cascades, apparitions, `--p`, parallaxe, mots qui s'éclairent, reflet et aimantation. |
| `public/assets/images/brand/logo-{navy,white}.png` | Logo WordPress recadré en 317 × 112 px (2x) sur fond transparent, en marine et en blanc (traînée rouge conservée). |

### Attributs d'animation, utilisables sur toutes les pages

- `data-reveal="up|down|left|right|scale|blur|clip|tilt|fade"` : apparition une seule fois à l'entrée dans l'écran.
- `data-split` : titre découpé en mots qui montent un à un.
- `data-stagger` sur un parent : décalage progressif des enfants (variable `--i`).
- `style="--delay: 300ms"` : retard.
- `data-words` : les mots s'éclairent au fil du scroll.
- `data-scroll` (ou `data-scroll="center"`) : variable `--p` de 0 à 1 selon la position.
- `data-parallax="0.15"` : décalage vertical proportionnel au scroll.
- `data-glow` : reflet qui suit le pointeur.
- `data-magnetic` : bouton attiré par le pointeur.

Sans JavaScript, ou avec la préférence « mouvement réduit », tout est visible, sans animation.

### Accueil

`app/Views/home.php`, `css/home.css` et `js/home.js` ont été réécrits. Les sections, dans l'ordre :

1. **Bandeau plein écran** :
   - les 7 photos de villes en fondu, avec un zoom lent ;
   - des trajectoires rouges animées et un avion qui suit la trajectoire (SVG) ;
   - le titre « Votre avenir n'a pas de frontières. », dont la seconde partie est en dégradé chatoyant ;
   - une carte de verre de la ville active, avec précédent, suivant et pause ;
   - des barres de progression par ville, qui donnent le tempo du diaporama ;
   - une parallaxe au scroll et un indicateur de défilement.

   Le diaporama se met en pause hors de l'écran, quand l'onglet est caché, ou si l'on demande moins de mouvement.
2. **Manifeste** dont les mots s'éclairent au scroll.
3. **Deux parcours** : photo révélée en rideau avec parallaxe, cartes de verre Études et Voyage.
4. **Parcours en 6 étapes** (fond marine) : titre fixe, ligne qui se remplit, avion qui la descend, étapes qui s'allument.
5. **Destinations** : les 12 pays en défilement horizontal épinglé au-delà de 62rem, avec une barre de progression. Sur mobile, carrousel à défilement tactile. Au clavier, la carte qui reçoit le focus est ramenée à l'écran.
6. **Secteurs** en mosaïque de verre, apparition en bascule 3D, sphères colorées en parallaxe.
7. **Témoignages** en bandeau défilant continu, qui s'arrête au survol.
8. **Réseau** : 8 écoles (initiales générées pour celles sans logo), deux rangées de logos partenaires en sens opposés, avis Kiyoh.
9. **Actualités** en liste à deux colonnes, avec soulignement rouge au survol.
10. **Appel final** (fond marine) : orbites animées, titre en dégradé, trois cartes (horaires, newsletter, adresse).

### Fiches pays et liste

- Bandeau pleine largeur sous l'en-tête posé dessus, arrondi en bas, assombri en haut pour la lisibilité du menu.
- Sommaire et rubriques décalés sous l'en-tête.
- Apparitions ajoutées aux fiches et à la liste.
- Le nom du pays et le mot en rouge de la liste ciblent maintenant une classe (`.country-name`, `.accent`) : avec le découpage en mots, la règle `h1 span` visait chaque mot.

### Vérifications

- `php -l` et `node --check` sans erreur. Accueil, liste et fiches en 200, redirections et 404 inchangées. Aucune erreur dans la console.
- Rendu contrôlé à 1 024 px et 1 440 px (navigation complète, menu déroulant à deux colonnes) et à 375 px (bandeau, menu plein écran, fiche pays).
- Comportements vérifiés :
  - l'en-tête passe en blanc sur le bandeau, se masque à la descente et revient en version claire à la remontée ;
  - le manifeste s'éclaire progressivement, les étapes s'allument une à une, et le défilement horizontal suit le scroll (45 % donne un décalage de 1 423 px sur 3 162) ;
  - le menu mobile s'ouvre, se ferme avec Échap, et le focus revient sur le bouton ;
  - aucun débordement horizontal à 375 px.
- Corrections faites pendant les tests :
  - actions de l'en-tête alignées à droite sous 78rem ;
  - pied de page sur une colonne sous 75rem ;
  - photos des témoignages en 4:5 ;
  - initiales des écoles en majuscules ;
  - photos des destinations préchargées ;
  - l'avion du parcours passe sous les pastilles ;
  - titres découpés ciblés par classe.
- **Limites des tests** :
  - le panneau du navigateur était masqué, donc les captures figent les animations en cours de route. Les états finaux ont été contrôlés en coupant les transitions, et la fluidité réelle reste à juger sur un écran visible ;
  - la préférence « mouvement réduit » n'a pas été émulée : seul le code a été relu ;
  - pas de test sous Apache/WAMP ni sur Safari, alors que les effets de verre utilisent `-webkit-backdrop-filter` et que l'avion utilise SVG `animateMotion`.
- L'image d'inspiration citée par l'utilisateur n'est pas parvenue : le design est à ajuster quand elle sera fournie.

## Itération 2 : élégance typographique (20 novembre 2026)

### Retours de l'utilisateur

- Pas de dégradé de couleur sur le texte.
- Titre du bandeau trop volumineux.
- Police plus élégante.
- Aucun texte en rouge.
- Titre en machine à écrire qui cite les pays.
- Diaporama trop lent : 4 secondes par ville.
- Section « Deux parcours » qui manque de style : la photo n'apparaissait pas dans son navigateur, et le titre occupait quatre lignes géantes.

### Plan

1. Polices : Playfair Display pour les titres et Plus Jakarta Sans pour le texte (Google Fonts, `display=swap`). Titres plus mesurés.
2. Supprimer tous les dégradés sur texte : titre et appel final de l'accueil, mot-symbole du pied de page, classe `.text-gradient`.
3. Passer en bleu marine ou en blanc tous les textes rouges : surtitres, liens, catégories, numéros, survols du menu, manifeste. Le rouge reste réservé aux boutons, pastilles, traits et icônes.
4. Bandeau : titre « Votre avenir s'écrit » suivi d'une destination tapée à la machine à écrire (« au Canada », « en France »…), synchronisée avec la photo. 4 secondes par ville.
5. Section « Deux parcours » redessinée : composition de photos superposées avec une trajectoire, titre plus court, deux parcours numérotés présentant les services inclus.

### Résultat de l'itération 2

- **Polices** : Playfair Display (500, 600, 700 et italique) pour les titres, Plus Jakarta Sans pour le texte, chargées depuis Google Fonts dans le gabarit. Titres en graisse 600, interlettrage -0,015em. Tailles réduites : `h2` de 2 à 3,3rem, titre du bandeau de 2,4 à 4,6rem (2,1 à 2,8rem sur mobile), appel final de 2,3 à 4,4rem, manifeste de 1,7 à 3rem, noms de pays des fiches de 2,8 à 5,4rem.
- **Plus aucun dégradé sur le texte** : règles du titre et de l'appel final, mot-symbole du pied de page (blanc à 7 % d'opacité, en italique) et classe `.text-gradient` supprimés. Les parties accentuées sont en italique.
- **Plus aucun texte rouge** : surtitres, survol des menus, liens de parcours, catégories d'actualités, initiales des écoles, manifeste, sommaire et numéros des fiches, liens du contenu, compteurs du filtre et mot accentué de la liste. Le rouge reste sur les boutons, les pastilles, les traits, les puces, les icônes et le curseur.
- **Bandeau** : titre « Votre avenir s'écrit » suivi d'une destination tapée à la machine à écrire. La destination (7ᵉ valeur de `heroSlides` dans `config/home.php`) s'efface jusqu'au début commun puis se tape, avec un curseur rouge qui clignote à l'arrêt, au moment où la photo change. Les lecteurs d'écran lisent la liste complète des destinations. **4 secondes par ville** (`--slide-ms`), fondu de 1,2 s.
- **Deux parcours** : composition avec la photo de l'étudiant en arche, une carte Paris superposée, une trajectoire rouge en pointillés qui se dessine, un cercle pointillé et un badge de verre flottant « Un conseiller dédié ». À droite : « Étudier ou voyager, *nous traçons la route.* », une introduction, puis deux parcours numérotés en italique, avec leurs services en étiquettes, un trait rouge au survol et une flèche ronde. L'apparition en rideau (`clip`) est remplacée par une montée simple.

### Vérifications de l'itération 2

- `php -l` et `node --check` sans erreur. Pages en 200, aucune erreur dans la console. Polices chargées (`document.fonts.check`).
- Machine à écrire : au passage à Paris, « au Canada » s'efface puis « en France » se tape, et la photo change en même temps. Le diaporama est passé seul de Toronto à Paris pendant un rendu.
- « au Royaume-Uni », la destination la plus longue, tient sur une ligne à 375 px (343 px). Aucun débordement horizontal à 375 px. Composition des parcours dans les limites de l'écran.
- Rendu contrôlé à 1 280 px (bandeau, Deux parcours) et à 375 px (bandeau).
- Dans le panneau masqué, la photo en arche n'apparaissait qu'après son décodage, comme sur la capture de l'utilisateur. Le décodage différé (`decoding="async"`) a été retiré de cette image. À confirmer dans le navigateur de l'utilisateur.

## Itération 3 : animations réversibles (20 novembre 2026)

### Demande

- Manifeste « Notre mission » : machine à écrire liée au scroll. Le texte s'écrit quand on descend et s'efface quand on remonte.
- « Deux parcours » : le personnage au centre, entouré d'éléments stylés. À l'arrivée, il monte du bas avec un rebond, puis les éléments arrivent chacun de leur côté. Quand on remonte, l'animation se rembobine.

### Plan

1. `app.js` :
   - `[data-type-scroll]` découpe le texte en caractères et en affiche une proportion égale à la progression du scroll, avec un curseur rouge ;
   - `[data-reveal-toggle]` ajoute `.is-in` à l'entrée et le retire quand l'élément ressort par le bas : l'animation joue à l'envers.
2. Section « Deux parcours » refaite en scène centrée :
   - en-tête centré ;
   - l'étudiant en arche au centre, avec des orbites et des étiquettes autour ;
   - à gauche, la carte Études et le badge conseiller ; à droite, la carte Voyage et la photo de Paris ;
   - une trajectoire qui relie le tout.

   Les animations utilisent les propriétés `translate` et `scale` : le survol, qui utilise `transform`, reste indépendant. Rebond par courbe de dépassement. Au rembobinage, l'ordre est inversé : les côtés partent avant le personnage.

### Résultat de l'itération 3

- **Manifeste** (`data-type-scroll`) : chaque caractère est un `span`, et les mots restent insécables. La part tapée suit la position du scroll : 0 % quand le haut du texte est à 88 % de l'écran, 100 % quand il atteint 30 %. Le curseur rouge suit la dernière lettre et clignote une fois le texte terminé. On écrit en descendant, on efface en remontant. La mise en page ne bouge pas, car les lettres non tapées sont transparentes. La fin de la phrase est en italique (`<em>`).
- **Deux parcours** (`data-reveal-toggle`) :
  - en-tête centré ;
  - une scène sur trois colonnes : la carte Études et le badge conseiller à gauche, l'étudiant en arche au centre, la carte Voyage et la photo Paris à droite ;
  - autour de l'arche : deux orbites qui tournent avec un point rouge, quatre étiquettes de verre (Orientation, Admission, Visa, Logement), l'avion dans une pastille rouge, et une trajectoire en pointillés qui relie les deux côtés.
- **Chorégraphie** :
  1. l'étudiant monte de 160 px avec un rebond (courbe de dépassement) ;
  2. les orbites s'ouvrent ;
  3. la gauche arrive de la gauche et la droite de la droite, en cascade et avec rebond ;
  4. les étiquettes jaillissent ;
  5. la trajectoire se dessine.

  En remontant au-dessus de la scène, `.is-in` est retiré et tout se rembobine dans l'ordre inverse (côtés et étiquettes d'abord, étudiant ensuite). Les animations utilisent `translate`, `scale` et `opacity`, et le survol des cartes garde `transform`.
- **Adaptation** :
  - colonne centrale proportionnelle (`clamp(15rem, 25vw, 24rem)`) ;
  - sous 62rem, l'étudiant au-dessus et les deux côtés en dessous sur deux colonnes ;
  - sous 34rem, une seule colonne, des étiquettes plus petites et rapprochées, et des côtés qui arrivent de 60 px.

### Vérifications de l'itération 3

- `php -l` et `node --check` sans erreur, pages en 200.
- Manifeste : 73 caractères sur 152 tapés quand le haut du texte est à 60 % de l'écran, 152 sur 152 plus haut, et retour à 16 sur 152 en remontant, curseur placé après la dernière lettre.
- Scène : `.is-in` ajouté quand la scène est à l'écran, retiré quand on remonte au-dessus.
- Mise en page vérifiée à 1 024 px (colonnes de 308 px, étiquettes sans chevauchement des cartes) et à 375 px (aucun débordement horizontal ; les étiquettes débordaient de 5 px avant correction).
- **Limite** : le panneau du navigateur étant masqué, `requestAnimationFrame` ne tourne qu'au moment des captures. Les états ont été vérifiés par les classes et les mesures. La fluidité réelle du rebond et du rembobinage reste à juger sur un écran visible.

## Itération 4 : manifeste fusionné dans « Deux parcours » (20 novembre 2026)

- À la demande de l'utilisateur, la section « Notre mission » est supprimée (balisage et styles `.manifesto`).
- Son texte (« De la première question au premier jour sur le campus… *le bon choix, au bon endroit, au bon moment.* ») remplace l'ancien paragraphe d'introduction, sous le titre de « Deux parcours ». Il garde la machine à écrire liée au scroll (`data-type-scroll`). La partie finale est en Playfair italique, en bleu marine.
- La section « Deux parcours » reçoit `id="parcours"`, et le lien « Découvrir » du bandeau, qui visait `#manifeste`, pointe désormais vers elle.
- Vérifié : plus de section `.manifesto` dans la page, texte tapé à 73 caractères sur 152 à mi-écran puis à 152 sur 152, PHP valide.

## Itération 5 : pays plutôt que villes dans le bandeau (20 novembre 2026)

- La carte « Destination à la une » affiche le drapeau suivi du nom du pays (Canada, France, Turquie, Royaume-Uni, Maroc, Tunisie, Sénégal), à la place de la ville. La ligne séparée du pays est supprimée (`.hero-card-country` devient `.hero-card-name`). L'accroche et le lien sont conservés.
- Les repères de progression du bas, leurs libellés accessibles et l'annonce aux lecteurs d'écran utilisent eux aussi le nom du pays. Repères élargis à 6,6rem.
- Les photos restent celles des villes (Toronto, Paris…), avec leurs crédits dans le pied de page.
- Vérifié à 1 280 px : « Royaume-Uni » tient sur une ligne dans la carte, et les sept repères tiennent sur une ligne.

## Itération 6 : bandeau mobile épuré, navigation compacte (20 novembre 2026)

Uniquement sous 34rem (544 px, téléphones) :
- Bandeau : le paragraphe d'introduction et les deux boutons (« Prendre rendez-vous », « Explorer les destinations ») sont masqués. Il reste l'étiquette, le titre en machine à écrire et la carte du pays avec ses commandes. Le rendez-vous reste accessible depuis le menu plein écran.
- Barre de navigation : hauteur ramenée à 3,4rem (barre de 54 px), marges réduites, logo de 88 × 31 px (contre 110 × 39), bouton menu de 38 px (contre 46), traits de 15 px. Le bouton Rendez-vous de la barre était déjà masqué à cette taille.
- Vérifié à 375 px : paragraphe et boutons en `display: none`, aucun débordement horizontal.

## Itération 7 : fonds en blanc pur (20 novembre 2026)

- À la demande de l'utilisateur, tous les fonds beige ou blanc cassé sont remplacés par du blanc pur `#FFFFFF`. Seules les sections explicitement marine gardent leur couleur : bandeau, parcours en étapes, appel final, pied de page et bandeau des fiches pays.
- `--paper` et `--sand` valent désormais `#fff`. Sont aussi passés en blanc : la barre de navigation claire (`#ffffffe0`, ou `#ffffffbf` avec le flou), les sections Secteurs et Actualités, la section Accompagnement des fiches pays, l'en-tête de la liste des destinations (sans halo rosé), l'encadré d'aide des fiches (blanc avec bordure) et la page d'erreur.
- Les halos colorés de la section Secteurs (`.orb`) sont supprimés : ils teintaient le fond.
- Pour que les cartes de verre restent détachées du blanc, `.glass` a désormais une bordure marine très légère (`#041b5114`) au lieu d'une bordure blanche.
- Page d'erreur : le grand numéro passe du rouge au marine (règle « aucun texte en rouge »).
- Vérifié : body et sections claires en `rgb(255, 255, 255)` ou transparentes, sections marine inchangées, plus aucun élément `.orb`.

## Itération 8 : animations à chaque passage, responsive proportionnel (20 novembre 2026)

### Constat

- `[data-reveal]` et `[data-split]` ne jouaient qu'une fois : l'observateur cessait de suivre l'élément après son apparition. `[data-reveal-toggle]` ne se rembobinait qu'en remontant.
- Les tailles sont exprimées en `rem` à partir d'une base fixe de 16 px : sur téléphone, les textes, marges, cartes et arrondis gardaient leur taille d'ordinateur, d'où un rendu déséquilibré.

### Plan

1. `app.js` : toutes les apparitions (`data-reveal`, `data-split`, `data-reveal-toggle`) jouent à l'entrée dans l'écran et sont retirées à la sortie, par le haut comme par le bas. Elles rejouent donc à chaque passage, dans les deux sens. Le mouvement réduit reste respecté.
2. `app.css` : taille de base de 15 px sous 48rem (tablettes) et de 14 px sous 34rem (téléphones). Tout ce qui est en `rem` se réduit proportionnellement. Les points de rupture restent calculés sur 16 px. Les cibles tactiles en pixels (boutons de 44 à 48 px) sont conservées.
3. Réglages fins sur mobile : marges des sections, cartes, titres, scène « Deux parcours », destinations, témoignages, logos et pied de page.

### Résultat de l'itération 8

- **Apparitions rejouées à chaque passage** (`app.js`) :
  - un seul observateur pour `data-reveal`, `data-split` et `data-reveal-toggle` ;
  - `.is-in` est ajouté quand au moins 10 % de l'élément est visible (25 % pour les scènes `data-reveal-toggle`), proportion calculée sur la hauteur de l'élément ou de l'écran s'il est plus grand ;
  - `.is-in` est retiré dès que l'élément sort complètement de l'écran, par le haut ou par le bas.
  
  L'animation rejoue donc en descendant comme en remontant, et se joue à l'envers à la sortie. La machine à écrire et les effets de progression étaient déjà réversibles.
- **Échelle responsive** (`app.css`) : taille de base de 15 px sous 48rem et de 14 px sous 34rem. Tout ce qui est en `rem` se réduit proportionnellement : textes, marges, cartes, arrondis, hauteur de la barre de navigation.
- **Réglages fins sous 34rem** :
  - `app.css` : marges de section de 3,75rem, `h2` de 1,8 à 2,3rem, surtitres, boutons et pied de page resserrés ;
  - `home.css` : carte du bandeau, scène « Deux parcours » (cartes, photo en 16:10, avion), étapes, destinations (cartes de 13 à 16rem), mosaïque des secteurs (sans hauteur minimale), témoignages, écoles, logos, actualités et appel final.
- La trajectoire en pointillés de « Deux parcours » est masquée quand la scène est empilée (sous 62rem) : elle ne laissait que des tirets isolés entre les cartes.
- **Vérifications** :
  - un titre de section perd et retrouve `.is-in` en sortant puis en revenant, par le haut comme par le bas ;
  - taille de base de 14 px à 375 px et de 15 px à 768 px ;
  - aucun débordement horizontal sur l'accueil, deux fiches pays et la liste, à 375 et 768 px (la carte du bandeau, signalée pendant son animation d'entrée, reste dans l'écran une fois arrivée) ;
  - contrôle visuel de la scène « Deux parcours » à 375 px.
