# Fiches destinations

## Plan (20 novembre 2026)

Étape 2 du [plan de migration](plan-migration.md) : reprendre les 12 pages pays et la liste `/destination/` du site WordPress.

### Constat sur les contenus WordPress

Les 12 pages utilisent toutes le même modèle Elementor : une image, une introduction, puis des rubriques en accordéon (widget `toggle`). Deux pages ont en plus des titres intermédiaires (`divider`) suivis de textes. Aucune balise SEO n'est renseignée. Le HTML des rubriques utilise `p`, `ul`, `ol`, `li`, `strong`, `h5`, `br`, et quelques `div` et `span`.

Erreurs relevées sur le site actuel :

- **Turquie** et **Angleterre** affichent tout le texte de la page Canada (« Étudier au Canada… », CAQ, IRCC). Ce contenu n'est pas repris : il faut que le client fournisse les textes.
- **Japon** contient trois rubriques sur la Chine (« Visiter la Chine », exigences et demande de visa chinois). Elles ne sont pas reprises.
- **Chine** : la rubrique « La demande d'admission au Canada » est renommée « La demande d'admission en Chine ».
- Fautes et majuscules dans les titres (« Aller au Belgique », « TRAVAILLER en BEGIQUE », « Preparartion »…). Elles sont corrigées.
- La page France a l'adresse `/aller-en-en-france/`, alors que les liens internes de WordPress pointent vers `/aller-en-france/`.

### Réalisation prévue

- `database/import/wordpress-destinations.php` : script en ligne de commande, relançable. Il lit l'export XML et produit un fichier de données relisible par pays dans `database/content/destinations/`, avec l'image, l'introduction et les rubriques au HTML nettoyé. Les corrections listées ci-dessus sont décrites dans le script.
- `config/destinations.php` : registre des pays (adresse, nom, préposition, région, photo, drapeau, alias).
- `App\Models\Destination`, `App\Controllers\DestinationController`, une route par pays à son adresse WordPress, une redirection 301 depuis les alias, et la liste `/destination/`.
- `legacy_url()` renvoie vers la nouvelle page dès que la route existe. L'accueil et le menu basculent donc automatiquement vers les pages migrées.
- Fiche pays modernisée : bandeau photo, sommaire, rubriques en accordéon, accompagnement Taysir, autres destinations et appel à l'action. Styles dans `css/destination.css`.
- Vérifications : syntaxe, toutes les adresses, rendu sur ordinateur et sur mobile.

## Résultat

### Adresses

| Adresse | Effet |
| --- | --- |
| `/destination/` | Liste des 12 pays, filtrable par région |
| `/aller-au-canada/`, `/aller-en-france/`, `/aller-en-angleterre/`, `/aller-au-belgique/`, `/portugal/`, `/aller-au-russie/`, `/aller-en-turquie/`, `/aller-en-maroc/`, `/aller-en-tunisie/`, `/aller-en-arabie-saoudite/`, `/aller-en-chine/`, `/japon-2/` | Fiches pays, aux adresses de WordPress |
| `/aller-en-en-france/` → `/aller-en-france/`, `/japon/` → `/japon-2/`, `/destinations/` → `/destination/` | Redirections 301 |

La France est publiée sous `/aller-en-france/`, l'adresse utilisée 33 fois par les liens internes de WordPress. L'adresse réelle de la page WordPress, `/aller-en-en-france/`, est redirigée vers elle.

### Fichiers

| Fichier | Rôle |
| --- | --- |
| `database/import/wordpress-destinations.php` | Extraction relançable : `php -d extension=mbstring database/import/wordpress-destinations.php` (sous WAMP en ligne de commande, ajouter `-d extension_dir=C:/wamp64/bin/php/php8.4.15/ext`). Les corrections éditoriales sont listées dans `$fixes` et `$titleFixes`. |
| `database/import/WordpressExport.php` | Outil partagé : lecture de l'export, parcours des widgets Elementor, nettoyage du HTML, conversion du Markdown collé dans Elementor, écriture des fichiers de données. Réutilisable pour les prochains groupes de pages. |
| `database/content/destinations/<slug>.php` | Contenu de chaque pays : image d'origine, introduction, rubriques, et `pending` si le contenu reste à fournir. |
| `config/destinations.php` | Registre : adresse, alias, nom, préposition, région, photo, ville (pour le crédit photo), drapeau. |
| `app/Models/Destination.php` | `all()`, `find()`, `others()`, `byRegion()`, `imageUrl()`, `photoCredits()`, `summary()` (méta-description). |
| `app/Controllers/DestinationController.php` | `index()` pour la liste, `show($slug)` pour une fiche. |
| `app/Views/pages/destination.php`, `pages/destinations.php`, `partials/destination-cards.php` | Vues. |
| `public/assets/css/destination.css`, `js/destination.js`, `js/destinations.js` | Styles, sommaire actif et ouverture des rubriques, filtre par région. |
| `public/assets/images/destinations/{belgique,portugal,russie,arabie-saoudite,chine,japon}.webp` | Images des pages WordPress converties en WebP 1 920 px (de 86 à 244 Ko, contre 2,6 à 4,9 Mo en PNG). L'image d'origine de l'Arabie saoudite (texte « ARABIE SAOUDITE » incrusté) a été remplacée par l'autre image de ce pays sur le site, sans texte. |

