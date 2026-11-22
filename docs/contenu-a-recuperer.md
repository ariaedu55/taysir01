# Contenu du site WordPress : ce qui est récupéré, ce qui manque

Analyse de l'export `storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml`, le 20 novembre 2026.

## Pourquoi la navigation mène encore à l'ancien site

Les liens du menu passent par `legacy_url()`. Tant qu'une page n'a pas été recréée dans la nouvelle application, le lien pointe vers l'ancien site. **Dès que la route d'une page existe, le lien bascule automatiquement** (voir [destinations.md](destinations.md)). Aujourd'hui, seules l'accueil, la liste des destinations et les 12 fiches pays sont migrées. Les autres liens du menu (universités, filières, secteurs, métiers, demandes, offres, activités…) basculeront au fur et à mesure.

## Ce que l'export contient déjà (rien à demander)

| Contenu | Volume | Dans l'export |
| --- | --- | --- |
| Pages publiées | 77 | Textes, titres, accordéons, listes et boutons des mises en page Elementor (394 titres, 374 blocs de texte, 83 accordéons, 81 listes à icônes) |
| Articles | 83, tous de 2024 | Texte complet et catégories : métiers 39, formations professionnelles 20, secteurs, offres d'emploi, de stage, de formation et bourses |
| Menus | 107 entrées | Structure de navigation |
| SEO | Métadonnées Yoast | Titres et descriptions des pages, réutilisables |
| Formulaires | 15 formulaires Elementor (contact, admission, logement, visa, prise en charge, billet, rendez-vous…) | **Liste des champs** : elle sert de base aux formulaires du portail |
| Médias | 909 références | **Adresses** des fichiers sur le site public, mais pas les fichiers eux-mêmes |

**Conclusion : tous les textes nécessaires pour continuer la migration sont disponibles.** Les groupes suivants peuvent être traités tout de suite avec l'outil d'extraction (`database/import/`) : universités (9 pages), secteurs, métiers et orientation (12 pages), pages de l'agence (7), ressources et articles (83).

## Ce qui manque

### 1. Les fichiers médias : important, simple à obtenir

- Sur 909 fichiers, **56 sont en local** (ceux de l'accueil). Les autres sont encore chargés depuis l'ancien site, qui disparaîtra à la bascule.
- Répartition : 848 images (png, jpg, jpeg, webp, svg), 33 PDF, 7 Word, 6 zip, 3 vidéos et quelques fichiers bureautiques.
- Deux façons de les récupérer :
  - **A. Téléchargement depuis le site public** : les adresses sont toutes dans l'export, et c'est déjà ainsi que les images des pays ont été récupérées. Il suffit de l'accord de l'utilisateur. Seuls les fichiers réellement utilisés par les pages migrées seraient téléchargés, puis convertis en WebP.
  - **B. Copie du dossier `wp-content/uploads/`** depuis l'hébergement LWS (gestionnaire de fichiers ou FTP), à déposer dans `storage/exports/uploads/`. C'est plus complet (originaux en pleine taille), mais cela demande un accès à LWS.

#### Résultat : médias récupérés le 20 novembre 2026 (option A, avec l'accord de l'utilisateur)

- **Les 909 fichiers sont récupérés**, dans `storage/exports/uploads/` (912 Mo). Le dossier reproduit l'arborescence `wp-content/uploads/` et se trouve hors de la racine web : c'est l'archive des originaux.
  - Premier passage : 851 fichiers téléchargés et 56 recopiés.
  - Vérification de chaque fichier contre la taille annoncée par le serveur : 68 fichiers tronqués ou différents ont été retéléchargés (dont les gros PNG de 6 à 32 Mo, coupés en cours de transfert), et les noms à caractères spéciaux (« … », arabe) ont été encodés.
  - Résultat : 909 fichiers identiques aux originaux.
- **Publication** avec `database/import/publish-media.php` (relançable) :
  - les **575 médias utilisés** par les pages, articles, images à la une et fichiers de configuration sont convertis en WebP (1 920 px au maximum) dans `public/assets/images/wordpress/` : **539,9 Mo d'originaux, 34,3 Mo publiés** ;
  - le manifeste `database/content/media-manifest.php` associe chaque chemin d'origine à son fichier publié ;
  - `media_url()` sert la version publiée, sinon une copie locale à l'identique, et en dernier recours l'ancien site ;
  - les 54 anciennes copies au format d'origine présentes dans `public/` ont été supprimées (originaux conservés dans l'archive).
- Vérifié : accueil, liste des destinations, fiche Canada et inscription chargent tous leurs fichiers en local, sans erreur, et **plus aucun média n'est appelé sur l'ancien site**.
- Les médias non utilisés (334, dont des images de démonstration du thème) restent dans l'archive. `--all` permet de tout publier si besoin.

