# Accueil : section « Le monde est votre campus »

Demande du 21 novembre 2026 : remplacer le défilement horizontal épinglé par un **carrousel en profondeur**.
- Une carte au centre, devant, et deux cartes de chaque côté, un peu en retrait derrière elle.
- En avançant, la carte de droite prend la place du centre et celle du centre part à gauche, et ainsi de suite.
- Deux boutons discrets pour le piloter.
- Pilotage aussi au défilement horizontal (gauche-droite).
- Même disposition sur tablette et mobile, en plus petit et plus resserré.

Cartes : celles de [charte-claire.md](charte-claire.md) (photo nette, voile sombre sur les 30 % du bas, texte blanc).

## Avant

La section était épinglée : en faisant défiler la page, la rangée de 12 cartes glissait de droite à gauche (`data-pin`, `home.js`), avec une barre de progression. Sur tablette et mobile, simple rangée défilante.

## Conception

- **Scène** (`.coverflow`) : les 12 cartes sont superposées au centre. Le script donne à chacune sa distance circulaire à la carte active (`--o` de −2 à 2, `--a` sa valeur absolue).
- **Position** : décalage horizontal, échelle (1 ; 0,8 ; 0,64), légère rotation en perspective (`rotateY`), assombrissement des côtés (`brightness`, pas de couleur) et ordre d'empilement. Les cartes au-delà de ±2 sont invisibles, juste derrière les côtés.
- **Transition** de 0,8 s : la carte de droite glisse au centre en grandissant, celle du centre recule à gauche, la plus à gauche disparaît, une nouvelle apparaît à droite. Le tout en boucle.
- **Pilotage** :
  - deux boutons ronds (précédent, suivant) et un compteur « 04 / 12 » sous la scène ;
  - défilement horizontal du pavé tactile ou de la molette avec Maj ;
  - glisser du doigt ou de la souris ;
  - flèches du clavier quand le carrousel a le focus ;
  - clic sur une carte de côté pour l'amener au centre (seule la carte du centre ouvre la fiche du pays).
- **Défilement automatique** toutes les 4,5 s, comme le bandeau. Il reprend 6 s après la dernière action, et s'arrête si la section est hors écran ou l'onglet caché.
- **Accessibilité** : cartes hors du centre retirées de la tabulation, pays annoncé à chaque changement, boutons nommés.
- **Responsive** : même disposition ; cartes plus petites (largeur liée à l'écran) et décalages réduits sur tablette et mobile, pour que les côtés restent visibles et resserrés.

## Plan

1. `home.php` : structure du carrousel (cartes existantes, boutons, compteur, annonce).
2. `home.css` : scène, positions, transitions, boutons, responsive ; retrait des styles de l'épinglage.
3. `home.js` : remplacement de l'épinglage par le carrousel.
4. Vérifications : bureau, tablette, mobile ; boutons, défilement horizontal, glisser, clavier, clic sur un côté, boucle ; feuille CSS lue en entier.

## Résultat (21 novembre 2026)

Fichiers :
- `app/Views/home.php` : section réécrite, sans `data-pin` ni barre de progression ;
- `public/assets/css/home.css` : bloc « carrousel en profondeur » ; styles de l'épinglage retirés ;
- `public/assets/js/home.js` : le carrousel remplace le script d'épinglage.

Réglages :

| | Ordinateur | Tablette (≤ 62rem) | Mobile (≤ 34rem) |
| --- | --- | --- | --- |
| Largeur de la carte centrale | `clamp(15rem, 24vw, 24rem)` (345 px à 1440 px) | `clamp(13rem, 34vw, 17rem)` | `clamp(10rem, 47vw, 12.5rem)` |
| Décalage des côtés (1er / 2e) | 64 % / 112 % | 58 % / 100 % | 50 % / 86 % |
| Échelle (centre, 1er, 2e) | 1 ; 0,8 ; 0,64 | idem | idem |

Plus de détails :
- luminosité 1 ; 0,78 ; 0,6 ;
- rotation en perspective de 12° par rang (10° sur mobile) ;
- transition de 0,8 s ;
- flèche de la carte visible seulement au centre ;
- les cartes affichent désormais leur numéro en bleu nuit (la pastille blanche était vide depuis le passage au texte blanc).

Vérifications :
- **Ordinateur (1440 px)** :
  - 5 cartes visibles ;
  - boutons suivant et précédent ;
  - boucle (Canada, puis retour à Japon 12) ;
  - flèche droite du clavier ;
  - défilement horizontal (`deltaX`) ;
  - clic sur une carte de côté : elle vient au centre sans ouvrir la fiche.
- **Mobile (375 px)** :
  - même disposition, plus petite et plus resserrée, sans débordement ;
  - défilement automatique (01 puis 02 après 4,5 s) ;
  - glisser du doigt vers la gauche (04 puis 05) et vers la droite (retour à 04).
- **Tablette (768 px)** : même disposition intermédiaire, sans débordement.
- **Technique** : console sans erreur ; les 319 règles de `home.css` sont lues. Les seuls `gradient(` restants sont les masques des logos et le voile des cartes autorisé.

## Mise à jour : carrousel agrandi, numérotation retirée (21 novembre 2026)

Demande : agrandir encore le carrousel sur ordinateur et tablette, et retirer la numérotation jugée inutile.

Changements :
- `home.php` : retrait du compteur « 06 / 12 » sous la scène et des numéros sur les cartes (`.dest-index`) ;
- `home.js` : retrait de la mise à jour du compteur ;
- `home.css` : styles `.cover-count*` et `.dest-index` retirés ; cartes agrandies ; boutons un peu plus espacés.

| | Ordinateur | Tablette (≤ 62rem) | Mobile (≤ 34rem) |
| --- | --- | --- | --- |
| Largeur de la carte centrale | `clamp(17rem, 30vw, 30rem)` | `clamp(15rem, 44vw, 22rem)` | inchangé : `clamp(10rem, 47vw, 12.5rem)` |
| Décalage des côtés (1er / 2e) | 64 % / 112 % | 56 % / 96 % | inchangé : 50 % / 86 % |

Carte centrale mesurée :

| Écran | Carte (px) |
| --- | --- |
| 1920 × 969 | 480 × 640 |
| 1440 × 900 | 428 × 570 (avant : 345) |
| 1366 × 657 | 405 × 540 |
| 1024 × 768 | 303 × 404 |
| 768 × 1024 | 330 × 440 |
| 375 × 812 | 169 × 226 |

Vérifications :
- aucun débordement horizontal à toutes les tailles ; sur tablette, les cartes du 2e rang sont légèrement coupées par les bords de l'écran, ce qui est voulu ;
- plus aucun numéro dans la page ;
- carrousel fonctionnel (défilement automatique, boutons) ;
- console sans erreur ; `home.css` lue en entier (314 règles).
