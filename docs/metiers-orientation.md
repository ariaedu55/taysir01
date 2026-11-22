# Métiers, secteurs, écoles et orientation

## Plan (20 novembre 2026)

Migration du groupe « Après le bac / S'orienter » : 12 pages et les 83 articles qu'elles affichent.

### Constat sur l'export

Ces pages contiennent très peu de texte propre. Ce sont surtout des **listes d'articles** générées par WordPress, filtrées par catégorie :

| Page | Contenu WordPress |
| --- | --- |
| `/metiers/` | Recherche et liste des fiches métiers des 6 secteurs, puis offres |
| `/metier-sante/`, `/metier-du-numerique/`, `/petrole-et-gaz/`, `/metier-de-limmobilier/`, `/agriculture-et-agrobusiness/`, `/les-energies-renouvelables/` | Fiches métiers du secteur, puis 3 dernières offres d'emploi et de stage |
| `/formations-profesionnelles/` | Les 20 écoles de formation professionnelle |
| `/quel-secteur/`, `/quel-metier/`, `/quelle-filiere/` | Un texte d'introduction et des tests d'orientation (extension QSM : questions absentes de l'export) |
| `/apres-le-bac/` | Page vide dans l'export (contenu produit par le thème) |

Les **83 articles** sont à la racine du site (`/medecin/`, `/unipro-d/`…) :

| Type | Catégories WordPress | Nombre |
| --- | --- | ---: |
| Fiche métier | `metier` + secteur (`sante`, `informatique`, `petrole_gaz`, `immobilier`, `energie_renouvelable`, `agriculture_agrobusiness`) | 39 |
| École de formation professionnelle | `formations-professionnelles` | 20 |
| Offre d'emploi, de stage, bourse, formation courte | `offre_emploie`, `offre_stage`, `offre-bourses`, `offre-formation` | 19 |
| Salon collégien | `salon-collegien` | 3 |
| Test et partenaire vides | `non-classe` (`jjj`), `partenaire` (`edvantis`, sans contenu) | 2 (non repris) |

66 articles sont mis en page avec Elementor : en-tête du thème (ignoré), image de couverture, introduction, puis des titres de rubrique (Tâches, Compétences, Formations, Diplômes, Filières, Vie étudiante…) suivis de texte, d'accordéons ou d'une galerie. Les 17 autres sont des articles classiques en HTML.

### Réalisation prévue

1. **Table `articles` en base MySQL** (migration `002_create_articles.sql`) : adresse, titre, type, secteur, extrait, introduction, rubriques et galerie (JSON), image de couverture, date de publication, statut (`publie` ou `archive`), référence WordPress. C'est le premier contenu en base : il pourra être géré depuis le back-office (voir [vision-plateforme.md](vision-plateforme.md)).
2. `database/import/wordpress-articles.php` : extraction relançable des 81 articles, avec les mêmes règles de nettoyage que les autres pages. Les offres, bourses et salons de 2024 sont importés avec le statut `archive` : ils restent consultables, avec une mention « publication de juin 2024 », mais ne sont plus présentés comme d'actualité.
3. `config/sectors.php` : les 6 secteurs (adresse WordPress, nom, description, catégorie d'origine).
4. Pages :
   - fiche article à son adresse d'origine, adaptée à son type (métier, école, offre) ;
   - pages secteur ;
   - `/metiers/` avec filtre par secteur et recherche ;
   - `/formations-profesionnelles/` ;
   - pages d'orientation `/quel-secteur/`, `/quel-metier/`, `/quelle-filiere/`, qui reprennent leur texte et proposent un rendez-vous d'orientation à la place des tests manquants ;
   - `/apres-le-bac/`, page d'aiguillage vers les universités, écoles, métiers et pages d'orientation.
5. Médias publiés, vérification des adresses, du rendu et des liens du menu.

## Résultat

### Base de données

- Migration `002_create_articles.sql` appliquée : table `articles` (adresse unique, type, secteur, extrait, introduction, rubriques et galerie en JSON, image, statut, date, référence WordPress).
- `database/import/wordpress-articles.php` (relançable, mise à jour sur l'adresse) a importé **81 articles** :

  | Type | Nombre | Statut |
  | --- | ---: | --- |
  | Fiches métiers (santé 7, numérique 6, pétrole et gaz 6, immobilier 6, agriculture 8, énergies 6) | 39 | publié |
  | Écoles de formation professionnelle | 20 | publié |
  | Offres d'emploi 5, de stage 5, bourses 4, formations 5 | 19 | archive |
  | Salons collégiens | 3 | archive |

- Extraction des articles Elementor : l'en-tête du thème est ignoré, l'extraction s'arrête au pied de page recopié dans certains articles (newsletter, « Nos services », téléphone), les titres deviennent des rubriques (Tâches, Compétences et Formations pour les 39 métiers ; Diplômes et Filières pour les 20 écoles), et les galeries sont reprises. Les articles classiques sont nettoyés de leurs commentaires de blocs.
- Toutes les fiches ont une image (image à la une, ou première image de l'article). Le Markdown, y compris un « ** » orphelin dans « Médecin », est nettoyé.
- `WordpressExport` lit désormais aussi les articles et les médias (`posts()`, `attachment()`). Les fiches pays et universités régénérées restent identiques.

### Pages

| Adresse | Contenu |
| --- | --- |
| `/<adresse de l'article>/` (81) | Fiche à l'adresse WordPress : fil d'Ariane selon le type, pastilles (secteur, « Fiche métier », date pour les archives), image, mention d'archive, introduction, rubriques, galerie, encadré d'accompagnement adapté au type (« Trouver ma formation », « Préparer mon inscription » ou « Créer mon compte »), lien vers le secteur, articles proches |
| `/metier-sante/`, `/metier-du-numerique/`, `/petrole-et-gaz/`, `/metier-de-limmobilier/`, `/agriculture-et-agrobusiness/`, `/les-energies-renouvelables/` | Page secteur : métiers du secteur, autres secteurs, dernières opportunités (archives 2024) |
| `/metiers/` | Les 39 fiches, filtre par secteur et recherche insensible aux accents (`js/content.js`) |
| `/formations-profesionnelles/` | Les 20 écoles, avec recherche |
| `/quel-secteur/`, `/quel-metier/`, `/quelle-filiere/` | Texte repris, mention « test en ligne bientôt dans votre espace », pistes à explorer, secteurs, encadré « Bilan d'orientation » en 3 étapes |
| `/apres-le-bac/` | Page d'aiguillage : universités, écoles, métiers, filière, étranger, bilan, secteurs d'avenir |

Fichiers : `app/Models/Article.php` et `Sector.php`, `app/Controllers/ArticleController.php` et `OrientationController.php`, vues `pages/article.php`, `sector.php`, `careers.php`, `schools.php`, `orientation.php`, `after-bac.php`, fragment `partials/article-cards.php`, `public/assets/css/content.css`, `public/assets/js/content.js`, `config/sectors.php`, `database/import/wordpress-orientation.php` et `database/content/orientation/`.

Les routes des articles sont déclarées en dernier, et seulement si aucune page n'utilise déjà l'adresse. Si la base est indisponible, le reste du site fonctionne sans elles.

### Vérifications

- **93 pages** (81 articles et 12 pages) en 200, **0 image en erreur, 0 appel à l'ancien site**. L'article de test `jjj`, écarté, renvoie 404. Temps de réponse local : 0,08 s pour un article et 0,1 s pour l'accueil.
- Filtre `/metiers/` : 39 au total, 7 en santé, « data » trouve Data Scientist, « genie » sans accent trouve les 7 ingénieurs, message en l'absence de résultat.
- Rendu contrôlé à 1 280 px (fiche Médecin) et à 375 px (Après le bac). Aucun débordement horizontal à 375 px sur 10 pages représentatives.
- Les liens de l'accueil vers les 6 secteurs, les 6 écoles mises en avant, les offres et les pages d'orientation pointent désormais vers le nouveau site.

### État du menu

Sur les 34 liens du menu principal, **16 pointent encore vers l'ancien site** :
- les formulaires de demande (rendez-vous, admission, logement, visa, prise en charge, billet, hôtel), à remplacer par le portail ;
- les pages d'offres et d'activités (stages, emplois, avis de recruteurs et de professionnels, Parlons de voyage, salons, Taysir Club Challenge) ;
- les avis d'étudiants.

### À faire valider par le client

- Images à la une : certaines fiches réutilisent une image d'une autre fiche (par exemple Architecte avec une image nommée « Ingénieur en sécurité »).
- Titres d'articles repris tels quels (« Developpeur web » sans accent, titres bilingues comme « Architecte (Architect) »).
- Les tests d'orientation (extension QSM) sont à fournir ou à recréer dans le portail.
- Les offres de 2024 sont archivées : décider s'il faut les garder visibles, et publier de nouvelles offres depuis le back-office une fois celui-ci prêt.
