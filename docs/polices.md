# Polices et bandeau d'accueil

Demande du 20 novembre 2026 :
- dans la première section, retirer les fonds autour des éléments et passer le texte en blanc, avec un léger fond noir pour qu'il ressorte ;
- utiliser pour le titre principal et les titres la police spéciale de l'ancien site (capture « Allez en ziarah à La mecque / Arabie Saoudite ») ;
- utiliser Poppins pour tous les autres textes.

Complète [charte-claire.md](charte-claire.md).

## Polices de l'ancien site

Lues dans les fichiers de police du thème WordPress Zourney (table des noms), sans les copier :

| Rôle sur l'ancien site | Nom dans le thème | Police réelle | Auteur |
| --- | --- | --- | --- |
| Titres (« Arabie Saoudite », « Canada ») | Zourney Heading | **Giliams** | Brandsemut, 2021 |
| Ligne manuscrite (« Allez en ziarah à La mecque ») | Zourney Sub | **Malisso** | Inspirasign, 2019 |
| Texte | Jost | Jost (Google Fonts) | — |

Giliams et Malisso sont des **polices commerciales** fournies avec le thème payant : leur licence couvre en général l'usage avec ce thème, pas leur reprise sur un autre site. Elles ne sont donc pas copiées.

## Choix

Des équivalents gratuits (Google Fonts), comparés côte à côte avec la capture :

- **Titres** : **Prata**, une serif de contraste élevé et de graisse normale, au plus près de Giliams.
- **Mots d'accent des titres et ligne qui tape le pays** : **Mrs Saint Delafield**, une écriture manuscrite fine, au plus près de Malisso. Comme sur l'ancien site, la police manuscrite accompagne la police de titre (« Votre avenir s'écrit » puis « *au Canada* » en manuscrit).
- **Texte, boutons, menus, formulaires** : **Poppins** (400 à 800, italique 400 et 500).

Pour passer aux polices d'origine, une fois la licence web achetée par le client :
1. déposer les fichiers dans `public/assets/fonts/` ;
2. ajouter les `@font-face` correspondants ;
3. modifier `--font-display` et `--font-script` dans `app.css`.

Rien d'autre n'est à changer.

## Réalisation

- **Lien Google Fonts** dans `app/Views/layouts/main.php` : Prata, Mrs Saint Delafield, Poppins.
- **`app.css`** :
  - variables `--font-display` (Prata), `--font-script` (Mrs Saint Delafield) et `--font-body` (Poppins) ;
  - titres en graisse 400 (Prata n'en a qu'une) ;
  - règle commune `:is(h1, h2) :is(em, .accent)` : le mot d'accent des titres passe en manuscrit, 1,32 fois plus grand ;
  - `font-synthesis: none` : pas de faux gras ni de fausse italique.
- **Menu** : liens à 0,88rem et bouton « Rendez-vous » sur une ligne (Poppins est plus large que Plus Jakarta Sans).
- **Bandeau d'accueil** (`home.css`, `home.php`) :
  - le panneau blanc derrière le titre et la carte « Destination à la une » sont retirés, tout comme les fonds de la barre des pays et de « Découvrir » ;
  - le texte est blanc, avec une ombre très légère ;
  - **voile noir léger et uni** sur la photo (`.hero-veil`, noir à 35 %) : ni dégradé, ni bleu ;
  - bouton « Explorer les destinations » blanc transparent ;
  - ligne qui tape le pays en manuscrit.
  - Les bandeaux des pages pays et universités gardent leur panneau clair : la demande ne portait que sur l'accueil.
- **Correction** : un rebut de l'étape précédente (`color: var(--accene;`, parenthèse non fermée) faisait ignorer la moitié de `destination.css` : pages destinations sans mise en page. Corrigé. Une vérification parse maintenant chaque feuille dans le navigateur et compare le nombre de règles lues au nombre attendu : identiques pour les 7 feuilles.

## Vérifications

- Les 3 polices sont chargées.
- Accueil sur ordinateur (1280 px) et sur mobile (375 px) : photo nette sous un voile noir léger, texte blanc lisible, aucun fond autour des éléments.
- Menu sans débordement à 1000, 1100 et 1280 px.
- Page Destinations : titre en Prata, « étudier ? » en manuscrit.
- Aucun titre ne déborde sur mobile (8 pages contrôlées).

## Mise à jour : bandeau centré (20 novembre 2026)

Demande : retirer le paragraphe et les boutons du diaporama, agrandir le titre principal et le centrer.

- **Retirés** (`home.php`) : le paragraphe « De l'orientation au premier jour… » et les boutons précédent, pause et suivant.
- **Titre** :
  - centré horizontalement et verticalement dans le bandeau ;
  - plus grand : jusqu'à 6,6rem sur ordinateur, 2,6 à 3,5rem sur mobile ;
  - pastille « Orientation · Études · Voyage » et boutons centrés dessous.
- **Carte « Destination à la une »** : déplacée dans la barre du bas, à droite et alignée à droite (centrée sur mobile). La liste des pays tient sur une ligne : chaque libellé prend sa largeur naturelle.
- **Diaporama** (`home.js`) :
  - il défile seul et le script fonctionne sans les boutons ;
  - pour rester accessible (tout contenu qui défile seul doit pouvoir être arrêté), il se met en pause tant que la souris ou le focus clavier est sur le bandeau ;
  - il reste arrêté si la personne demande moins de mouvement ;
  - un clic sur un pays l'affiche.
- **Mobile** : la ligne manuscrite passe à 1,42 fois la taille du titre, pour que le pays le plus long (« au Royaume-Uni ») tienne sur un écran de 375 px.

Vérifié :
- sur ordinateur (1280 px) et sur mobile (375 px) ;
- les 7 pays tiennent sur mobile ;
- aucune erreur dans la console ;
- le diaporama avance seul et se met en pause au focus.

## Mise à jour : bandeau épuré et animé dès l'arrivée (20 novembre 2026)

Demande : titre rééquilibré, barre de navigation intégrée à la photo, animations déclenchées dès l'arrivée, plus de pause, plus de pastille « Orientation · Études · Voyage », seulement l'essentiel.

- **Titre** : « Votre avenir s'écrit » réduit (0,56 fois la taille du titre) ; la ligne manuscrite du pays domine.
- **Pastille retirée** (`home.php`).
- **Barre de navigation sur le bandeau** (`home.css`, accueil uniquement) :
  - sans fond ni ombre, avec le logo blanc, le texte blanc et une ombre de texte légère, comme le titre ;
  - elle redevient la barre blanche dès qu'on quitte le bandeau (classe `is-on-hero`, déjà gérée par `app.js`) et quand le menu mobile est ouvert ;
  - les autres pages ne changent pas.
- **Animations dès l'arrivée** (`home.js`) :
  - la ligne du pays est vidée puis tapée à 450 ms ;
  - le diaporama change toutes les 4 secondes dès le chargement.
  - Pourquoi il ne démarrait pas :
    - la pause au survol ajoutée à l'étape précédente : arriver avec la souris sur la page suffisait à le bloquer ;
    - le réglage système « moins d'animations », souvent coupé sous Windows, l'arrêtait complètement ;
    - le premier pays était déjà écrit dans la page, donc rien ne se tapait.
- **Plus aucune pause** : ni bouton, ni survol, ni focus, ni réglage « moins d'animations » (demande explicite de l'utilisateur). Le diaporama ne s'arrête que si l'onglet est caché ou si le bandeau est hors de l'écran. Sous « moins d'animations », la barre des pays garde sa durée de 4 secondes (règle déjà en place), le fondu entre photos devient instantané et la machine à écrire tape normalement.
- **Restent dans le bandeau** : le titre, les deux boutons (ordinateur), la liste des pays, la carte « Destination à la une » et « Découvrir ».

