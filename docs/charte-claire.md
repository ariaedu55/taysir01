# Charte claire : images nettes, sans dégradé, sans fond bleu

Demande du 20 novembre 2026 : sur l'accueil, pas de voile bleu sur les images ; retirer toutes les couleurs en dégradé du site ; aucun élément sur fond bleu, sauf les boutons. Référence : l'ancien site, presque entièrement blanc et gris clair. Complète [DESIGN.md](DESIGN.md) et [refonte-immersive.md](refonte-immersive.md).

## Constat

- **Ancien site** : fonds blanc et gris clair (`#f6f8fb`, `#edf1f4`), pied de page clair, boutons rouges (`#f11515`), texte bleu nuit. Le bleu nuit n'apparaît que dans une illustration.
- **Nouveau site** :
  - **sections sur fond bleu nuit** : bandeau d'accueil, « parcours », « finale », pied de page, menu mobile, encadrés d'appel à l'action, chiffres de la présentation, panneau de connexion ;
  - **voiles dégradés sur les photos** : accueil, fiches pays, cartes de destinations, connexion ;
  - **36 dégradés** : boutons, pastilles d'icônes, verre, halos, barres de progression ;
  - **petits éléments sur fond bleu** : numéros d'étapes, avatars, pastilles.

## Règles

- **Photos sans voile** : l'image s'affiche nette et claire. Le texte qui la recouvre est posé sur un panneau blanc (verre clair) pour rester lisible.
- **Aucun dégradé de couleur.** Restent seulement les masques de fondu des bandeaux de logos, qui ne colorent rien.
- **Fonds** : blanc ou gris clair (`--surface: #f6f8fb`, `--surface-2: #edf1f4`). Le bleu nuit reste la couleur du texte.
- **Boutons** : rouge uni pour l'action principale ; blanc bordé pour les autres. Les boutons de filtre ou de navigation actifs peuvent rester bleu nuit (ce sont des boutons).
- **Pastilles d'icônes** : fond rouge très clair (`--red-wash`) et icône rouge. Numéros et avatars : fond gris clair, texte bleu nuit.
- **E-mails** : en-tête blanc au lieu du bandeau bleu.

## Plan

1. Accueil : bandeau sans voile, textes et carte « destination à la une » sur panneaux clairs ; sections « parcours » et « finale » claires ; cartes de destinations sans voile.
2. `app.css` : boutons unis, verre clair sans dégradé, halo retiré, en-tête toujours clair, menu mobile et pied de page clairs, variantes « sur fond sombre » transformées en variantes claires.
3. Autres feuilles (`destination`, `content`, `auth`, `portal`, `agency`) : bandeaux photo, encadrés d'appel à l'action, pastilles, numéros, panneau de connexion, vidéo.
4. Vérification : aucun `gradient(` coloré ni fond bleu restant (recherche dans le CSS), puis contrôle visuel des pages principales, bureau et mobile.
5. Résultat ici, mise à jour de `DESIGN.md` et de la mémoire.

## Résultat (20 novembre 2026)

**Accueil**

- **Bandeau** : photo nette, sans voile (bloc `.hero-veil` retiré de la vue et du CSS). Le titre, le texte et les boutons sont sur un panneau blanc translucide (`.hero-copy`), comme la carte « Destination à la une ». Les pays sont sur une barre blanche.
- **Les autres sections deviennent claires** :
  - « Deux parcours » : arche grise derrière l'étudiant ;
  - « Votre parcours, étape par étape » : fond gris clair, cartes blanches, ligne et numéro actif rouges ;
  - destinations : photos sans voile, nom du pays sur une étiquette blanche ;
  - pastilles des secteurs rouge pâle ;
  - appel final sur gris clair.

**Commun** (`app.css`)

- **Boutons et verre** :
  - bouton principal rouge uni, sans reflet ;
  - verre blanc uni, `.glass-dark` aligné sur `.glass` ;
  - halo qui suivait le pointeur retiré.
- **En-tête toujours blanc**, logo bleu nuit, même sur une photo.
- **Menu mobile et pied de page clairs** : le pied de page est gris clair avec le logo bleu nuit, comme l'ancien site.
- **Nouveaux éléments** : jetons `--surface` (`#f6f8fb`) et `--surface-2` (`#edf1f4`), et panneau `.hero-panel` pour le texte posé sur une photo.

**Autres pages**

- Fiches pays et universités : photo nette, contenu sur `.hero-panel` (vues `destination.php` et `university.php`).
- Connexion, inscription et mot de passe : photo nette, carte claire avec logo bleu nuit.
- Encadrés d'appel à l'action (`.country-cta`) : fond gris clair bordé. `.outline-light` est devenu un bouton blanc bordé.
- Chiffres de « Qui sommes-nous ? » : fond gris clair, trait rouge.
- Vidéo : cadre gris clair, bouton de lecture rouge.
- Pastilles d'icônes (modules, piliers, contact, secteurs, étapes) : rouge pâle. Numéros, avatars, types de documents, icônes d'état : gris clair, texte bleu nuit.
- E-mails : en-tête blanc au lieu du bandeau bleu.

Restent sur fond bleu nuit, parce que ce sont des boutons : l'état actif des filtres (`region-filter`, `sector-link`) et l'onglet courant du back-office.

