# Destinations et cartes : un univers de voyage

Demande du 21 novembre 2026 :
- rendre la page `/destination/` élégante, moderne et époustouflante ;
- faire de même pour toutes les pages qui affichent leurs éléments en simples « briques ».

On doit se sentir dans un univers de voyage et de découverte, digne de la figure publique de l'orientation au Sénégal. Carte blanche.

## Existant

| Composant | Pages | Rendu actuel |
| --- | --- | --- |
| `partials/destination-cards.php` (`.destination-card`) | `/destination/`, « autres destinations » des fiches pays | photo et nom, grille uniforme |
| `partials/university-cards.php` (même style) | `/universites/` | photo, sigle, nom |
| `partials/article-cards.php` (`.article-card`) | métiers, secteurs, blog, formations, articles liés | carte blanche : image, catégorie, titre, résumé |
| `.hub-cards` | après le bac, orientation, blog (familles) | carte blanche : icône, titre, texte |

La page `/destination/` se limite à un titre, un paragraphe et une grille de 12 photos.

## Conception

Les contraintes de la charte claire restent (`charte-claire.md`) :
- blanc et gris clair, texte noir, pas de texte rouge ;
- aucun fond bleu hors boutons ;
- pas de dégradé de couleur, sauf le voile des 30 % du bas des photos ;
- Prata, écriture manuscrite et Poppins.

### 1. `/destination/` : « Départ de Dakar »

**Bandeau en deux colonnes :**
- **À gauche** :
  - le titre ;
  - le texte ;
  - trois chiffres clés : nombre de pays, nombre de régions et la destination la plus lointaine, en km.
- **À droite, un radar « Vos destinations vues depuis Dakar »** :
  - **Position des pays** : Dakar est au centre. Chaque pays est placé selon sa direction réelle depuis Dakar (cap initial) et sa distance réelle (rayon proportionnel à la racine de la distance). Des cercles repères indiquent 2 000, 5 000 et 10 000 km.
  - **Animation** : des arcs en pointillés se dessinent vers chaque pays. Toutes les 2,4 s, une destination s'allume : son arc devient rouge, son nom et sa distance s'affichent, et un petit avion (`avion-route.webp`) parcourt l'arc.
  - **Lien avec les cartes** : survoler ou atteindre une carte au clavier allume sa destination sur le radar.

**Cartes « carte d'embarquement » :**
- **Haut** : la photo, avec le drapeau et le nom du pays sur le voile.
- **Talon** : séparé par une ligne de perforation et deux encoches. Il affiche :
  - la route `DSS ✈ CDG` en grands caractères, avec les villes en dessous ;
  - la distance à vol d'oiseau, calculée, et la région ;
  - une flèche.
- **Survol** :
  - la carte s'incline légèrement en 3D selon la souris ;
  - la photo zoome ;
  - l'avion du talon traverse la ligne ;
  - la flèche tourne.

**Données ajoutées :**
- dans `config/destinations.php`, l'aéroport principal de chaque pays : code IATA, ville et coordonnées ;
- dans `Destination`, `distanceKm()` (formule de haversine) et `bearing()` depuis l'aéroport de Dakar (DSS).

### 2. Cartes d'articles (métiers, blog, formations)

- **Photo** encadrée dans la carte (marge intérieure, coins arrondis), qui zoome au survol.
- **Catégorie** dans une pastille claire avec l'icône du secteur.
- **Bas de carte** : une ligne « Lire la fiche », avec une flèche ronde qui devient rouge et pivote au survol.
- **Survol** : légère inclinaison 3D et ombre plus profonde.

### 3. Cartes de rubriques (hub)

- **Numéro** « 01 », « 02 » en Prata à côté de l'icône.
- **Icône en filigrane** : une grande icône très pâle dans le coin.
- **Flèche ronde** en bas de carte.
- **Survol** : la pastille d'icône devient rouge pleine, la carte monte et s'incline légèrement.

### 4. Universités

La carte photo existante est conservée, avec :
- l'inclinaison 3D au survol ;
- un liseré blanc intérieur ;
- une flèche ronde en verre ;
- le nom complet lisible.

### 5. Commun

- **Inclinaison 3D** : `[data-tilt]` dans `app.js`, limitée à 5°. Elle ne s'active qu'avec une souris, est désactivée en mouvement réduit, et n'utilise que `transform`.

## Plan

