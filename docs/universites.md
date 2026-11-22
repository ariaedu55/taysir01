# Fiches universités

## Plan (20 novembre 2026)

Migration des 9 pages « Après le bac → Universités » : la liste `/universites/` et les 8 fiches d'universités publiques du Sénégal. Elles gardent leurs adresses WordPress, donc les liens du menu basculent automatiquement.

### Constat sur l'export

| Adresse | Université | Ville | Rubriques WordPress |
| --- | --- | --- | --- |
| `/ucad/` | Université Cheikh Anta Diop (UCAD) | Dakar | À propos, Facultés, Centres-écoles-instituts, Admissions |
| `/ugb/` | Université Gaston Berger (UGB) | Saint-Louis | À propos, UFR, Centres-écoles-instituts, Admissions |
| `/uadb/` | Université Alioune Diop de Bambey (UADB) | Bambey | À propos, UFR et instituts |
| `/uamb/` | Université Amadou Mahtar Mbow (UAM) | Dakar (Diamniadio) | À propos, UFR, Centres-écoles-instituts, Admissions |
| `/uas/` | Université Assane Seck de Ziguinchor (UASZ) | Ziguinchor | À propos, UFR, Admissions |
| `/uidt/` | Université Iba Der Thiam (UIDT) | Thiès | À propos, UFR, Écoles-instituts, Admission |
| `/usm/` | Université du Sine Saloum El Hadj Ibrahima Niass (USSEIN) | Kaolack | À propos, Facultés, Scolarité |
| `/uvs/` | Université numérique Cheikh Hamidou Kane (UN-CHK), ex-Université virtuelle du Sénégal | En ligne, Dakar | À propos, Formations, Admissions |

Toutes les fiches suivent le même modèle : une photo, le nom, des icônes de contact (e-mail, Facebook, Twitter, site officiel, parfois YouTube), une introduction, des rubriques en accordéon et une colonne « Nouvelles universitaires » (liste d'articles générée par le thème).

**Erreurs relevées sur le site actuel**, corrigées dans le script :
- l'UADB et l'USSEIN utilisent la même photo (`Image_UEIN.jpg`) : l'UADB reprend `uadb.jpeg`, sa photo sur la page de liste ;
- sur la page de liste, la carte UASZ utilise la photo de l'UAM : la fiche garde sa propre photo (`Image_UASZ.jpg`) ;
- UADB : la rubrique « À propos » est titrée « (UCAD) » ;
- UASZ : le lien « site officiel » pointe vers `ucad.sn` (retiré, à faire fournir), et l'adresse e-mail commence par « rr » (`rrectorat@univ-zig.sn`, conservée telle quelle, à vérifier) ;
- UGB : le lien « Twitter » est une adresse e-mail (retiré) ;
- UVS : renommée UN-CHK depuis, comme l'indiquent déjà le menu et la page de liste.

### Réalisation prévue

1. Mise en commun des fonctions d'extraction (`database/import/extract-functions.php`) entre les destinations et les universités, en vérifiant que les fiches pays restent identiques.
2. `database/import/wordpress-universities.php` : image, introduction, rubriques nettoyées, liens de contact. La rubrique « À propos » est retirée quand elle répète l'introduction.
3. `config/universities.php` : registre (adresse, sigle, nom, ville, photo, ordre).
4. Modèle `University`, contrôleur, routes, vues `pages/university.php` et `pages/universities.php`, reprenant le modèle visuel des fiches pays (bandeau, sommaire, accordéons) avec un encadré « Contacts officiels ». Actions vers le portail : « Créer mon compte » et « Prendre rendez-vous ».
5. Bloc « Taysir vous accompagne » sorti dans un fragment commun aux pays et aux universités.
6. Publication des médias, puis vérification des adresses, du rendu et des liens du menu.

## Résultat

### Adresses

| Adresse | Contenu |
| --- | --- |
| `/universites/` | Liste des 8 universités (cartes photo, sigle, ville, nom complet) |
| `/ucad/`, `/uvs/`, `/uamb/`, `/uidt/`, `/ugb/`, `/uadb/`, `/usm/`, `/uas/` | Fiches, aux adresses WordPress |

**Les 9 liens « Après le bac → Universités » du menu pointent désormais vers le nouveau site** (bascule automatique de `legacy_url()`).

### Fichiers

| Fichier | Rôle |
| --- | --- |
| `database/import/extract-functions.php` | Fonctions communes : `extractPage()` (image, introduction, rubriques, **liens de contact**), `classifyLink()` (écarte une icône sociale qui pointe ailleurs), `cleanTitle()`. Les fiches pays régénérées avec ces fonctions sont identiques à l'octet près. |
| `database/import/wordpress-universities.php` | Extraction relançable : `php -d extension=mbstring database/import/wordpress-universities.php`. Rubrique « À propos » renommée avec le sigle actuel, ou retirée si elle répète l'introduction (cas de l'UCAD) ; titres en casse française, accents rétablis (Facultés, Écoles, Étape, Scolarité…) et sigles en majuscules ; lien erroné de l'UASZ retiré. |
| `database/import/WordpressExport.php` | Détection des sous-titres affinée : un paragraphe en gras commençant par une puce (« – », « 1- ») ou finissant par « ; » ou « , » reste un paragraphe ; longueur de 4 à 75 caractères. |
| `database/content/universities/<slug>.php` | Contenu : introduction, rubriques, liens (site, e-mail, Facebook, X, YouTube). |
| `config/universities.php` | Registre : adresse, sigle, nom, ville, photo, ancien nom. |
| `app/Models/University.php`, `app/Controllers/UniversityController.php` | Modèle et contrôleur. |
| `app/Views/pages/university.php`, `pages/universities.php`, `partials/university-cards.php` | Vues. |
| `app/Views/partials/services.php` | Bloc « Taysir vous accompagne », désormais commun aux fiches pays et universités. La fiche Canada produit le même HTML qu'avant. |
| `public/assets/css/destination.css` | Ajouts : contacts officiels, ancien nom, mention de source, nom complet sur les cartes. |

