# Accueil Taysir Orientation Voyage

## Plan

Créer l’accueil en PHP MVC, présenter les parcours Études et Voyage, reprendre les liens et références médias du site existant, puis vérifier le rendu et la navigation.

## Résultat du 20 novembre 2026

- `public/index.php` appelle `HomeController`, qui charge les données de `HomeContent` et le gabarit de l’accueil.
- L’accueil présente les deux parcours, trois destinations, les coordonnées et les accès au rendez-vous et au suivi.
- La page s’adapte aux petits écrans en CSS. Les destinations utilisent les URL des médias relevées dans l’export WordPress. La copie locale de ces médias reste à intégrer.
- Les liens vers les services, la prise de rendez-vous et le suivi pointent vers les pages WordPress existantes. Avant la mise en ligne sur le domaine d’origine, mettre à disposition les pages correspondantes et basculer ces liens vers les routes de la nouvelle application.
- La syntaxe de tous les fichiers PHP et la génération du HTML en ligne de commande ont été vérifiées sous PHP 8.4.15. La prévisualisation HTTP depuis la session a échoué sur la connexion locale. Le rendu visuel dans le navigateur reste à vérifier.

## Aperçu local

Depuis la racine du projet, exécuter dans PowerShell la commande suivante, puis ouvrir `http://127.0.0.1:8080/`.

```powershell
& 'C:/wamp64/bin/php/php8.4.15/php.exe' -n -S 127.0.0.1:8080 -t public
```

## Refonte enrichie à partir de l’accueil WordPress

Le [site public](https://taysir-orientation.com/) et l’export WordPress ont servi à vérifier la navigation, les fiches et les médias. La nouvelle composition comprend les deux parcours, six secteurs, huit cartes de destinations et d’études, les témoignages, huit écoles, les logos partenaires, les avis, la newsletter et les horaires d’assistance.

- Les données de l’accueil se trouvent dans `config/home.php`. Les vues restent en PHP natif. Le menu et les commandes des galeries utilisent du JavaScript natif.
- 57 médias de la médiathèque publique sont intégrés sous `public/assets/images/wordpress/`. Les publications de juin 2024 sont datées et regroupées comme archives. Les notes et compteurs promotionnels de l’ancien accueil ont été écartés en attendant des chiffres validés.
- La prise de rendez-vous, le dossier et l’inscription à la newsletter continuent d’utiliser les services existants. L’intégration native de ces services reste à faire avant le basculement.
- L’aperçu local a été rechargé dans Edge. Le bandeau et ses boutons ont été contrôlés visuellement sur ordinateur. L’ouverture du menu Après le bac et le défilement des destinations ont été testés.

## Palette Taysir et bandeau interactif

Le bandeau propose désormais quatre destinations consultables au clavier ou au toucher. Chaque sélection change la photo et le lien vers la fiche. Les images des autres destinations sont chargées à la demande. Les effets d’entrée et de survol respectent la préférence de réduction des animations.

Le bleu marine et le rouge ont été relevés dans les styles publics du site. Les valeurs et leur usage sont consignés dans [DESIGN.md](DESIGN.md).

La syntaxe PHP et JavaScript a été vérifiée. Dans le navigateur intégré à 530 px, la touche Entrée a sélectionné la France et actualisé la carte et l’état du bouton, sans débordement horizontal.

## Refonte immersive inspirée de la référence visuelle

### Plan

Créer une illustration propre à Taysir autour du thème de l’orientation, transformer le bandeau et les cartes avec des effets de verre, et contrôler la navigation sur ordinateur et mobile.

### Résultat

- Le bandeau utilise deux fichiers WebP locaux, [ordinateur](../public/assets/images/horizon-dakar.webp) et [mobile](../public/assets/images/horizon-dakar-mobile.webp). Le navigateur choisit le cadrage en fonction de la largeur de l’écran.
- Le sélecteur de destinations, les cartes de parcours et les secteurs reprennent un traitement translucide. Les sections ont des animations d’entrée, de défilement et de survol selon les capacités du navigateur et la préférence de mouvement.
- La syntaxe PHP des vues et la syntaxe JavaScript ont été validées. L’accueil a été inspecté à 530 px et 1 440 px. Le menu mobile, la sélection au clavier, la fiche associée et le défilement de la galerie ont été testés. Le recadrage mobile et l’absence de débordement horizontal ont été vérifiés à 360 px.

### Illustration

Générée avec l’outil ImageGen intégré. Prompt :

> Create a wide cinematic 2.2:1 premium 3D editorial artwork for the homepage of Taysir Orientation Voyage, a student guidance and travel agency in Dakar. Deliver just the scene, no words or UI. The singular sculptural subject is a monumental tilted armillary compass, made of thick optically clear refractive glass rings and slim polished silver edges, suspended above the Atlantic coast of Dakar at blue hour. A few fine crimson route trails thread its center and run into the distant horizon, with tiny points of light. Ocean reflections and low silhouettes of the Dakar peninsula give credible atmosphere. Place the ring's center at x=72%, y=47%. Keep the left 42% deliberately calm, dark near-black navy with only faint haze, for large white text to be overlaid. Dominant deep #041B51 marine-blue, ice-white and cobalt refractions, restrained vivid #F11515 accents only, no purple or gold. Dramatic but refined film lighting, realistic glass caustics, subtle ocean mist, clean silhouettes, a single hero object and no generic floating cubes. Full bleed to the edges. No typography, flags, icons, watermarks, logos, people, interface or framing.

## Accueil clair et diaporama photographique

### Plan

Reprendre l’enchaînement des sections de l’accueil WordPress et remplacer le concept illustré de l’itération précédente par des photographies urbaines. Appliquer le verre dépoli aux commandes et aux cartes sur un fond clair.

### Résultat et vérifications

- Sept villes sont présentées dans le diaporama, Toronto, Paris, Istanbul, Londres, Casablanca, Tunis et Dakar. Les médias locaux figurent dans `public/assets/images/destinations/`. Les villes, textes, liens et crédits sont dans `config/home.php`. Les crédits photo et les licences sont consultables en pied de page.
- Les parcours, les secteurs, les destinations, les témoignages, les actualités et les partenaires reprennent l’ordre de présentation de l’accueil existant, avec des compositions propres à chaque section.
- Le navigateur a chargé les sept photos. Le passage automatique de Toronto à Paris, la sélection directe, le passage de Casablanca à Tunis avec la touche Entrée et la suspension après intervention ont été vérifiés. Aucun débordement horizontal n’a été observé à 360 px. Le rendu a aussi été contrôlé à 530 et 1 440 px.
- Validation PHP de `config/home.php`, `app/Views/home.php` et du gabarit principal. Validation JavaScript avec `node --check`.

## Mise à jour : section « Opportunités et bourses » supprimée (21 novembre 2026)

Demande : supprimer la section « Opportunités et bourses » de l'accueil, qui listait huit publications de juin 2024.

Retraits :
- `app/Views/home.php` : section `#actualites` ;
- `public/assets/css/home.css` : bloc « Actualités » et lignes `.news*` des media queries (17 lignes) ;
- `config/home.php` : données `news`, qui ne servaient qu'à cette section.

Les publications restent accessibles sur la page « Blog » et dans leurs fiches.

Ordre de l'accueil désormais : bandeau, deux parcours, étapes, destinations, secteurs, témoignages, écoles et partenaires, appel final.

Vérifications : plus aucune trace de la section ; les partenaires s'enchaînent sur l'appel final ; les 337 règles de `home.css` sont lues.
