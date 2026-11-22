# Plan de migration du site WordPress

> **Important :** l’objectif n’est pas seulement de refaire la vitrine. L’application est une plateforme (portail client et gestion de l’agence), voir [vision-plateforme.md](vision-plateforme.md). Les formulaires WordPress (étape 3) sont remplacés par des fonctionnalités du portail, avec comptes, suivi et back-office.

## Objectif (20 novembre 2026)

Refaire [taysir-orientation.com](https://taysir-orientation.com/), aujourd'hui sous WordPress et Elementor chez LWS, en PHP natif MVC. On reprend les contenus existants (textes, images, structure, adresses) et on les modernise avec l'identité définie dans [DESIGN.md](DESIGN.md), sans repartir de zéro.

## Déjà fait

- Inventaire de l'export WordPress : [inventaire-wordpress.md](inventaire-wordpress.md). L'export est rangé dans `storage/exports/`.
- Inventaire du site public et choix de navigation : [refonte-taysir.md](refonte-taysir.md).
- Accueil refait à partir de l'accueil WordPress : [accueil.md](accueil.md).
- Socle technique : [architecture-mvc.md](architecture-mvc.md), [noyau-mvc.md](noyau-mvc.md), [gabarit-partials.md](gabarit-partials.md), [css-commun.md](css-commun.md).
- 57 des 909 médias sont copiés en local. Les autres sont encore chargés depuis l'ancien site.
- **Étape 2 terminée** : les 12 fiches pays et la liste, voir [destinations.md](destinations.md).
- **Inventaire de ce qui manque** (médias, données d’extensions, pages vides, validations) : [contenu-a-recuperer.md](contenu-a-recuperer.md).
- **Métiers, secteurs, écoles, orientation et 81 articles faits** (articles en base MySQL), voir [metiers-orientation.md](metiers-orientation.md).
- **Universités faites** : liste et 8 fiches, voir [universites.md](universites.md).
- **Portail amorcé** : base MySQL, inscription, connexion, espace client et premier back-office, voir [authentification.md](authentification.md).

## Les 77 pages publiées, regroupées par modèle

La plupart des pages se répètent. On crée **un modèle par groupe**, alimenté par les données extraites de l'export, au lieu de refaire 77 pages une par une. On **garde les mêmes adresses** (par exemple `/aller-au-canada/`) pour ne pas perdre le référencement Google.

| Groupe | Pages WordPress | Nombre | Modèle à créer |
| --- | --- | ---: | --- |
| Destinations | `aller-au-canada`, `aller-en-en-france`, `aller-en-angleterre`, `aller-en-turquie`, `aller-en-maroc`, `aller-en-tunisie`, `aller-en-arabie-saoudite`, `aller-au-belgique`, `aller-au-russie`, `aller-en-chine`, `japon-2`, `portugal`, plus la liste `destination` | 13 | Fiche pays + liste |
| Universités du Sénégal | `ucad`, `ugb`, `uadb`, `uamb`, `uas`, `uidt`, `usm`, `uvs`, plus la liste `universites` | 9 | Fiche établissement + liste |
| Secteurs et métiers | `agriculture-et-agrobusiness`, `les-energies-renouvelables`, `metier-de-limmobilier`, `metier-du-numerique`, `metier-sante`, `petrole-et-gaz`, `metiers`, `quel-secteur`, `quel-metier`, `quelle-filiere`, `formations-profesionnelles`, `apres-le-bac` | 12 | Fiche secteur + pages d'orientation |
| Demandes de service | `demande-dadmission`, `demande-de-logement`, `demande-de-prise-en-charge`, `demande-de-visa`, `vente-billet-davion`, `reservation-hotel`, `rendez_vous_orientation` | 7 | **Fait** ([demandes-rendez-vous.md](demandes-rendez-vous.md)) : pages de service, demandes en base avec documents, rendez-vous, suivi client et back-office. E-mails faits ([notifications-email.md](notifications-email.md)) |
| Agence | `about-us`, `contact`, `faq`, `nos-regles-et-valeurs`, `terms-conditions`, `temoignage`, `avis-etudiants` | 7 | **Fait** ([pages-agence.md](pages-agence.md)) : contact enregistré en base et traité dans le back-office |
| Ressources et activités | `offre-demploie`, `offre_stage`, `salons-internationaux`, `salon-collegien`, `taysir-club-challenge`, `blog`, et les 83 articles | 6 + articles | **Pages faites** ([offres-activites.md](offres-activites.md)) ; articles faits ; blog fait ([blog.md](blog.md)) |
| À vérifier avec le client | `suivre-son-dossier`, `partir-a-letranger`, `sorienter`, `parlons-de-voyage`, `journal-ecoles`, `evenements`, `entreprise`, `avis-de-recruteurs`, `avis-de-professionnels` | 9 | Pages vides dans l'export : contenu produit par une extension ou lien externe |
| À ne pas reprendre | `add_services`, `admin-confirmation`, `all-items`, `ba-wishlist`, `checkout`, `confirmation`, `confirmation-2`, `my-account`, `search-result`, `tester`, `test-filiere-collegien`, `questionaire`, `metier-sante-2`, `home-2` (déjà refait) | 14 | Pages techniques d'extensions, tests, doublons |

## Ce qu'on améliore par rapport au site WordPress

- **Rapidité** : sans Elementor ni extensions, les images sont en WebP avec chargement différé. Objectif : un score Lighthouse supérieur à 90 sur mobile.
- **Design** : un style commun moderne pour toutes les pages (photos, panneaux de verre, animations sobres), d'abord pensé pour le mobile.
- **Parcours clair** : chaque page mène à une action (prendre rendez-vous, faire une demande, écrire sur WhatsApp).
- **Formulaires natifs** : les demandes sont enregistrées en base, avec un e-mail de confirmation et un suivi dans un back-office, au lieu de dépendre d'extensions.
- **Contenus vérifiés** : les offres et actualités périmées sont archivées, et les chiffres et partenariats sont à faire valider par le client.
- **Référencement** : mêmes adresses, balises title et description par page, plan du site `sitemap.xml`, redirection permanente (301) des pages supprimées.

## Étapes proposées

1. **Outil d'extraction** : un script lit l'export XML et les données Elementor, puis produit les textes, images et liens de chaque page dans des fichiers de données relus à la main.
2. **Destinations** : le modèle de fiche pays et ses 12 pays. C'est la partie la plus visible, et elle est utile pour une première démonstration au client.
3. **Demandes de service** : la base de données, les 7 formulaires, les e-mails et un premier back-office.
4. **Universités, secteurs et orientation**.
5. **Pages de l'agence**.
6. **Ressources et blog**.
7. **Mise en ligne chez LWS** : d'abord une version de test sur un sous-domaine, avec validation du client, puis bascule du domaine, redirections et suivi dans Google Search Console.

## À récupérer chez LWS

- Le dossier `wp-content/uploads/` complet, via le gestionnaire de fichiers ou le FTP de LWS, à copier dans `storage/exports/uploads/`. Il permettra de servir tous les médias en local.
- Le nom des extensions de formulaires, de rendez-vous et de suivi de dossier, et l'éventuel historique des demandes, si le client souhaite le conserver.
- La version de PHP et les accès MySQL de l'hébergement, pour préparer la mise en ligne.