#### Confidentialité : documents personnels dans la médiathèque

La médiathèque WordPress contient **53 documents** qui ne sont pas des images. Une partie relève de **données personnelles de clients**, aujourd'hui **accessibles publiquement à leur adresse sur le site actuel** :
- 3 passeports ;
- 3 documents de visa ;
- 4 lettres d'admission ou d'offre ;
- 3 attestations ou assurances ;
- 4 rendez-vous, billets ou reçus de paiement ;
- 5 contrats ou conventions.

Mesures prises :
- ces documents restent uniquement dans l'archive privée `storage/exports/uploads/`, qui doit être exclue de tout dépôt Git et de toute sauvegarde partagée ;
- `publish-media.php` ne publie aucun document qui ne figure pas dans la liste validée `$approvedDocuments`, vide pour l'instant. Aucune page ne cite d'ailleurs ces documents.

**À recommander au client** : supprimer ces fichiers de la médiathèque WordPress dès maintenant, sans attendre la bascule, car ils restent téléchargeables par toute personne qui connaît ou devine leur adresse. À l'avenir, les documents des clients seront déposés dans l'espace client et stockés hors de la racine web (voir [vision-plateforme.md](vision-plateforme.md)).

### 2. Les données gérées par des extensions : seulement si on veut les conserver

Ces contenus ne sont pas dans l'export XML. Ils se trouvent dans des tables propres aux extensions. On ne peut les récupérer **qu'avec une sauvegarde de la base MySQL** (export phpMyAdmin depuis l'espace LWS), et seulement si le client veut les garder :

| Extension | Utilisation sur le site | Dans l'export | Utile ? |
| --- | --- | --- | --- |
| Elementor Pro, formulaires | Demandes envoyées par les visiteurs (contacts, admissions, visas…) | Non (table des soumissions) | Uniquement pour reprendre l'historique des demandes |
| QSM (Quiz and Survey Master) | « Test filière collégien », « Questionnaire » (3 quiz) | Titres seulement : **questions et réponses absentes** | Oui, si les tests d'orientation doivent être repris tels quels. Sinon, le client fournit les questions |
| BA Book Everything | Réservation de circuits (pages `all-items`, `ba-wishlist`, `checkout`, `my-account`) | Aucun circuit dans l'export | Probablement non : extension apparemment inutilisée |
| Slider Revolution | Diaporama de l'ancien accueil | Non | Non : accueil déjà refait |
| `gaov.edutaysir.com` | Lien « Mon dossier » vers une application externe | — | **À clarifier** : s'agit-il d'un autre logiciel qui contient des dossiers clients ? Faut-il le remplacer par l'espace client ou y reprendre des données ? |

### 3. Les pages vides dans l'export : contenu à fournir ou à générer

19 pages publiées n'ont aucun texte : leur contenu est produit par le thème ou une extension.
- **Pages à générer** par la nouvelle application à partir des articles : `blog`, `journal-ecoles`, `evenements`.
- **Pages techniques, remplacées par le portail** : `suivre-son-dossier` (déjà redirigée vers `/mon-espace/`), `my-account`, `checkout`, `confirmation`, `confirmation-2`, `admin-confirmation`, `add_services`, `search-result`.
- **Pages dont le client doit fournir le contenu** (ou décider de les supprimer) : `entreprise`, `avis-de-recruteurs`, `avis-de-professionnels`, `sorienter`, `partir-a-letranger`, `parlons-de-voyage`, `questionaire`, `metier-sante-2` (doublon de `metier-sante`).

### 4. Validations du client

- Contenu des pages **Turquie** et **Angleterre** : elles reprennent aujourd'hui le texte du Canada (voir [destinations.md](destinations.md)).
- Actualité des **offres, stages et bourses** : les 83 articles datent tous de 2024. Il faut décider lesquels publier, archiver ou supprimer.
- **Chiffres, partenaires et coordonnées** (numéro parisien incomplet sur la page Contact).
- Rôle de **`gaov.edutaysir.com`** (voir ci-dessus).

## Ordre proposé pour la suite

1. ~~Récupérer les médias~~ : fait, voir ci-dessus.
2. Migrer, dans cet ordre, en reliant chaque page à une action du portail ([vision-plateforme.md](vision-plateforme.md)) :
   - ~~universités~~ (fait) ;
   - ~~secteurs, métiers et orientation~~ (fait, avec les 81 articles) ;
   - pages de l'agence (à propos, contact, FAQ, règles, conditions, témoignages) ;
   - blog et articles, avec leurs catégories ;
   - offres, salons et activités.
3. ~~Remplacer les **formulaires** WordPress par les fonctionnalités du portail~~ : fait, voir [demandes-rendez-vous.md](demandes-rendez-vous.md).
4. Rassembler dans une seule demande au client tous les **contenus manquants** listés plus haut.