### Fiche université

- Bandeau photo avec fil d'Ariane, « Université publique · ville », nom complet puis sigle en grand, ancien nom (UN-CHK), et actions **« Préparer mon admission »** (vers l'inscription au portail) et « Prendre rendez-vous ».
- Sommaire, encadré « Contacts officiels » (icônes, adresse e-mail visible), aide WhatsApp.
- Introduction, rubriques en accordéon et mention « Les dates et conditions d'admission évoluent chaque année : vérifiez-les… ».
- Accompagnement Taysir, autres universités, appel final « Prêt à rejoindre l'UCAD ? » avec « Créer mon compte ».

### Vérifications

- Les 9 pages en 200, `/ucad` redirigé en 301 vers `/ucad/`.
- Rubriques : UCAD 3, UN-CHK 3, UAM 4, UIDT 4, UGB 4, UADB 2, USSEIN 3, UASZ 3. Encadré des contacts présent partout.
- Toutes les images sont locales (WebP), aucune erreur, **aucun appel à l'ancien site**.
- Rendu contrôlé à 1 280 px (bandeau, sommaire, contacts, facultés) et à 375 px (liste). Aucun débordement horizontal à 375 px sur les 9 pages.
- Correction pendant les tests : le nom complet de l'université, découpé en mots, s'affichait un mot par ligne dans la grille du bandeau. Il est maintenant regroupé (`.university-name`).

### À faire valider par le client

- Adresse e-mail de l'UASZ (`rrectorat@univ-zig.sn`, avec deux « r ») et son site officiel (le lien actuel menait vers l'UCAD).
- Introductions reprises telles quelles : certaines commencent par un sigle en gras suivi d'une phrase mal raccordée (« UAM La deuxième université… »).
- UN-CHK : la rubrique Formations annonce des modules à télécharger qui n'existent pas dans la médiathèque.
- Procédures et frais d'inscription (étapes Campusen, assurance à 750 F CFA…) : à vérifier pour l'année en cours.