### Nettoyage du contenu repris

- Balises limitées à `p`, `ul`, `ol`, `li`, `strong`, `em`, `br`, `a`, `h3` et `h4`, sans attributs sauf `href` en http(s), mailto ou tel.
- Titres de rubrique et sous-titres en casse française, avec les noms propres rétablis (pays, CAQ, IRCC, Arrima, La Mecque) et les fautes corrigées.
- Paragraphe court entièrement en gras, ou court et suivi d'une liste : transformé en sous-titre `h3`.
- Le **Markdown brut** collé dans Elementor pour la Belgique, la Russie, la Chine et le Maroc (`###`, `- `, `**…**`, `[texte](lien)`) est converti. Il s'affiche tel quel sur le site actuel.
- Listes numérotées découpées en une liste par élément : fusionnées. Sauts de ligne parasites supprimés.

### Changements communs à tout le site

- **Adresses terminées par `/`**, comme sur WordPress : `url('/contact')` donne `/contact/`, et une requête GET sans `/` final est redirigée en 301. Les fichiers (avec une extension) ne sont pas concernés.
- `Router::redirect()` pour les 301, et `Router::has()` utilisé par `legacy_url()` : **un lien vers une page migrée bascule tout seul vers la nouvelle application**. Les liens destinations de l'accueil et le menu pointent déjà vers les nouvelles fiches.
- `js/app.js` regroupe le comportement du menu, retiré de `home.js`. Le gabarit le charge sur toutes les pages, faute de quoi le menu restait ouvert sur mobile hors de l'accueil.
- `app.css` : les règles `nav` ne visent plus que `.site-menu nav`, car elles transformaient tout `nav` (fil d'Ariane, sommaire) en menu déroulant sous 68rem. `.light` a désormais le texte bleu marine, lisible sur fond sombre.
- Gabarit : `$canonical` (balise `link rel="canonical"`) et balises Open Graph de titre et de description.
- `config/site.php` : ajout de `contact.whatsapp` et `services` (les six étapes de l'accompagnement), et de « Toutes les destinations » dans le menu Études à l'étranger.

### Vérifications

- `php -l` sur tous les fichiers PHP et `node --check` sur tous les scripts : aucune erreur.
- 12 fiches et la liste en 200, redirections 301 attendues, 404 sur une adresse inconnue. Chaque fiche a le bon nombre de rubriques et sa photo.
- Navigateur à 1 024 px : bandeau, sommaire, ouverture d'une rubrique depuis le sommaire avec mise en évidence de l'entrée active, accompagnement, autres destinations, appel à l'action, et filtre « Asie » (Chine et Japon, annonce « 2 pays affichés »).
- À 375 px : aucun débordement horizontal sur les 12 fiches et la liste, menu fermé, sommaire masqué, aide placée après les rubriques.
- Test du routeur en sous-dossier WAMP : paramètres, préfixe des adresses, CSRF accepté ou refusé.
- Non vérifié : passage par Apache/WAMP, et nouvelle comparaison de l'accueil après les modifications d'`app.css` (`.light`, `nav`). L'accueil répond 200 et son menu utilise désormais `app.js`.

### À faire valider par le client

1. **Turquie et Angleterre** : fournir le contenu. La page affiche pour l'instant un encart « Guide en cours de mise à jour » avec les contacts.
2. **Japon** : trois rubriques sur la Chine ont été retirées. Fournir l'éventuel contenu sur le visa japonais.
3. **Relecture** : les textes sont repris tels quels, à l'exception des titres. Quelques fautes subsistent (« jeunes africain »…), et certains chiffres ou démarches (frais, délais, visas) peuvent avoir changé depuis leur publication.
4. Drapeaux manquants pour la Belgique, le Portugal, la Russie, l'Arabie saoudite, la Chine et le Japon (affichage sans drapeau pour l'instant).
5. Les boutons Rendez-vous, Admission et les étapes de l'accompagnement pointent encore vers les formulaires WordPress (étape 3 du plan).