Vérifié :
- à l'arrivée sur la page, sans aucune interaction : pays tapé (« au Canada » vers 1 s), changement de photo vers 4 s (« en France »), aucune pause ;
- en-tête transparent sur le bandeau, blanc en dehors, transparent au retour en haut ;
- mobile 375 px sans débordement.

## Mise à jour : tailles du bandeau sur PC, tablette et mobile (21 novembre 2026)

Demande :
- boutons du bandeau trop encombrants ;
- titre trop proche de la barre de navigation sur PC ;
- sur tablette, titre trop petit et boutons trop gros ;
- vérifier l'ergonomie sur mobile.

**Constat (mesures dans le navigateur)**

- **Portables** (1366×657, 1536×730) : le titre ne suivait que la largeur de l'écran. Il devenait trop grand pour la hauteur disponible, remontait vers le menu, et les boutons chevauchaient la barre des pays de 56 px.
- **Tablette** (768×1024) : la première ligne du titre ne mesurait que 30 px. Les boutons faisaient 232×49 px.
- **Mobile** : le bouton du menu ne mesurait que 38×38 px, sous les 44 px conseillés pour le doigt.

**Corrections** (`home.css`, `app.css`)

- **Titre** :
  - sur ordinateur, `clamp(2.6rem, min(7vw, 9vh), 6rem)` : il suit la largeur *et* la hauteur de l'écran ;
  - sur tablette, `clamp(3.2rem, 9vw, 5.4rem)` : plus grand ;
  - sur mobile, la taille ne change pas.
- **Espace sous le menu** : `padding-top: calc(var(--header-h) + clamp(3rem, 10vh, 7rem))` sur ordinateur, `clamp(3rem, 8vh, 6rem)` sur tablette.
- **Boutons du bandeau compacts** : 42 px de haut, texte à 0,88rem, flèche réduite, écart de 0,6rem.
- **Portables peu hauts** (hauteur ≤ 780 px) : la carte « Destination à la une » perd sa phrase descriptive et la barre du bas se resserre, pour laisser respirer le titre.
- **Mobile** : bouton du menu à 44×44 px.

**Résultat mesuré**

| Écran | 1re ligne / pays | Menu → titre | Boutons → barre des pays |
| --- | --- | --- | --- |
| 1920×969 | 49 / 148 px | 267 px | 85 px |
| 1536×730 | 37 / 112 px | 200 px | 43 px |
| 1366×657 | 33 / 101 px | 172 px | 22 px |
| 1280×800 | 40 / 122 px | 200 px | 36 px |
| 1024×768 | 39 / 118 px | 136 px | 85 px |
| 768×1024 (tablette) | 38 / 115 px | 375 px | 206 px |

Boutons : 213×42 px sur ordinateur, 200×42 px sur tablette (contre 247×52 et 232×49 avant). Le pays le plus long (« au Royaume-Uni ») ne déborde à aucune taille.

**Ergonomie mobile vérifiée** (360×740, 375×812, 390×664, 430×932)

- aucun débordement horizontal ;
- bouton du menu à 44×44 px ;
- titre centré ;
- carte du pays visible en bas de l'écran une fois son animation d'entrée jouée ;
- toutes les zones cliquables d'au moins 44 px, sauf le logo (88×31 px, assez large pour le doigt).

Les boutons du bandeau restent masqués sur téléphone (choix précédent de l'utilisateur) : la prise de rendez-vous passe par le menu.

Validé le 21 novembre 2026.
