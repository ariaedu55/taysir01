# Identité visuelle de Taysir Orientation Voyage

## Direction

L’accueil s’adresse aux élèves, étudiants et voyageurs qui cherchent un conseil ou une destination. Il reprend le rythme de l’accueil WordPress, qui ouvre sur les destinations et enchaîne avec l’accompagnement, les secteurs, la galerie, les témoignages et les actualités. Chaque ouverture associe une photo de ville, un titre centré et un drapeau. Les sections suivantes alternent portrait éditorial, panneaux de verre, galerie photographique et liste d’actualités.

## Couleurs

| Couleur | Valeur | Usage |
| --- | --- | --- |
| Bleu marine | `#041B51` | Texte principal et éléments d’identité |
| Rouge Taysir | `#F11515` | accents visuels |
| Rouge d’action | `#CE0F0F` | Boutons et sélection active avec texte blanc |
| Gris bleuté | `#516078` | Texte secondaire |
| Ivoire | `#FCFBF8` | Fond principal |
| Blanc | `#FFFFFF` | Panneaux, commandes et texte sur la photo |

Les couleurs de marque viennent du [kit Elementor](https://taysir-orientation.com/wp-content/uploads/elementor/css/post-73.css) et des [styles de l’accueil WordPress](https://taysir-orientation.com/wp-content/uploads/elementor/css/post-670.css). Les variables de `public/assets/css/home.css` portent les couleurs de l’application. Les titres utilisent Bahnschrift lorsqu’elle est disponible. Le texte courant et les contrôles utilisent Segoe UI ou la police système.

## Mouvement et adaptation

Le diaporama propose un fondu photo, un léger zoom et une arrivée échelonnée du texte. La lecture avance toutes les 6,5 secondes lorsqu’elle est active. Une sélection manuelle la suspend. Le bouton de lecture permet de la relancer. Le champ visuel reste stable pendant les transitions. Le défilement automatique est désactivé au chargement en cas de préférence de mouvement réduit. Les panneaux translucides ont un fond blanc de secours si le flou d’arrière-plan est indisponible.

## Refonte immersive (20 novembre 2026)

La direction actuelle est décrite dans [refonte-immersive.md](refonte-immersive.md). En résumé :

- Sections en **blanc pur `#FFFFFF`** ou gris clair (`#f6f8fb`), plus aucune section marine depuis la charte claire ; le marine `#041B51` sert au texte et le rouge `#F11515` / `#CE0F0F` aux boutons, traits et icônes.
- Panneaux de verre clair (`.glass` ; `.glass-dark` est devenu identique), boutons en pilule, bouton principal **rouge uni**.
- **Charte claire** ([charte-claire.md](charte-claire.md)) : aucun dégradé de couleur, aucun élément sur fond bleu sauf les boutons, fonds blancs ou gris clair (`--surface`, `--surface-2`), photos nettes sans voile ; le texte posé sur une photo va sur un panneau clair (`.hero-panel`).
- Motif de la traînée rouge et de l’avion du logo, repris en trajectoires animées.
- Polices ([polices.md](polices.md)) : **Prata** pour les titres (graisse 400), **Mrs Saint Delafield** (manuscrite) pour le mot d'accent des titres (`em`, `.accent`), **Poppins** pour tout le reste. Équivalents gratuits de Giliams et Malisso, les polices commerciales de l'ancien site. **Jamais de dégradé de couleur sur le texte**, uniquement des couleurs pleines.
- Titres animés en machine à écrire (`data-typewriter`), avec un curseur rouge.
- Animations par attributs (`data-reveal`, `data-split`, `data-words`, `data-parallax`…), toutes désactivées si l’utilisateur demande moins de mouvement.
- Le [parcours de vol](accueil-parcours.md) est commun à l’accueil et aux fiches pays et universités. La route sinueuse, les stations rouges et les tampons relient les six cartes illustrées. Deux avions détourés matérialisent la progression sur la jauge DKR et entre les escales. Les images passent au-dessus du texte lorsque les deux colonnes deviennent trop étroites.
- Le [survol du bandeau](accueil-survol.md) dévoile la première destination à travers des nuages. Un passage plus discret accompagne le retour au bandeau et un petit avion suit la ligne rouge.
- Les [fiches d’universités et d’écoles](etablissements-decouverte.md) s’ouvrent sur une illustration adaptée à leur domaine, sous une navigation transparente. Un voile noir uni soutient le texte blanc. Sur fond blanc, la présentation reprend le visuel d’origine et donne accès aux rubriques et aux démarches.
- Les [destinations](destinations-decouverte.md) reprennent ce bandeau photographique avec des panoramas en paysage et portrait. L’annuaire conserve le radar et les cartes d’embarquement. Sur les fiches, le repère de vol indique l’aéroport de référence et la distance à vol d’oiseau.
- Les [rubriques Offres et Activités](offres-activites.md) utilisent le même bandeau. Chaque sujet possède une illustration dédiée et un lien vers le contenu de la page.
- Sur téléphone et tablette, la [navigation](navigation-mobile.md) associe un accès illustré aux destinations, des rubriques en accordéon et des actions de compte et de rendez-vous visibles pendant le défilement.

## Espaces client et équipe

Poppins porte l’interface connectée. Les cartes et navigation utilisent des surfaces unies et des touches de couleur par fonction. Les boutons lisent les jetons `--btn-base`, `--btn-ok`, `--btn-warn` et `--btn-danger` dans `public/assets/css/app.css`. Les indicateurs placent le nombre à droite de l’icône et le libellé dessous. Les styles de l’équipe sont dans `public/assets/css/gestion.css`. L’espace client connecté est délimité par `.client-workspace` dans `public/assets/css/gestion-client.css`. Les formulaires et le suivi s’appuient sur `portal.css`. Voir le [contrat et la recette de phase 10](gestion-phase-10.md).
