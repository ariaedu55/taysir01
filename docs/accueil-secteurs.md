# Accueil : section « Secteurs d’avenir »

Demande du 21 novembre 2026 : un design exceptionnel et bien démonstratif pour la section « Choisissez un secteur qui recrute ».

## Avant

Une grille « bento » de 6 cartes blanches : icône, nom, phrase, flèche. Rien ne montrait concrètement ce que contient chaque secteur.

## Conception : panneaux photo qui s’ouvrent

- **Six panneaux photo côte à côte**, un par secteur. Un seul est ouvert, large ; les autres restent étroits, avec le nom à la verticale et l’icône.
- **Démonstratif** : le panneau ouvert montre :
  - le nom et la phrase du secteur ;
  - le nombre de fiches métiers ;
  - trois métiers en pastilles cliquables, menant à leur fiche ;
  - le lien « Explorer le secteur ».
- **Photos** : une photo réelle par secteur, reprise des fiches métiers de l’ancien site. Les photos sont nettes ; un voile sombre couvre les 30 % du bas et le texte est blanc (exception autorisée, voir [charte-claire.md](charte-claire.md)). Les panneaux fermés sont légèrement assombris (`brightness`, pas de couleur).
- **Animation** :
  - ouverture fluide (largeur et contenu en cascade) ;
  - léger zoom de la photo ouverte ;
  - défilement automatique toutes les 5 s, avec une fine barre de progression blanche ;
  - pause au survol, au focus et quand la section est hors écran ;
  - entrée au scroll en cascade, rejouée dans les deux sens.
- **Pilotage** : survol ou clic d’un panneau sur ordinateur ; toucher sur tablette et mobile ; clavier (bouton par panneau, `aria-expanded`).
- **Chiffres** sous le titre : « 6 secteurs · N fiches métiers ».
- **Responsive** :
  - tablette : mêmes panneaux, moins hauts ;
  - mobile : les panneaux s’empilent en bandeaux horizontaux (icône et nom), et le panneau ouvert s’agrandit en hauteur.

## Plan

1. `HomeController` : fiches métiers groupées par secteur (`Article::careers()`).
2. `config/sectors.php` : une photo par secteur.
3. `home.php` : nouvelle structure de la section.
4. `home.css` : panneaux, états, animation, responsive ; retrait des styles `bento`.
5. `home.js` : ouverture, défilement automatique, pause.
6. Vérifications : ordinateur, tablette, mobile ; clavier ; feuille CSS lue en entier ; console.

## Résultat (21 novembre 2026)

Fichiers :
- `app/Controllers/HomeController.php` : passe `sectors` (`Sector::all()`) et `careers` (fiches métiers groupées par secteur) à la vue ;
- `config/sectors.php` : nouvelle clé `image`, une photo par secteur ;
- `app/Views/home.php` : section réécrite. Les icônes viennent de `Sector::icon()`, et l'ancien tableau `$sectorIcons` est supprimé. Les titres des métiers sont raccourcis pour les pastilles, par exemple « Expert immobilier (Real Estate Appraiser) : » devient « Expert immobilier » ;
- `public/assets/css/home.css` : bloc « Secteurs : panneaux photo », qui remplace les styles `bento` (y compris leurs copies dans les media queries) ;
- `public/assets/js/home.js` : script des panneaux.

Photos (médiathèque de l'ancien site) :

| Secteur | Photo |
| --- | --- |
| Santé | `medcin` |
| Numérique | `Design-sans-titre-14` |
| Pétrole et gaz | `Ingenieur-Petrolier` |
| Immobilier | `Ingenieur-en-securite-Safety-Engineer-5` |
| Agriculture et agrobusiness | `30745389-portrait-agriculteur…` |
| Énergies renouvelables | `Consultant-en-agro-business-1` |

Réglages :

| | Ordinateur | Tablette (≤ 62rem) | Mobile (≤ 40rem) |
| --- | --- | --- | --- |
| Disposition | 6 panneaux côte à côte | idem | bandeaux empilés |
| Hauteur | `clamp(30rem, 40vw, 38rem)` | `clamp(26rem, 62vw, 32rem)` | fermé 4,6rem, ouvert 25rem |
| Panneau ouvert | 5 fois la largeur d'un fermé | 6 fois | — |
| Mesuré | 608 px ouvert, 122 px fermés (1440 px) | 353 px / 59 px (768 px) | 350 px / 64 px (375 px) |

Plus de détails :
- **Photos** : assombries à 62 % quand le panneau est fermé, à 86 % quand il est ouvert ; léger zoom à l'ouverture.
- **Voile et texte** : le voile sombre couvre les 30 % du bas ; le texte blanc a une ombre douce pour rester lisible au-dessus du voile.
- **Contenu** : apparaît en cascade (0,3 à 0,5 s après l'ouverture).
- **Pastilles des métiers** : verre dépoli blanc ; « Explorer le secteur » est en blanc plein.
- **Défilement automatique** : la barre de progression dure 5,5 s et sa fin ouvre le secteur suivant. Il se met en pause au survol, au focus, hors écran et quand l'onglet est caché. La barre garde sa durée en mouvement réduit.
- **Survol** : il ouvre le panneau après 140 ms, pour que traverser les panneaux avec la souris ne les ouvre pas tous ; uniquement avec une souris.
- **Accessibilité** : le panneau entier est un bouton avec `aria-expanded` ; le contenu des panneaux fermés est `inert`.

Vérifications :
- **Ordinateur (1440 px)** :
  - passage automatique de Santé à Pétrole et gaz ;
  - Tab puis Entrée ouvre « Agriculture et agrobusiness » ;
  - le focus passe ensuite au premier métier et le défilement se met en pause.
- **Tablette (768 px) et mobile (375 px)** :
  - aucun débordement ;
  - toucher ouvre le bandeau, et les autres se referment ;
  - `aria-expanded` et `inert` sont à jour.
- **Liens** : les 24 liens des panneaux (18 métiers et 6 secteurs) répondent 200.
- **Technique** : console sans erreur ; les 347 règles de `home.css` sont toutes lues.
