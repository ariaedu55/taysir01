# Blog

Dernier lien du site encore servi par l'ancien WordPress : `/blog/` (« Toutes les publications » sur l'accueil). Voir [plan-migration.md](plan-migration.md).

## Constat (20 novembre 2026)

- Sur WordPress, `/blog/` est une page vide : le thème y affichait automatiquement la liste des articles.
- L'export contient 84 articles ; 81 sont déjà en base (`articles`) : 39 fiches métiers, 20 écoles, 19 offres (5 emplois, 5 stages, 5 formations, 4 bourses), 3 salons. Les 3 autres sont un test (`jjj`), une fiche partenaire vide (`edvantis`) et un doublon non publié ; ils restent écartés.
- Chaque article a déjà sa page à son adresse WordPress ([metiers-orientation.md](metiers-orientation.md)).

## Choix

- `/blog/` devient la page « Publications » : tous les articles, du plus récent au plus ancien (les archives de 2024 après les fiches à jour), filtrables par famille (métiers, écoles, offres et bourses, salons) et par recherche, avec le filtre déjà utilisé sur `/metiers/` (`content.js`, sans JavaScript tout reste affiché).
- Le partial `article-cards` accepte un critère de filtre (`filterBy` : secteur ou famille) ; les familles sont définies dans `App\Models\Article::FAMILIES`.
- Chaque famille renvoie aussi vers sa page dédiée (`/metiers/`, `/formations-profesionnelles/`, `/offre_stage/`, `/salon-collegien/`).

## Plan

1. `Article::FAMILIES` et `Article::all()` publiés ; option `filterBy` du partial.
2. `ArticleController::blog()`, vue `pages/blog.php`, route `/blog`.
3. Vérifications : page, filtres, recherche, menu sans lien vers l'ancien site, mobile.
4. Résultat ici, plan de migration et mémoire.

## Résultat (20 novembre 2026)

`/blog/` répond sur le nouveau site : **plus aucun lien du site ne pointe vers l'ancien WordPress** (vérifié sur l'accueil, `/metiers/`, `/destination/`, `/contact/`, `/offre_stage/`).

| Fichier | Rôle |
| --- | --- |
| `app/Models/Article.php` | `FAMILIES` (métiers, écoles, offres et bourses, salons, actualités), `family()`, `published()` |
| `app/Controllers/ArticleController.php` | `blog()` : articles et nombre par famille (familles vides masquées) |
| `app/Views/pages/blog.php` | Bandeau, rubriques vers les pages dédiées, filtre par famille et recherche, appel à l'action |
| `app/Views/partials/article-cards.php` | Option `filterBy` (`sector` par défaut, `family` pour le blog) ; `/metiers/` inchangé |
| `public/assets/css/content.css` | `.blog-families` (2 colonnes compactes sur mobile) |
| `routes/web.php` | `/blog` |

Vérifications : 81 publications (39 métiers, 20 écoles, 19 offres et bourses, 3 salons) ; filtres « Salons » 3, « Offres et bourses » 19 ; recherche « bourse » 4 ; message « aucune publication » ; filtre de `/metiers/` toujours par secteur (6) ; mobile sans débordement.

La famille « Actualités » est prête pour de futures publications (type `actualite`), masquée tant qu'elle est vide. Publier de nouveaux articles demandera un écran de rédaction dans le back-office.