**Vérifications**

- **Recherche dans le CSS** : plus aucun `gradient(` hors des 2 masques de fondu des bandeaux de logos.
- **Contrôle automatique de 21 pages**, en vérifiant les styles calculés de chaque élément : aucun fond bleu nuit hors boutons, aucun fond en dégradé. Pages couvertes :
  - accueil, destinations, Canada, universités, UCAD, après le bac, métiers, une fiche métier, « Quel métier ? » ;
  - blog, stages, salons internationaux, demande de visa, rendez-vous ;
  - agence, contact, FAQ, témoignages, connexion, inscription, mot de passe oublié.
- **Contrôle visuel** : accueil (bandeau, parcours, destinations, appel final, pied de page), fiche Canada, connexion et « Qui sommes-nous ? » sur ordinateur ; accueil et menu sur mobile, sans débordement.

## Mise à jour : bandeau d'accueil (20 novembre 2026)

À la demande de l'utilisateur, le bandeau d'accueil n'a plus de panneaux clairs : texte blanc directement sur la photo, avec un **voile noir léger et uni** (`.hero-veil`, noir à 35 %), ni bleu ni dégradé. Voir [polices.md](polices.md). Les autres bandeaux photo (pays, universités, connexion) gardent leur panneau clair.

## Exception : cartes photo avec voile sombre en bas (21 novembre 2026)

Demande explicite : sur les cartes photo, retirer le fond de l'étiquette, mettre le texte en blanc et ajouter un dégradé sombre limité aux 30 % du bas de l'image, pour un rendu premium. C'est la **seule exception à la règle « aucun dégradé »**, à conserver.

- **Concerné** : les cartes de destinations de l'accueil (`.dest-card`), et les partials `destination-cards` et `university-cards` (`.destination-card`), utilisés sur `/destination/`, en bas des fiches pays et universités, et sur `/universites/`.
- **Voile** (`::after`, sous le texte) : `linear-gradient(to top, #000000a3 0%, #00000066 38%, #0000001f 72%, #00000000 100%)`, sur `height: 30%` seulement. Noir neutre, jamais bleu ; le reste de la photo reste net et clair.
- **Étiquettes sans fond** (classe `glass` retirée des vues), texte blanc avec une légère ombre ; région ou ville en blanc à 85 %, nom en Prata. Flèche de l'accueil : cercle blanc transparent, rouge au survol.
- **Universités** (3 lignes) : nom complet sur une ligne (« … » si trop long), lignes resserrées. Sur téléphone, le nom complet est masqué (cartes trop étroites) ; il reste sur la fiche.
- **Hauteur minimale** des cartes (14rem, 16rem pour les universités) pour que le texte tienne dans les 30 % du voile.

Vérifié par mesure (part de la carte occupée par le texte, à 1920, 1440, 1280, 1024, 768 et 375 px) :

| Page | Part maximale |
| --- | --- |
| Accueil | 29 % |
| `/destination/` | 28 % |
| `/universites/` | 28 % |
| `/aller-au-canada/` (autres destinations) | 28 % |
| `/ucad/` (autres universités) | 28 % |

Le texte reste donc toujours sur la partie assombrie. Les deux feuilles concernées sont lues en entier par le navigateur.

### Mise à jour : texte plus grand, voile plus visible (21 novembre 2026)

Retour de l'utilisateur : texte trop petit, dégradé presque invisible.

- **Voile renforcé**, toujours sur les 30 % du bas : `linear-gradient(to top, #000000d9 0%, #000000a6 30%, #00000059 62%, #00000014 88%, #00000000 100%)`. Noir à 85 % tout en bas, au lieu de 64 %.
- **Texte agrandi** : continent ou ville à 0,92–0,95rem ; nom en Prata, jusqu'à 2,15rem sur l'accueil, 1,9rem pour les pays, 1,7rem pour les universités ; nom complet des universités à 0,86rem. Mesuré sur ordinateur : 34 px sur l'accueil, 30 px pour les pays, 27 px pour les universités (contre environ 23 et 21 px avant).
- **Noms sur une ligne** : la taille suit la largeur de la carte (unités de conteneur, `container-type: inline-size` sur la carte, `min(…, 10,5–11cqi)`). « Arabie saoudite » et « Royaume-Uni » tiennent donc sur les cartes étroites. La petite ligne du haut est coupée par « … » si besoin (« Enseignement en ligne »).
- **Accueil** : la flèche passe sur la ligne du continent ; le nom prend toute la largeur.
- **Hauteur minimale** des cartes portée à 17rem (20rem pour les universités), avec `width: 100%`. Sans cela, le ratio d'image élargissait la carte au-delà de sa colonne : les cartes se chevauchaient. Le défaut a été vu et corrigé pendant la vérification.

Vérifié à 1920, 1440, 1280, 1024, 768 et 375 px, sur l'accueil, `/destination/`, `/universites/`, `/aller-au-canada/` et `/ucad/` :
- le texte occupe au plus 27 % du bas des cartes, donc reste sur la partie assombrie ;
- aucun nom coupé, aucune carte plus large que sa colonne ;
- feuilles CSS lues en entier.
