# Phase 12 — Catalogue des démarches et pièces à fournir (proposition)

Demande de l’utilisateur (28/11/2026) : permettre à l’administrateur de configurer les pièces à fournir selon la demande (visa études ou travail, admission en France ou ailleurs…), de façon cohérente entre le site public, l’ouverture d’un dossier et le suivi par l’équipe, en quelques clics.

**Statut : proposition, à valider avant tout code.**

## 1. Ce qui existe aujourd’hui

| Élément | Où | Limite |
| --- | --- | --- |
| 6 services (admission, logement, visa, prise en charge, billet, hôtel) avec leurs champs et 4 étapes affichées | `config/services.php` (code) | Non modifiable depuis la gestion. |
| Pays de destination | Champ libre avec suggestions, sur chaque formulaire | Pas de liste des pays couverts : « France », « france » et « FR » sont trois valeurs différentes. |
| Type de visa (étudiant, travailleur, affaires, tourisme, visite familiale) | Formulaire visa seulement | Enregistré, mais n’influence rien. |
| Modèles de procédure (2C), avec étapes, pièces par étape, versions, sources et vérification | `/gestion/modeles/`, administrateur | Seulement service + pays, pas de motif. 3 modèles en brouillon, aucun publié. |
| Pièces attendues d’un dossier | Copiées du modèle choisi **à la main**, quand un conseiller ouvre un dossier depuis une fiche, ou ajoutées une par une | Une demande faite sur le site **ne reçoit aucune pièce automatiquement**. Le client ne sait pas quoi préparer. |
| Site public | Pages services et destinations | Les pièces qui y figurent ne viennent pas des modèles : deux sources qui peuvent se contredire. |

## 2. Principe proposé : une « démarche » = service + pays + motif

Une **démarche** décrit une situation concrète, par exemple « Visa · France · Études » ou « Admission · Canada · Master ». Elle porte :

- **les pièces à fournir**, chacune avec :
  - son nom ;
  - une aide d’une ligne (« pages d’identité, en couleur ») ;
  - obligatoire ou facultative ;
  - qui la fournit (l’étudiant, le parent ou le garant) ;
  - une validité exigée si besoin (« passeport valable 6 mois après le départ ») ;
- **les étapes** suivies par l’équipe ;
- **les sources officielles** et la date de vérification (déjà en place).

**Règle de choix, sans multiplier les fiches :** le système retient la démarche la plus précise qui existe.

1. Service + pays + motif (Visa · France · Études)
2. Service + pays, pour tous les motifs (Visa · France)
3. Service + motif, pour tous les pays (Visa · Études)
4. Le modèle général du service (Visa)

L’administrateur crée donc un modèle général par service, puis il précise seulement les cas qui diffèrent vraiment : France, Canada, Belgique, Maroc…

**Motif : seulement là où il change les pièces.**

- **Visa :** études, travail, tourisme, visite familiale, affaires.
- **Admission :** niveau visé (licence, master, BTS ou école).
- **Logement, prise en charge, billet, hôtel :** pas de motif.

## 3. Parcours proposé

**Client, sur le site** (3 choix, puis la liste des pièces) :

1. Il choisit le service : les cartes déjà en place.
2. Il choisit le pays dans la liste des **pays couverts**, ou « Autre pays ».
3. Il choisit le motif, si le service en a.
4. Il voit aussitôt « Documents à préparer », la liste exacte de la démarche, avant d’envoyer.
5. À l’envoi, le dossier est créé avec ses **pièces attendues déjà en place**. Le client les dépose une par une, avec les boutons « Ajouter un fichier » et « Enregistrer la pièce ».

**Équipe :**

- **En-tête du dossier, dans toutes les listes :** « Visa · France · Études », la version du modèle, et le nombre de pièces reçues sur le total (« 3/7 pièces »). Les commerciaux, la prospection et l’accueil voient le même libellé.
- **Ouverture depuis une fiche :** les mêmes trois choix. La démarche est appliquée automatiquement, sans modèle à chercher.
- **Le conseiller peut ajouter une pièce propre au cas.** Il peut aussi marquer une pièce « non demandée pour ce dossier », avec un motif : rien n’est supprimé.
- **Filtres** des listes de dossiers par service, pays et motif.

**Administrateur : « Démarches et pièces »**, qui remplace « Modèles de procédure » :

- un tableau par service, avec les pays en lignes et les motifs en colonnes ; une case vide signifie que le modèle plus général s’applique ;
- un formulaire simple : une pièce par ligne (nom, aide, obligatoire, fournisseur, validité), les étapes, les sources ;
- « Dupliquer pour un autre pays » ;
- « Publier », avec les versions déjà en place. Un dossier ouvert garde sa version ;
- la liste des **pays couverts**, partagée par le site, les formulaires et les filtres.

**Site public :** les pages services et destinations affichent « Documents habituellement demandés », lus dans les démarches publiées. Il n’y a plus qu’une seule source.

## 4. Découpage technique proposé

- **12A — Données :**
  - migration 026 : pays couverts ; motif sur les modèles ; pièces structurées (obligatoire, aide, fournisseur, validité) ;
  - service + pays + motif enregistrés sur le dossier, avec la démarche retenue ;
  - fonction de choix de la démarche, avec ses tests.
- **12B — Page administrateur** « Démarches et pièces » : tableau, formulaire, duplication, publication, pays couverts.
- **12C — Application automatique :**
  - choix pays + motif sur les formulaires publics et sur l’ouverture d’un dossier ;
  - liste « Documents à préparer » ;
  - pièces attendues créées à la création du dossier.
- **12D — Affichage et filtres :**
  - libellé de la démarche et compteur de pièces dans les listes et l’en-tête ;
  - filtres par service, pays et motif ;
  - pièces sur les pages publiques.

## 5. Décisions attendues de l’utilisateur

1. La liste des **pays couverts** : faut-il partir des 12 destinations du site, avec « Autre pays » en plus ?
2. Les **motifs** : visa (5 motifs) et admission (niveau visé), comme proposé ?
3. Le **contenu des démarches** : l’agence fournit-elle les listes de pièces réelles, ou faut-il préparer des listes de départ à faire valider ?
4. **Les demandes déjà faites** : leur appliquer la démarche ? Je recommande de ne rien changer aux dossiers existants.