1. Données : aéroports dans `config/destinations.php`, calculs dans `Destination`.
2. `/destination/` : bandeau et radar (`pages/destinations.php`), cartes d'embarquement (`partials/destination-cards.php`), styles (`destination.css`), script du radar (`js/destinations.js`).
3. Cartes d'articles et de rubriques (`article-cards.php`, `content.css`).
4. Universités (`university-cards.php`, `destination.css`).
5. Inclinaison 3D (`app.js`).
6. Vérifications : ordinateur, tablette, mobile ; clavier ; feuilles lues en entier ; fluidité.

## Résultat (21 novembre 2026)

### Fichiers

- `config/destinations.php` : clé `airport` pour les 12 pays.

  | Code | Ville | Code | Ville |
  | --- | --- | --- | --- |
  | YUL | Montréal | IST | Istanbul |
  | CDG | Paris | CMN | Casablanca |
  | LHR | Londres | TUN | Tunis |
  | BRU | Bruxelles | JED | Djeddah |
  | LIS | Lisbonne | PEK | Pékin |
  | SVO | Moscou | HND | Tokyo |

- `app/Models/Destination.php` : `ORIGIN` (Dakar, DSS), `distanceKm()`, `bearing()` et `distanceLabel()`.
- `app/Controllers/DestinationController.php` : script `js/destinations.js`. Les fiches pays proposent 3 autres destinations, soit une rangée complète.
- `app/Views/pages/destinations.php` :
  - bandeau en deux colonnes : texte et chiffres clés (12 pays, 5 régions, 13 920 km jusqu'à Tokyo), radar SVG calculé en PHP ;
  - les cartes de la liste.
- `app/Views/partials/destination-cards.php` : cartes d'embarquement (`.pass-card`), utilisées par `/destination/` et par les fiches pays.
- `app/Views/partials/article-cards.php` : ligne « Découvrir le métier » ou « Lire la suite » avec une flèche ronde.
- `app/Views/partials/university-cards.php` : flèche en verre.
- `public/assets/css/destination.css` :
  - bandeau, radar et cartes d'embarquement ;
  - cartes universités (liseré, flèche, inclinaison) ;
  - grille des universités à 4 colonnes au lieu de 5.
- `public/assets/css/content.css` : cartes d'articles et de rubriques réécrites.
  - Les rubriques sont numérotées automatiquement par un compteur CSS, et leur flèche est faite en CSS : aucune page n'a été modifiée.
- `public/assets/js/destinations.js` (nouveau) : radar.
- `public/assets/js/app.js` : inclinaison 3D des cartes (`.pass-card`, `.article-card`, `.hub-card`, `.destination-card`, `[data-tilt]`). Elle ne s'active qu'avec une souris et est désactivée en mouvement réduit.

### Distances calculées depuis Dakar (DSS)

| Pays | Distance | Pays | Distance |
| --- | --- | --- | --- |
| Maroc | 2 290 km | Turquie | 5 300 km |
| Portugal | 2 790 km | Arabie saoudite | 5 960 km |
| Tunisie | 3 660 km | Canada | 6 270 km |
| France | 4 220 km | Russie | 6 490 km |
| Angleterre | 4 350 km | Chine | 12 270 km |
| Belgique | 4 470 km | Japon | 13 920 km |

### Pages touchées

Toutes avec les mêmes composants :
- destinations et fiches pays ;
- universités ;
- métiers, pages secteurs, blog, formations et articles liés ;
- après le bac, orientation et activités (cartes de rubriques).

### Vérifications

- **Rendu** :
  - `/destination/` à 1440 px (3 colonnes de cartes d'embarquement, radar) et à 375 px (1 colonne, radar sous le texte), sans débordement ;
  - fiche France (3 suggestions) ;
  - `/metiers/`, `/apres-le-bac/` et `/universites/`.
- **Survol** : la carte s'incline et monte, la flèche devient rouge et pivote, et l'avion traverse la route du talon.
- **Radar** : la première destination s'allume après le dessin des arcs, puis une nouvelle toutes les 2,6 s. Le survol d'une carte allume son pays.
- **Fluidité de `/destination/`** : 90 images/s sur 3 s, aucune saccade de plus de 25 ms.
- **Technique** : `app.css` (166), `destination.css` (167) et `content.css` (113) lus en entier ; console sans erreur ; les pages répondent 200.
