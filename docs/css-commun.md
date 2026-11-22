# Séparation des styles communs et de l'accueil

## Plan (20 novembre 2026)

`public/assets/css/home.css` contenait à la fois les styles communs à toutes les pages et ceux propres à l'accueil.

- `public/assets/css/app.css` : variables de couleur, base typographique, utilitaires (`.wrap`, `.sr-only`, `.skip`), en-tête et menu, boutons, `.glass`, `.actions`, structure des sections (`.section`, `.section-head`, `.section-label`), pied de page et crédits, avec leurs règles responsives et d'animation.
- `public/assets/css/home.css` : uniquement les sections de l'accueil (diaporama, introduction, secteurs, galeries, témoignages, actualités, écoles, avis, partenaires, clôture).
- Le gabarit charge toujours `app.css`, puis les feuilles propres à la page.
- Vérification : comparer les styles calculés de tous les éléments de l'accueil avant et après, à la largeur du navigateur intégré (1 024 px) et à 375 px.

## Résultat

| Fichier | Contenu |
| --- | --- |
| `public/assets/css/app.css` (119 lignes) | Variables `:root`, base (`body`, titres, liens, focus, images), `.wrap`, `.sr-only`, `.skip`, en-tête (`.header`, `.brand`, `.site-menu`, `nav`, `.nav-group`), boutons (`.button`, `.outline`, `.primary`, `.light`), `.actions`, `.glass`, sections (`.section`, `.section-head`, `.section-label`), pied de page (`.footer`, `.photo-credits`). S'y ajoutent l'animation d'entrée `enter-section` et le survol des boutons, le menu mobile sous 68rem, les ajustements sous 52rem et 24rem, et le défilement sans animation si l'utilisateur le demande. |
| `public/assets/css/home.css` (135 lignes) | Diaporama, introduction et parcours, secteurs, galeries et destinations, témoignages, actualités, écoles, avis, partenaires, clôture, avec leurs animations et règles responsives. Utilise l'animation `enter-section` définie dans `app.css`. |
| `app/Views/layouts/main.php` | Charge toujours `app.css`, puis les feuilles de `$styles`. |

Les règles qui mélangeaient sélecteurs communs et sélecteurs de l'accueil ont été scindées : graisse des liens de `.section-head`, `.paths` et `.review`, transitions et survol des boutons, animation d'entrée, colonnes uniques sous 52rem. L'ordre de chargement (`app.css` puis `home.css`) conserve la priorité des règles de l'accueil sur les règles communes, par exemple `.hero-link` sur `.button`.

### Vérifications

- Avant le découpage, les styles calculés des 488 éléments de l'accueil ont été enregistrés (hors propriétés animées), avec leurs dimensions, à 1 024 px et à 375 px (émulation mobile). Après le découpage : **0 différence** aux deux largeurs.
- Le navigateur charge `app.css` puis `home.css`.
- Toutes les classes de `layouts/main.php` et des partials sont définies dans `app.css`. Une nouvelle page peut donc se passer de `home.css`.

### Pour une nouvelle page

Ne pas ajouter `css/home.css` à `$styles`. Créer si besoin une feuille propre à la page (par exemple `css/contact.css`), qui peut utiliser les variables de couleur (`--ink`, `--muted`, `--action`, `--accent`, `--paper`, `--line`, `--red-wash`) et les classes communes.

L'exemple de [gabarit-partials.md](gabarit-partials.md), qui chargeait `css/home.css` pour la page Contact, est remplacé par cette règle.
