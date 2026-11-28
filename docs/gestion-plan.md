# Système de gestion Taysir : plan d'action et d'implémentation (version 2.4)

- **Version 1** : 26 novembre 2026.
- **Version 2** : 26 novembre 2026, après la revue de l'utilisateur, avec ses 7 observations (§0).
- **Version 2.1** : direction validée par l'utilisateur ; ajout du §12 (parcours et expérience par rôle, décisions sur les fonctions de GAOV, maquette navigable à valider en phase 1, compte rendu en trois parties).
- **Version 2.2** : référence = **les 8 postes réels de GAOV** (rôles, affectations, parcours) ; matrice des droits par poste ; accès Client commun avec parcours *études* et *voyage* ; écrans mutualisés ; points tranchés par les données de GAOV et points à décider (§3.6) ; faille de droits de GAOV en production (§3.7).
- **Version 2.3** : comptes GAOV = comptes de test, carte blanche ; décisions arrêtées sur les comptes, les cumuls, les agences et la matrice (§3.6) ; reprise limitée aux référentiels.
- **Version 2.4** (28 novembre 2026, décision de l’utilisateur) : **plus aucun travail sur l’ancienne application GAOV**. Elle n’est plus utilisée ; l’agence travaille sur la suite Office en attendant le nouveau système. La reprise GAOV (§9) est abandonnée, la phase 9 devient la recette d’ensemble, et une **dernière phase 10, refonte des interfaces**, est ajoutée.

**Objectif.** Refondre la gestion interne (ancienne application GAOV, voir [gaov-readme.md](gaov-readme.md)) **dans la plateforme Taysir**, sans copier GAOV :
- un seul projet PHP MVC, un seul domaine ;
- la connexion publique commune ;
- l'espace client `/mon-espace/` et l'espace équipe `/gestion/` ;
- la charte du site : blanc et gris clair, texte noir, rouge Taysir pour les actions, aucun dégradé ; Prata et Poppins sur le site public, **Poppins seule dans la gestion** ;
- une navigation repensée ;
- les défauts de GAOV corrigés ;
- les fonctions indispensables ajoutées.

---

## 0. Revue de la version 1 : observations retenues

| # | Observation | Verdict | Vérification dans le code | Effet sur le plan |
| --- | --- | --- | --- | --- |
| 1 | Partir du back-office existant ; carte de transition ; inventaire `/admin/` GET et POST ; redirections temporaires ; routes POST de compatibilité ; retour au formulaire après connexion | **Pertinente** | 15 routes `/admin/` (8 GET, 7 POST) ; `?retour=` déjà géré par `AuthController` (redirection 303) | §2 carte de transition et inventaire des adresses |
| 2 | Matrice des droits par rôle, agence et affectation, appliquée côté serveur partout (listes, compteurs, recherche, téléchargements, exports, accès direct) | **Pertinente, faille confirmée** | `Admin\RequestController::document()` vérifie seulement le rôle : **un conseiller peut télécharger la pièce d'un dossier attribué à un collègue** | §3, avec correction en phase 1 |
| 3 | Séparer personne, compte et dossiers ; parents et tuteurs ; doublons validés par un humain ; correspondance demandes et dossiers ; règles avant toute suppression | **Pertinente, risque confirmé** | `service_requests.user_id … ON DELETE CASCADE`, puis pièces et événements en cascade : supprimer un compte **effacerait tout l'historique**, et les fichiers resteraient orphelins sur le disque | §4 modèle de données et cycle de vie |
| 4 | Livrer d'abord un parcours métier complet ; recherche quand elle est utile ; signature et paiement plus tard ; fuseaux, confirmation, doubles réservations ; procédures versionnées et sourcées ; validité des pièces selon la démarche | **Pertinente** | fuseau unique `Africa/Dakar` alors que l'agence de Paris est en UTC+1 ou UTC+2 | §5 phases réordonnées ; §6 règles d'agenda et de procédures |
| 5 | Finance V1 = enregistrement (pas d'encaissement en ligne) ; règles de correction, remboursement, devise et numérotation ; données privées dès le socle ; cadre légal à valider avec l'agence ; assistant limité au public | **Pertinente** | l'assistant n'indexe déjà que le contenu public (articles publiés, configuration, pages) | §7 et §8 |
| 6 | Préparer la reprise dès le cadrage : correspondances, comptes à réinviter et à écarter, import relançable, répétition sur une copie protégée, gel, sauvegarde, retour arrière ; révocation de l'accès Git à part | **Pertinente** | base GAOV : 18 comptes, 2 clients, 2 rendez-vous, des référentiels | §9 |
| 7 | Scénarios avec résultats attendus (303, refus hors périmètre, effets en base, téléchargements, double soumission, retours après connexion), clavier, 3 tailles d'écran, approbation du tableau de bord, de la navigation et d'une liste type en phase 1 | **Pertinente** | les formulaires actuels redirigent en 303 (`redirect_to`) | §10 |

Aucune observation n'a été écartée. Une nuance porte sur le point 1 : les routes POST de compatibilité ne servent qu'aux onglets ouverts pendant le déploiement. Elles sont gardées **une version**, puis retirées.

---

## 1. Règle de conduite (inchangée et renforcée)

Pour chaque phase :
1. **Avant** : `docs/gestion-phase-N.md`, avec les objectifs, les scénarios, les résultats attendus et les données de test.
2. **Réalisation.**
3. **Tests** (§10).
4. **Après** : résultats, écarts et corrections, dans le même document.
5. **Accord explicite de l'utilisateur.**

La phase suivante ne démarre jamais sans cet accord. Toute anomalie est corrigée avant validation.

---

## 2. Point de départ : le back-office existant

### 2.1 Ce qui est réutilisé tel quel (déjà testé)

- **Accès** : connexion et inscription ; retour au formulaire d'origine (`?retour=`, chemin interne uniquement) ; réinitialisation du mot de passe ; invitation d'équipe (72 h) ; désactivation des comptes.
- **Demandes** : 7 services en ligne ; pièces stockées hors du web ; fil d'événements ; statuts ; attribution à un conseiller.
- **Rendez-vous** publics, messages de contact, file d'e-mails (`mail_outbox`) avec nouvelles tentatives par tâche cron.

### 2.2 Carte de transition par module

| Module actuel | Phase | Devient | Ce qui change |
| --- | --- | --- | --- |
| Connexion, inscription, `?retour=` | 1 | inchangé | une seule règle de redirection après connexion (§2.4) |
| Invitations, équipe, désactivation | 1 | `/gestion/equipe/` | 8 postes, agences, affectations |
| Tableau de bord `/admin/` | 1 | `/gestion/` « Ma journée » | adapté au rôle |
| Demandes `/admin/demandes/` | 1, puis 3 | `/gestion/dossiers/` | **contrôle par affectation dès la phase 1** ; chaque demande devient un dossier rattaché à une personne en phase 3 (§4.3) |
| Pièces d'une demande | 1, puis 3 | pièces du dossier | contrôle d'accès et journal des téléchargements dès la phase 1 |
| Rendez-vous `/admin/rendez-vous/` | 1, puis 2 | `/gestion/rendez-vous/` | agenda, confirmation, anti-double réservation, compte rendu (phase 2) |
| Messages `/admin/messages/` | 1 | `/gestion/messages/` | périmètre d'agence |
| E-mails `/admin/e-mails/` | 1 | `/gestion/e-mails/` | inchangé, réservé aux administrateurs |
| Espace client `/mon-espace/` | 2, puis 3 | inchangé | état du dossier, pièces attendues, rappels |

### 2.3 Inventaire des adresses `/admin/` et traitement pendant la transition

| Méthode | Adresse actuelle | Accès actuel | Future adresse | Transition |
| --- | --- | --- | --- | --- |
| GET | `/admin/` | admin, conseiller | `/gestion/` | redirection **302 temporaire** |
| GET | `/admin/demandes/` | admin, conseiller | `/gestion/dossiers/` | 302 avec les filtres conservés |
| GET | `/admin/demandes/{ref}/` | admin, conseiller | `/gestion/dossiers/{ref}/` | 302 ; le contrôle de droits s'applique à l'arrivée |
| POST | `/admin/demandes/{ref}/` | admin, conseiller | `/gestion/dossiers/{ref}/` | route de compatibilité : **même traitement, mêmes droits, même CSRF** |
| POST | `/admin/demandes/{ref}/attribution/` | admin, conseiller | `/gestion/dossiers/{ref}/attribution/` | compatibilité, même traitement |
| GET | `/admin/demandes/{ref}/documents/{id}/` | admin, conseiller | `/gestion/dossiers/{ref}/pieces/{id}/` | 302 ; contrôle par affectation |
| GET | `/admin/rendez-vous/` | admin, conseiller | `/gestion/rendez-vous/` | 302 |
| POST | `/admin/rendez-vous/{ref}/` | admin, conseiller | `/gestion/rendez-vous/{ref}/` | compatibilité |
| GET | `/admin/messages/` | admin, conseiller | `/gestion/messages/` | 302 |
| POST | `/admin/messages/{id}/` | admin, conseiller | `/gestion/messages/{id}/` | compatibilité |
| GET | `/admin/e-mails/` | admin, conseiller | `/gestion/e-mails/` | 302 |
| POST | `/admin/e-mails/{id}/` | admin, conseiller | `/gestion/e-mails/{id}/` | compatibilité |
| GET | `/admin/equipe/` | admin | `/gestion/equipe/` | 302 |
| POST | `/admin/equipe/` | admin | `/gestion/equipe/` | compatibilité |
| POST | `/admin/equipe/{id}/` | admin | `/gestion/equipe/{id}/` | compatibilité |

Après la validation de la phase 1 :
- les redirections GET deviennent **301** ;
- les routes POST de compatibilité sont retirées à la version suivante.

Les liens des e-mails déjà envoyés (`/admin/demandes/{ref}/`) restent valables grâce aux redirections.

### 2.4 Une seule règle de redirection après connexion

1. Si un chemin de retour **interne** a été mémorisé (`?retour=`) **et** que le compte y a accès, on revient à ce chemin. Exemple : le formulaire de demande de visa.
2. Sinon, un membre de l'équipe va sur `/gestion/`, un client sur `/mon-espace/`.
3. Un compte désactivé ne passe pas, comme aujourd'hui.

Cette règle est appliquée par une seule fonction, utilisée par la connexion, l'inscription, l'acceptation d'invitation et la réinitialisation du mot de passe.

---

## 3. Droits : postes, agences et affectation

*Version 2.2 : la référence est désormais **les postes réels de GAOV** (table `roles`, affectations dans `users`, parcours de l'ancienne application), comme le demande l'utilisateur.*

### 3.1 Les huit postes internes et l'accès Client

Chiffres extraits de l'export GAOV du 26/11/2026, hors comptes de test créés en local.

| Poste GAOV (id) | Comptes | Missions constatées dans GAOV (menus et routes) | Affectation dans les données GAOV | Poste dans la nouvelle plateforme |
| --- | --- | --- | --- | --- |
| ADMINISTRATEUR (1) | 1 | paramètres de l'agence, gestion des contrats, personnel (ajout, liste, supprimés), statistiques, et accès à tous les modules | — (voit tout) | **Administrateur** |
| SECRÉTAIRE (2) | 2 | visiteurs (ajout, liste), rendez-vous, statistiques | — | **Secrétaire** |
| AGENT PROSPECTION (3) | 1 | prospection : saisie et import Excel des prospects par établissement et niveau | `clients.agent_prospect_id` | **Agent de prospection** |
| COMMERCIALE (4) | 2 | prospects qui lui sont attribués, prise de rendez-vous, commentaires | `clients.commerciale_id`, `rendez_vous.commerciale_id`, `relances.commerciale_id` | **Commerciale** |
| COMPTABLE (5) | 2 | clients et paiements par type (visa, assurance, prise en charge, hébergement, autres), clients en règle, factures, dépenses | — | **Comptable** |
| CONSEILLER (6) | 2 | conseil : visiteurs et dossiers **qui lui sont affectés**, rendez-vous, commentaires, replanification | `clients.conseiller_id`, `rendez_vous.conseiller_id` (liste filtrée par conseiller dans le code) | **Conseiller** |
| AGENT ORIENTATION (7) | 2 | étudiants à orienter et déjà orientés, choix de destination, rendez-vous d'orientation | `clients.agent_orientation_id`, `rendez_vous.agent_orientation_id` | **Agent d'orientation** |
| AGENT TRAITEMENT (8) | 4 | clients à traiter, traitement des demandes : Campus France, France Visa, université, CAQ, Arrima, IRCC, ambassade | `demandes.agent_id` | **Agent de traitement** |
| ÉTUDIANT (9) | 2 | suivi, documents, paiements, demande, signature | `etudiants.user_id` | **Client** (parcours *études*) |
| NON-ÉTUDIANT (10) | 0 | suivi de procédure, traitement du dossier | — | **Client** (parcours *voyage*) |

**Total : 16 comptes internes et 2 comptes clients.**

**Les huit postes internes restent distincts.** En particulier :
- l'agent de prospection et la commerciale restent deux postes ;
- l'agent d'orientation et le conseiller restent deux postes.

**Aucune fusion de poste n'est proposée.** Ce qui est mutualisé, ce sont les **écrans et les traitements communs** :
- la fiche personne ;
- l'agenda ;
- le dossier et ses pièces ;
- les notes ;
- la recherche ;
- les tâches.

Chaque poste y accède par **sa propre entrée** (« Ma journée », son menu) et n'y voit que **ses actions autorisées**.

**Côté public, un seul accès Client** (inscription et connexion communes), avec deux parcours :
- **Études** (élèves, étudiants et leurs parents) : orientation, admission, visa études, logement.
- **Voyage** : visa touristique ou affaires, billets, hôtel, assurance, sans volet académique.

Le parcours est déterminé par le dossier ouvert, et non par le compte : une même personne peut avoir un dossier études et un dossier voyage.

### 3.2 Chaîne de travail héritée de GAOV, conservée et fluidifiée

```
Agent de prospection → Commerciale → Secrétaire → Conseiller → Agent d'orientation → Comptable → Agent de traitement → Client
 (collecte prospects)   (appel, RDV)   (accueil)    (entretien,   (choix destination,  (contrat,     (Campus France, visa,  (suivi, pièces,
                                                     dossier)      RDV d'orientation)   paiements)    CAQ, ambassade…)       paiements)
```

- Chaque passage de relais est une **affectation sur la fiche ou le dossier**, comme les colonnes `*_id` de GAOV. Le poste suivant voit le dossier arriver dans « Ma journée ».
- L'administrateur voit toute la chaîne.

### 3.3 Agences

- **Ce que GAOV confirme** : GAOV ne gère **qu'une agence**. Il a une seule ligne `agence_settings` (nom, ville, NINEA, registre du commerce, cachets), et aucun champ d'agence sur les comptes ni sur les clients.
- **Ce que le site public mentionne** : un siège à Dakar, une agence à Ziguinchor et un relais à Paris.
- **Proposition** :
  - la nouvelle plateforme prévoit un champ *agence* sur les comptes, les fiches et les dossiers, **renseigné par défaut à « Dakar »** ;
  - le filtre de périmètre par agence est prêt, mais **inactif** tant qu'une seule agence est utilisée ;
  - il s'active sans refonte si l'agence le décide (§3.6).

### 3.4 Règle de périmètre (côté serveur, partout)

- Un poste ne voit que **les fiches et dossiers qui lui sont affectés**, sauf les postes à vue transversale (administrateur, secrétaire, comptable, selon la matrice).
- **Un seul filtre**, appliqué partout : listes, compteurs, recherche, fiche ouverte par URL directe, téléchargement de pièce, export.
- Le menu n'est **jamais** la seule barrière : chaque accès est revérifié côté serveur.
- **Cumul** : un compte a **un poste**. Une exception nominative (par exemple une secrétaire autorisée à enregistrer un paiement) est validée par l'administrateur, datée et visible dans le journal d'audit.

### 3.5 Matrice des actions (proposition à valider)

**Légende** :
- *tout* = toutes les fiches ;
- *affecté* = fiches et dossiers qui lui sont affectés ;
- *non attr.* = dossiers non encore affectés à ce poste ;
- *propre* = le client pour ses propres dossiers ;
- — = refusé.

| Action | Admin | Secrétaire | Agent prospection | Commerciale | Conseiller | Agent orientation | Agent traitement | Comptable | Client |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Créer une fiche personne | oui | oui (visiteur) | oui (prospect, import) | oui (prospect) | oui | — | — | — | — (s'inscrit) |
| Voir la liste des personnes | tout | tout (coordonnées, statut) | ses prospects | affecté | affecté | affecté | affecté | tout (coordonnées, finance) | — |
| Voir un dossier non attribué | oui | en-tête | — | — | non attr. (pour le prendre) | non attr. (pour le prendre) | non attr. (pour le prendre) | — | propre |
| Voir un dossier d'un collègue du même poste | oui | en-tête | — | — | — | — | — | en-tête et finance | — |
| Affecter ou réaffecter | oui | vers conseiller | vers commerciale (lot) | — | vers agent d'orientation | vers agent de traitement | — | — | — |
| Transférer un de ses dossiers à un collègue | oui | — | — | demande, validée par l'admin | demande, validée par l'admin | demande, validée par l'admin | demande, validée par l'admin | — | — |
| Agenda : créer, confirmer, déplacer | tout | tout | — | ses rendez-vous | ses rendez-vous | ses rendez-vous | — | — | réserver, déplacer les siens |
| Télécharger une pièce | oui (journalisé) | — | — | — | affecté (journalisé) | affecté (journalisé) | affecté (journalisé) | justificatifs de paiement | propre |
| Valider ou refuser une pièce | oui | — | — | — | affecté | affecté | affecté | justificatifs de paiement | — |
| Étapes de procédure (Campus France, visa, CAQ…) | oui | — | — | — | lecture | lecture | **affecté (écriture)** | lecture | lecture (étapes partagées) |
| Choix de destination, recommandations | oui | — | — | — | affecté | **affecté (écriture)** | lecture | — | lecture |
| Note interne | lire et écrire | — | ses prospects | affecté | affecté | affecté | affecté | finance | **jamais** |
| Message au client (fil visible) | oui | oui | — | affecté | affecté | affecté | affecté | sujets financiers | propre |
| Devis, échéancier, règlement, reçu | oui | — (sauf exception nominative) | — | — | — | — | — | **oui** | consulter, télécharger ses reçus |
| Corriger ou annuler un paiement | oui | — | — | — | — | — | — | demande, validée par l'admin | — |
| Dépenses | oui | — | — | — | — | — | — | oui | — |
| Établissements et campagnes de prospection | oui | — | **oui** | lecture | — | — | — | — | — |
| Import Excel de prospects | oui | — | **oui** | — | — | — | — | — | — |
| Export (Excel ou CSV) | oui (journalisé) | — | ses prospects | affecté | — | — | — | finance (journalisé) | — |
| Contrats (modèles, archive) | oui | — | — | — | — | — | — | génération depuis le dossier | ses contrats |
| Équipe, postes, paramètres, modèles de procédure | oui | — | — | — | — | — | — | — | — |

**Test obligatoire de la phase 1** : le conseiller A tente d'ouvrir, puis de télécharger, la pièce d'un dossier affecté au conseiller B.
- **Résultat attendu** : 403 sur la fiche, le téléchargement et l'export.
- Le dossier est absent des listes, des compteurs et de la recherche de A.
- La tentative est journalisée.

Le même test est fait pour chaque poste, ainsi que pour un **client** qui tenterait une adresse de l'équipe.

### 3.6 Décisions arrêtées (version 2.3, 26 novembre 2026)

**L'utilisateur confirme** que tous les comptes de GAOV sont des **comptes de test**, et donne **carte blanche** sur le reste. Décisions prises en conséquence :

1. **Comptes** : aucun compte de GAOV n'est repris. L'équipe réelle est créée par **invitation** dans la nouvelle plateforme. Les 8 postes restent la référence des métiers (§3.1), mais les effectifs de GAOV ne disent rien de l'équipe réelle.
2. **Cumuls** :
   - **un poste par personne** ;
   - les besoins réels de cumul se couvrent par des **missions additionnelles nominatives**, prédéfinies et tracées : « encaissement au guichet », « orientation », « prospection », « traitement des dossiers » ;
   - chacune est attribuée par l'administrateur, avec date et journal d'audit ;
   - une personne peut donc couvrir deux missions sans créer de poste hybride.
3. **Agences** :
   - **Dakar (siège)** est active au démarrage ;
   - **Ziguinchor** et **Paris** sont créées et **inactives**. Elles s'activent depuis les Paramètres, sans développement ;
   - tant qu'une seule agence est active, le filtre d'agence reste invisible ;
   - dès qu'une deuxième est activée : chaque membre est rattaché à une agence ; l'administrateur et les comptes autorisés nominativement voient plusieurs agences ; les chiffres se lisent par agence et en total.
4. **Matrice des droits** : celle du §3.5 est **adoptée**. Elle sera vérifiée par les tests automatiques de chaque phase, et ajustée sur retour d'usage.

**Conséquence pour la reprise (§9)** : seuls les **référentiels** de GAOV sont repris, après contrôle :
- destinations ;
- types de services ;
- établissements ;
- modèles de messages ;
- mentions de l'agence (NINEA, registre du commerce).

Les comptes, clients et rendez-vous de test ne sont **pas** importés.

### 3.7 Sécurité de l'ancienne application

- Des mesures de sécurité concernant l'ancienne application GAOV ont été signalées à l'agence. Leur détail est conservé **hors du dépôt public**, dans un document local, tant que GAOV reste en ligne.
---

## 4. Données : personne, compte, dossiers

### 4.1 Modèle

- **Personne** (`people`) : identité et coordonnées, profil (élève, étudiant, parent, particulier, entreprise), agence, origine (prospect, visiteur, site, import). Elle peut exister **sans compte** : un prospect ou un visiteur.
- **Compte** (`users`) : la connexion. Il est facultatif, avec **au plus un compte par personne**, et lié à la personne par `person_id`.
- **Liens** (`person_relations`) : parent ou tuteur légal d'un élève.
  - Le lien est **vérifié par l'équipe** (pièce justifiant le lien, saisie par un conseiller), puis activé avec ses droits : lire, déposer une pièce, payer.
  - Un élève majeur peut retirer l'accès de son parent.
- **Dossier** : un projet de la personne, défini par un service, une destination, une rentrée et un responsable. **Décision D1 de la phase 2 (27/11/2026)** : la table `service_requests` devient la table des dossiers, enrichie de `person_id`, du modèle de procédure et des étapes ; il n’y a pas de table `cases` séparée.
  - Une personne peut avoir **plusieurs dossiers en parallèle**, par exemple France et Canada pour la même rentrée, et d'autres plus tard pour une autre rentrée.
  - Chaque dossier a ses étapes, ses pièces, ses réponses et son résultat.
- **Coordonnées partagées** : un téléphone ou un e-mail peut être commun à plusieurs personnes (un parent pour ses enfants). L'unicité de l'e-mail ne s'impose qu'aux **comptes**, pas aux personnes.

### 4.2 Doublons

- Une **détection** propose des rapprochements : nom et date de naissance proches, même téléphone **et** même nom, même établissement.
- **Aucune fusion automatique.** Un membre habilité compare les fiches côte à côte et valide.
- **La fusion conserve tout** : les références des dossiers, les pièces, les notes, les historiques, les paiements. La fiche absorbée garde une redirection vers la fiche conservée, et l'opération est journalisée.

### 4.3 Correspondance « demandes actuelles → dossiers », sans double suivi

Avec la décision D1, il n'y a **pas de conversion** : les demandes **sont** les dossiers.
- **Phase 2** (migration 011) : création d'**une personne** par compte client existant, et rattachement de ses demandes et de ses rendez-vous (`person_id`). Les rendez-vous sans compte sont rattachés par correspondance prudente, sans fusion ; voir `gestion-phase-2.md` §9, C5.
- **Phase 3** : compléments des dossiers (pièces attendues, échéances, résultats) sur la même table.

Il n'existe donc qu'un **seul** suivi. Le formulaire public de demande crée directement un dossier. Les anciennes adresses de demande, côté client et côté équipe, redirigent vers le dossier.

### 4.4 Cycle de vie et conservation, à décider **avant** toute fonction de suppression

- **Constat** : aujourd'hui, supprimer un compte client effacerait en cascade ses demandes, pièces et événements (`ON DELETE CASCADE`), et les fichiers resteraient sur le disque. Aucune fonction ne supprime de compte pour l'instant.
- **Règle à mettre en place dès la phase 1** :
  - on **désactive**, on ne supprime pas ;
  - les cascades vers les dossiers sont remplacées par `RESTRICT`, par migration, pour que la base empêche tout effacement accidentel.
- **Effacement à la demande** de la personne (droit à l'effacement) :
  - procédure dédiée en phase 7 : anonymisation de l'identité et suppression des fichiers, en conservant les pièces comptables pendant la durée légale ;
  - réservée à l'administrateur, avec double confirmation et journal.
- **Durées de conservation** par type de donnée (prospect inactif, dossier clos, pièces d'identité, pièces comptables) : **à faire valider par l'agence** (§8).

---

## 5. Phases réordonnées : un parcours métier complet d'abord

### Phase 1 : socle sécurisé et coquille

**Rôles et périmètre**
- **8 postes et l'accès Client** (§3.1), champ agence (Dakar par défaut), exceptions nominatives ; filtre de périmètre unique par affectation.
- **Correction de l'accès aux pièces par affectation.**
- Matrice appliquée aux modules existants.

**Connexion et transition**
- Règle unique de redirection (§2.4).
- Passage à `/gestion/` avec redirections 302 et routes POST de compatibilité (§2.3).

**Coquille de l'espace équipe**
- Barre latérale repensée par groupes, avec les compteurs du périmètre.
- Barre supérieure avec bouton « + Créer » et notifications (squelette).
- Version mobile (onglets en bas), bibliothèque de composants.

**Approbation préalable** : une **maquette navigable sur ordinateur et téléphone** (§12.3), avec la navigation, « Ma journée », une liste, une fiche dossier et le suivi côté client. Elle s'accompagne de tâches concrètes à essayer : confirmer un rendez-vous, reprendre un dossier, comprendre quelle pièce manque. Elle doit être validée (charte et facilité d'utilisation) **avant** de décliner les autres écrans.

**Sécurité**
- Journal d'audit des accès et modifications sensibles.
- Remplacement des cascades par `RESTRICT`.
- Règles d'accès aux pièces et visibilité des notes internes.
- Sauvegardes protégées (§8).

**Outils de test** : robot de recette `bin/recette.php` et jeu de données **fictives** de test.

### Phase 2 : parcours prioritaire, partie A (du rendez-vous à l'ouverture du dossier)

- **Fiche personne** (sans compte possible) et lien éventuel avec le compte.
- **Agenda** (§6.1) : créneaux, demande puis réservation confirmée, anti-double réservation, fuseaux.
- **Compte rendu d'entretien**, visible par le client s'il est marqué « partagé ».
- **Ouverture d'un dossier d'admission** depuis la fiche, à partir d'un modèle de procédure (§6.2).
- **E-mails** par la file existante : confirmation, rappel de rendez-vous, compte rendu disponible.
- **« Ma journée »** : rendez-vous, tâches, dossiers à reprendre.
- **Recherche globale**, introduite **ici** : personnes et dossiers du périmètre, résultats ouvrables.

### Phase 3 : parcours prioritaire, partie B (du dossier aux rappels)

- **Compléments des dossiers** sur la même table (D1, §4.3).
- **Côté client** : pièces attendues, dépôt, état du dossier dans `/mon-espace/`.
- **Côté équipe** : validation ou refus motivé des pièces, contrôle de validité selon la destination et la démarche (§6.3).
- **Échéances et rappels par e-mail**, au client et à l'équipe, par tâche cron.
- **Résultats d'admission** par établissement.
- **Accès parent ou tuteur vérifié** (§4.1).

**Scénario de bout en bout à valider** :
1. Une personne réserve un rendez-vous.
2. Elle reçoit le compte rendu.
3. Un dossier d'admission est ouvert.
4. Elle dépose une pièce.
5. Elle voit l'état de son dossier.
6. Elle reçoit un rappel.
7. L'équipe suit l'action dans « Ma journée » et reprend le dossier depuis la fiche personne.

### Phase 4 : accueil et doublons

- Registre des visites et file d'attente du jour ; conversion d'une visite en fiche.
- Détection des doublons et fusion validée (§4.2).

### Phase 5 : finance V1 (enregistrement, sans encaissement en ligne)

- Périmètre (§7) : **devis, échéancier, enregistrement d'un règlement déjà reçu, reçu PDF numéroté.**
- La version locale applique les règles provisoires du [document de phase](gestion-phase-5.md) pour les droits, les corrections, la devise et la numérotation.

### Phase 6 : prospection scolaire

- **Agenda de la commerciale** (§3.5, décision D2) : les rendez-vous des prospects attribués s’affichent dans le module commun. L’accueil confirme et affecte les propositions.

- Établissements et campagnes.
- Import Excel avec dédoublonnage et rapport d'écarts.
- Attribution aux commerciaux, conversion des prospects.

### Phase 7 : communication étendue, pilotage et conformité

- Modèles de messages.
- SMS et WhatsApp, après le choix du fournisseur.
- Tableaux de bord par rôle, exports journalisés.
- Procédure d'effacement et d'export des données d'une personne.

État au 28/11/2026 : modèles de messages et liens WhatsApp ou e-mail journalisés (7A), pilotage et exports CSV journalisés (7B), export JSON, anonymisation et liste des durées de conservation (7C) faits ; détail dans [gestion-phase-7.md](gestion-phase-7.md). L'envoi de SMS attend le choix d'un fournisseur.

### Phase 8 : signature en ligne et encaissement intégré (facultatif)

À lancer seulement après le choix des prestataires, des modalités de signature et un environnement de test fourni par le prestataire.

### Phase 9 : recette d’ensemble et préparation de la mise en service

- Aucune reprise de données GAOV (version 2.4) : les référentiels (destinations, établissements, modèles) sont saisis par l’agence dans la nouvelle plateforme.
- Solder la dette de test notée à chaque étape (2C, 2D, phase 7…) : droits de chaque route pour les 8 postes et le client, scénarios métier de bout en bout, moteur cible MariaDB 10.11, migrations 011 à 024 répétées sur une copie de la base de production.
- Préparer la mise en service : sauvegarde restaurable, ordre des migrations, comptes réels de l’équipe, tâches planifiées (e-mails, rappels).

### Phase 10 : refonte des interfaces

Les écrans actuels sont fonctionnels mais trop simples : ils ne guident pas assez au premier regard. Cette phase reprend le design de **chaque profil** (client, 8 postes de l’équipe) pour qu’il soit à la fois responsive, intuitif, ergonomique, beau et attractif.

- **Espace client en priorité de soin** : c’est la vitrine de la marque. Le client juge le professionnalisme de l’agence sur cet espace. Il faut donc une finition au niveau du site public : hiérarchie claire, état du dossier compris en un coup d’œil, prochaine action toujours visible.
- **Lisibilité au premier regard**, pour chaque poste :
  - une seule action principale par écran, mise en avant ;
  - les actions secondaires sont groupées ;
  - chaque bouton dit ce qu’il fait ;
  - les éléments sont regroupés par tâche, pas par table de la base.
- **Charte de la gestion**, précisée par l’utilisateur le 28/11 :
  - **une seule police, Poppins**, pour tous les titres et tous les textes de la gestion ;
  - mini-cartes de statistiques : **le chiffre sur la même ligne que l’icône** (icône à gauche, chiffre à droite), le libellé dessous ;
  - couleurs des boutons : bleu pour le courant, vert pour valider, orange pour avertir, rouge seulement pour le danger ;
  - surfaces teintées, jamais de bordures colorées ni de dégradés ;
  - affichage compact sur mobile.
- Méthode :
  - maquette de l’écran clé de chaque profil, validée avec l’utilisateur avant de décliner ;
  - puis contrôle visuel complet à 1440, 768 et 375 px, avant la mise en service.

### Phase 11 : configuration des notifications

Ajoutée le 28/11/2026 à la demande de l’utilisateur, après la mise en service. Étape 1 : e-mails et tâches automatiques, avec les paramètres SMTP fournis par l’utilisateur (`config/mail.local.php` sur le VPS). Étape 2 : notifications push, à partir des éléments qu’il fournira. Détail : [gestion-phase-11.md](gestion-phase-11.md).

---

## 6. Règles métier précisées

### 6.1 Agenda

- **Fuseaux** :
  - chaque agence a son fuseau (`Africa/Dakar` pour Dakar et Ziguinchor, `Europe/Paris` pour Paris) ;
  - les dates sont stockées en **UTC** et affichées dans le fuseau de l'agence du rendez-vous ;
  - pour un rendez-vous en visio, l'heure s'affiche aussi dans le fuseau du client.
- **Statuts** : *demandé* (formulaire public, sans engagement), puis *confirmé* (créneau réservé par l'équipe ou réservation directe sur un créneau ouvert), puis *honoré*, *absent* ou *reporté*, ou *annulé*.
- **Anti-double réservation** : contrainte d'unicité en base sur le couple (conseiller, début du créneau), plus une transaction. Deux réservations simultanées : une seule réussit, l'autre reçoit un message clair.

### 6.2 Modèles de procédure

- **Contenu** : un modèle par couple **destination et démarche** (exemple : France, admission par Campus France). Il décrit les étapes, les pièces, les délais types et les **sources officielles** (URL), avec la **date de dernière vérification** et son responsable.
- **Versions** : un dossier garde la version du modèle utilisée à son ouverture. Une nouvelle version ne modifie pas les dossiers en cours ; elle se propose au responsable.
- **Alerte de fraîcheur** : un modèle non vérifié depuis plus de 6 mois (durée paramétrable) est signalé à l'administrateur.

### 6.3 Validité des pièces

Les règles sont configurées **par destination et par démarche**, sans valeur unique codée en dur :
- validité minimale du passeport après la date de retour ou de fin de séjour ;
- ancienneté maximale des relevés bancaires ;
- traductions exigées.

---

## 7. Finance : périmètre précisé

- **Enregistrer un paiement déjà reçu** (espèces, Wave, Orange Money, virement…) est différent d'**encaisser en ligne** par une intégration de prestataire. La **V1 (phase 5) ne fait que l'enregistrement.**
- **V1** : devis, échéancier, enregistrement d'un règlement, **reçu PDF numéroté**, état des paiements dans l'espace client.
- **Décisions à prendre avant la phase 5** :
  - **Responsables** : qui enregistre, qui valide.
  - **Corrections** : on ne modifie jamais un reçu émis ; on passe une **écriture d'annulation** liée au reçu, avec un motif, puis on émet un nouveau reçu.
  - **Remboursements** : qui décide, sous quelle forme, dans quel délai.
  - **Devise** : FCFA (XOF), plus l'euro pour l'agence de Paris ? Pas de conversion automatique.
  - **Numérotation** : séquence continue par agence et par année, sans trou, par exemple `DKR-2027-000123`.
- **Plus tard, sur décision** : prestataires de paiement en ligne, signature électronique, modalités de facturation légale.

---

## 8. Données personnelles et sécurité dès le socle

- **Pièces** :
  - stockées hors du web (comme aujourd'hui) et servies seulement après contrôle de périmètre ;
  - chaque téléchargement par l'équipe est journalisé ;
  - aucun lien public.
- **Notes** : *interne* (jamais visible du client ni des rôles hors affectation) ou *partagée* (visible du client). Le choix est explicite à la saisie.
- **Journal d'audit** :
  - accès à une pièce, export, modification de rôle ou de droits, transfert, fusion, correction financière, effacement ;
  - le journal n'est pas modifiable depuis l'application.
- **Sauvegardes** : base et pièces, **chiffrées**, conservées hors du serveur avec rotation ; **test de restauration** documenté en phase 1 puis à chaque mise en service.
- **Cadre légal à faire valider par l'agence** (le plan ne tranche pas à sa place) :
  - **Loi sénégalaise n° 2008-12** sur les données personnelles, avec une éventuelle déclaration auprès de la CDP ([texte](https://www.archives.sn/docs/codes/loi-protection-donnees-a-caractere-personnel)).
  - **Application éventuelle du RGPD** : agence de Paris (art. 3), base légale (art. 6), effacement (art. 17), transferts hors de l'Union européenne (art. 44 et suivants) ([texte](https://eur-lex.europa.eu/eli/reg/2016/679/oj/fra)).
  - **Mineurs** : consentement et rôle du représentant légal.
  - **Transferts internationaux** : dossiers transmis à des établissements étrangers, hébergement, prestataires (dont l'IA).
  - **Durées de conservation.**
- **Assistant IA** : son index et ses réponses restent limités au **contenu public** de la plateforme. Aucune donnée de dossier, de personne ou de pièce ne lui est transmise. Une règle de test le vérifie à chaque phase.

---

## 9. Reprise des données GAOV — abandonnée (version 2.4)

> **Abandonnée le 28/11/2026** : l’ancienne application n’est plus utilisée, rien n’en est repris. Le tableau ci-dessous reste comme historique.

| Donnée GAOV | Volume | Décision | Cible |
| --- | --- | --- | --- |
| `destinations` | 196 | reprendre après contrôle | référentiel destinations |
| `type_visites`, `type_dossiers`, `type_clients`, `type_audiences` | 11, 7, 3, 2 | rapprocher du catalogue de services et des modèles de procédure | catalogue et modèles |
| `roles` | 10 | **correspondance directe** (8 postes et Client, §3.1) | postes |
| `users` | 16 internes et 2 étudiants | **ne pas reprendre** : comptes de test, confirmé par l'utilisateur ; l'équipe réelle est invitée dans la nouvelle plateforme | — |
| `clients` | 2 | **ne pas reprendre** (données de test) | — |
| `rendez_vous` | 2 | **ne pas reprendre** (données de test) | — |
| `etablissements` | 4 | reprendre | prospection (phase 6) |
| `com_personnalise` | 5 | relire et adapter | modèles de messages |
| `agence_settings` | 1 | rapprocher | paramètres d'agence |
| Pièces (fichiers) | à inventorier | vérifier leur présence et leur lien avec un client, sinon les écarter | pièces du dossier |

- **Import relançable** `bin/import-gaov.php` :
  - idempotent, avec correspondance des identifiants conservée ;
  - **rapport de rapprochement** : repris, écartés, écarts à traiter.
- **Répétitions** :
  - sur une **copie protégée** de la base GAOV, jamais versionnée ;
  - pour les tests courants, un **jeu fictif ou anonymisé**.
- **Bascule** :
  1. sauvegarde **restaurable** des deux bases et des fichiers, avec un test de restauration ;
  2. **gel des écritures** dans GAOV (lecture seule) ;
  3. import final et rapport ;
  4. recette ;
  5. **retour arrière** documenté si la recette échoue : réouverture de GAOV et restauration de la plateforme.
- **Hors plan** : mesures de sécurité de l'ancienne application (document local, hors dépôt).

---

## 10. Validation : scénarios et résultats attendus

Pour chaque phase, le document « avant » liste les scénarios. Le document « après » donne, pour chacun, le résultat attendu et le résultat obtenu. Contrôles systématiques :

| Contrôle | Résultat attendu |
| --- | --- |
| Soumission d'un formulaire valide | **303** vers la page de résultat ; effet vérifié **en base** |
| Double soumission (double clic, retour arrière) | **aucun doublon** en base : jeton à usage unique ou contrainte d'unicité |
| Formulaire invalide | message clair en français, saisie conservée, rien en base |
| Dossier hors périmètre ouvert par URL directe | **403** ; absent des listes, compteurs, recherche et exports ; tentative journalisée |
| Pièce hors périmètre (conseiller A sur le dossier de B) | **403** au téléchargement ; journalisé |
| Note interne | jamais visible du client ni d'un rôle non affecté |
| Retour après connexion (`?retour=` vers un formulaire de visa ou d'admission) | retour au formulaire si l'accès est autorisé, sinon page de l'espace |
| Anciennes adresses `/admin/` (GET et POST) | GET en 302 vers `/gestion/…` ; POST traité à l'identique, avec droits et CSRF |
| Parcours historiques | les demandes, pièces et événements existants restent consultables après migration |
| Robot de recette | toutes les routes de tous les rôles sans erreur 500 |
| Clavier | écrans principaux utilisables sans souris ; focus visible ; Échap ferme les panneaux |
| Affichage | ordinateur 1440 px, tablette 768 px, téléphone 375 px, avec des comptes de chaque rôle et des données représentatives (fictives) |
| Assistant IA | aucune donnée privée dans son index |

---

## 11. Suivi

| Phase | Document | État |
| --- | --- | --- |
| 0. Cadrage | `gestion-plan.md` (version 2.3) | **validé** : direction acceptée, carte blanche, décisions du §3.6 |
| 1. Socle sécurisé et coquille | `gestion-phase-1.md` | 1A et 1B validées ; **1B en production le 27/11/2026** (commit 403c4dd, migrations 007-010) ; validation en conditions réelles en cours |
| 2. Parcours A : rendez-vous → dossier | `gestion-phase-2.md` | 2.0, 2A et 2B closes en local. 2C codée, tests fonctionnels reportés à la recette d’ensemble. 2D close en local selon la [décision du validateur](validations/2026-11-27-fin-etape-2d.md). Publication de la phase 2 en attente de la recette d’ensemble. |
| 3. Parcours B : pièces → rappels | `gestion-phase-3.md` | 3A et 3B validées en local. Qualification finale sur le moteur cible et contrôle visuel en attente avant publication. |
| 4. Accueil et doublons | `gestion-phase-4.md` | Registre et rapprochement manuel disponibles en local. Qualification et cas de fusion avec comptes à suivre. |
| 5. Finance V1 | `gestion-phase-5.md` | implémentée et testée en local, migration 021 appliquée sur WAMP |
| 6. Prospection scolaire | `gestion-phase-6.md` | implémentée et testée en local, migration 022 appliquée sur WAMP |
| 7. Communication, pilotage, conformité | `gestion-phase-7.md` | codée et commitée le 28/11 (7A, 7B, 7C), robot 172/172 ; tests visuels à la recette d’ensemble |
| 8. Signature et encaissement en ligne | `gestion-phase-8.md` | facultatif, en attente du choix des prestataires |
| 9. Recette d’ensemble et préparation de la mise en service | `gestion-phase-9.md` | Recette et bascule terminées, migration 024 en production. Comptes réels et SMTP en attente. |
| 10. Refonte des interfaces | `gestion-phase-10.md` | publiée avec `4922bec`, 57 contrôles d’affichage sur les parcours principaux du client et des huit postes |
| 11. Configuration des notifications | `gestion-phase-11.md` | consignée le 28/11 : e-mails (SMTP fourni par l’utilisateur) et tâches automatiques, puis notifications push |

---

## 12. Refonte des parcours et de l'expérience par poste

*Versions 2.1 et 2.2 : section construite à partir des huit postes réels de GAOV (§3.1).*

**Principe.** Chaque poste arrive sur **« Ma journée »**, qui répond à trois questions : *qu'ai-je à faire aujourd'hui ?*, *qu'est-ce qui est en retard ou bloqué ?*, *qu'est-ce qui vient de m'être confié ?*.
- Les actions fréquentes se font **en un geste depuis la liste** (panneau latéral).
- Les écrans communs (fiche personne, agenda, dossier, notes, recherche, tâches) sont **mutualisés**. Chaque poste n'y voit que ses informations et ses actions autorisées (§3.5).

### 12.1 Décisions sur les fonctions de GAOV

| Fonction GAOV | Décision | Ce qu'elle devient |
| --- | --- | --- |
| Prospects, visiteurs, clients, étudiants (conversions manuelles entre tables) | **Mutualiser les données** | une **fiche personne** unique, avec un statut de parcours et les affectations par poste ; la conversion devient un changement d'étape |
| Rendez-vous gérés dans 4 contrôleurs (`/rendezvous` déclaré 4 fois) | **Mutualiser l'écran** | un seul **agenda** ; chaque poste (secrétaire, commerciale, conseiller, agent d'orientation) le voit filtré sur ses rendez-vous |
| Replanifier, reprogrammer, reporter (3 fonctions qui se recouvrent) | **Simplifier** | une seule action **« Déplacer »**, avec motif et historique |
| Commentaires de la commerciale, du conseiller, remarques et commentaires du dossier | **Mutualiser** | des **notes** sur la fiche ou le dossier, *internes* ou *partagées*, signées par leur auteur et son poste |
| Conseil (conseiller) et orientation (agent d'orientation) | **Garder deux postes distincts** | le conseiller rédige le **compte rendu d'entretien** et ouvre le dossier ; l'agent d'orientation fixe la **destination et les recommandations**, puis tient le rendez-vous d'orientation. Les deux postes travaillent sur le même dossier, chacun dans son volet |
| Prospection (agent de prospection) et suivi commercial (commerciale) | **Garder deux postes distincts** | l'agent de prospection gère **établissements, campagnes et import**, puis **attribue des lots** ; la commerciale **appelle, relance et pose les rendez-vous** |
| Traitement par saisies successives (8 formulaires figés : courriel étudiant, Campus France, France Visa, université, CAQ, Arrima, IRCC, ambassade) | **Simplifier et généraliser** | les **étapes du modèle de procédure** du dossier (§6.2), tenues par l'agent de traitement, avec une même interface pour toutes les destinations |
| 7 contrats PDF codés en dur | **Simplifier** | des **modèles de documents** par service, générés depuis le dossier ; signature en ligne en phase 8 |
| Paiements séparés par type (visa, assurance, prise en charge, hébergement, autres) | **Mutualiser** | un **échéancier par dossier** ; le type devient une ligne de prestation du catalogue |
| Dépenses, revenus, bénéfices | **Conserver et simplifier** | finance V1 : caisse, dépenses par catégorie, rapport mensuel |
| Statistiques dispersées (une quinzaine d'appels `stats/...`, plusieurs plantent) | **Remplacer** | un tableau de bord par poste, avec peu d'indicateurs, tous fiables |
| SMS de rappel (Twilio non configuré) | **Reporter** | e-mail dès la phase 2 ; SMS et WhatsApp en phase 7, après le choix du fournisseur |
| Signature en ligne de l'étudiant | **Reporter** | phase 8, sur décision |
| Invitation, suppression douce, restauration du personnel | **Conserver** | déjà en place : invitation, désactivation, réactivation |
| `/setup` et paramètres de l'agence (NINEA, registre du commerce, cachets) | **Conserver dans une page Paramètres** | agence (avec ses mentions légales), services et tarifs, modèles |
| Espaces étudiant et non-étudiant | **Mutualiser l'accès, garder deux parcours** | un **accès Client** commun ; les dossiers *études* et *voyage* ont des étapes et un vocabulaire adaptés |
| Routes et fonctions cassées ou inachevées | **Retirer** | rien n'est repris sans besoin établi |
| Contrôle des droits | **Renforcer** | contrôle serveur systématique (§3.4) |
| — | **Ajouter** | dossiers multiples par personne, liens parent ou tuteur, « Ma journée », tâches, procédures versionnées, validation des pièces, échéances et rappels, résultats d'admission et de visa, recherche globale, journal d'audit, notifications |

### 12.2 Parcours et écrans par poste

#### Administrateur (1 compte)

- **Tâches principales**
  - Piloter.
  - Gérer l'équipe et les postes, les paramètres de l'agence, les tarifs, les modèles de procédure et les contrats.
  - Arbitrer les transferts et les corrections.
- **À afficher en priorité**
  - La chaîne de travail : volume à chaque étape et blocages.
  - Les validations en attente.
  - Les indicateurs : contacts, rendez-vous honorés, dossiers ouverts, taux d'admission et de visa, encaissements.
  - Les modèles à revérifier.
- **Actions à rendre accessibles**
  - Inviter un membre.
  - Valider un transfert ou une correction.
  - Réaffecter un dossier.
  - Publier une version de modèle.
- **Étapes à simplifier** : les paramètres sont réunis sur une page à onglets.

#### Secrétaire (2 comptes)

- **Tâches principales**
  - Accueillir les visiteurs.
  - Tenir l'agenda.
  - Confirmer les demandes de rendez-vous.
  - Affecter au conseiller.
- **À afficher en priorité**
  - La **file d'attente du jour** : qui attend, pour qui, depuis quand.
  - Les rendez-vous du jour par conseiller.
  - Les **demandes de rendez-vous à confirmer**, venues du site ou d'une commerciale.
- **Actions à rendre accessibles**
  - « **+ Visite** » en une saisie : la fiche existante est retrouvée, sinon elle est créée.
  - « **Confirmer** ».
  - « **Affecter au conseiller** ».
  - « **Appeler le suivant** ».
- **Étapes à simplifier**
  - GAOV : visiteur, conversion, rendez-vous.
  - Nouveau : une saisie, avec le rendez-vous posé dans le même panneau.

#### Agent de prospection (1 compte)

- **Tâches principales**
  - Constituer la base de prospects par établissement et niveau.
  - Préparer les campagnes.
  - Importer les listes.
  - Attribuer les lots aux commerciales.
- **À afficher en priorité**
  - Les campagnes en cours et leur couverture : établissements visités, prospects collectés.
  - Les **prospects non attribués**.
  - Les imports récents et leurs doublons.
- **Actions à rendre accessibles**
  - « **Importer** » (Excel) avec aperçu et rapport de doublons.
  - « **Attribuer à une commerciale** » en masse depuis la liste.
  - « **+ Établissement** ».
- **Étapes à simplifier** : les sessions, le rattachement et l'attribution de GAOV (3 écrans) se font dans **une seule liste filtrée** avec sélection multiple.

#### Commerciale (2 comptes)

- **Tâches principales**
  - Appeler les prospects qui lui sont attribués.
  - Relancer.
  - Proposer des rendez-vous.
- **À afficher en priorité**
  - Les **prospects à appeler aujourd'hui** (rappels programmés).
  - Les rendez-vous proposés en attente de confirmation.
  - Son taux de conversion.
- **Actions à rendre accessibles**
  - « **Appelé** », avec un résultat en un geste : joignable, intéressé, rappeler le…, pas intéressé.
  - « **Proposer un rendez-vous** », qui part dans la file de confirmation de la secrétaire.
  - « **Noter** ».
- **Étapes à simplifier** : une liste « **Mes prospects** », avec l'appel et la relance intégrés.

#### Conseiller (2 comptes)

- **Tâches principales**
  - Mener l'entretien.
  - Constituer la fiche (projet, niveau, budget).
  - Ouvrir le dossier.
  - Suivre ses clients.
- **À afficher en priorité**
  - Les **rendez-vous du jour**, avec la fiche résumée.
  - Les dossiers affectés à reprendre.
  - Les tâches en retard.
- **Actions à rendre accessibles**
  - « **Commencer l'entretien** » : compte rendu prérempli.
  - « **Ouvrir un dossier** » (études ou voyage, modèle de procédure).
  - « **Affecter à l'agent d'orientation** ».
  - « **Noter** ».
- **Étapes à simplifier** : le compte rendu, l'ouverture du dossier et l'affectation s'enchaînent sur le même écran.

#### Agent d'orientation (2 comptes)

- **Tâches principales**
  - Orienter les étudiants affectés : filières, établissements, pays.
  - Tenir le rendez-vous d'orientation.
  - Transmettre au traitement.
- **À afficher en priorité**
  - Les **étudiants à orienter**.
  - Les rendez-vous d'orientation du jour.
  - Les dossiers orientés en attente de traitement.
- **Actions à rendre accessibles**
  - « **Recommander** » une destination et des établissements, avec les rentrées.
  - « **Planifier le rendez-vous d'orientation** ».
  - « **Affecter à l'agent de traitement** ».
- **Étapes à simplifier**
  - GAOV : listes « à orienter » et « orientés », puis édition, puis planification, sur des écrans séparés.
  - Nouveau : **une liste à onglets** (À orienter, Orientés) avec le panneau de recommandation.

#### Agent de traitement (4 comptes)

- **Tâches principales**
  - Mener les procédures affectées : candidatures, Campus France, France Visa, CAQ, Arrima, IRCC, ambassade, logement.
  - Valider les pièces.
  - Tenir les échéances.
  - Enregistrer les réponses.
- **À afficher en priorité**
  - Les **pièces à valider**.
  - Les **échéances des 7 prochains jours**.
  - Les dossiers **bloqués**, avec leur raison.
  - Les réponses à enregistrer.
- **Actions à rendre accessibles**
  - « **Valider** » ou « **Refuser avec motif** » une pièce, avec un aperçu.
  - « **Marquer l'étape faite** ».
  - « **Relancer le client** » (message généré listant ce qui manque).
  - « **Saisir une réponse** ».
- **Étapes à simplifier** : les 8 formulaires figés de GAOV deviennent **une checklist d'étapes** propre au dossier.

#### Comptable (2 comptes)

- **Tâches principales**
  - Établir les devis et échéanciers.
  - Enregistrer les règlements reçus et émettre les reçus.
  - Suivre les impayés.
  - Tenir les dépenses.
  - Générer les contrats depuis le dossier.
- **À afficher en priorité**
  - Les **échéances dues ou dépassées**.
  - Les encaissements du jour par moyen de paiement.
  - Les corrections en attente.
  - Les clients en règle.
- **Actions à rendre accessibles**
  - « **Enregistrer un règlement** », qui génère le reçu numéroté.
  - « **Relancer** ».
  - « **Annuler un reçu avec motif** ».
  - « **+ Dépense** ».
  - Export mensuel.
- **Étapes à simplifier** : les 5 listes de paiement par type de GAOV deviennent **une vue par dossier** et une liste « À encaisser ».

#### Client : accès commun, deux parcours

- **Tâches principales**
  - Réserver un rendez-vous.
  - Suivre ses dossiers.
  - Déposer les pièces demandées.
  - Suivre ses paiements.
  - Échanger avec l'équipe.
- **À afficher en priorité**
  - Un bloc **« Ce qu'il vous reste à faire »** : pièce manquante et raison, échéance, rendez-vous à venir, paiement dû.
  - Une **carte par dossier**, avec ses étapes lisibles et le nom de son interlocuteur.
- **Parcours *études*** (élève, étudiant, parent)
  - Étapes : orientation, admission (établissements et réponses), visa études, logement, départ.
  - Le parent ou tuteur vérifié voit les dossiers de l'élève (§4.1).
- **Parcours *voyage***
  - Étapes : visa (tourisme ou affaires), billet, hôtel, assurance, départ.
  - Aucun écran académique.
- **Actions à rendre accessibles**
  - « **Déposer la pièce** » depuis la ligne de la pièce manquante, avec le motif d'un éventuel refus.
  - « **Réserver** » ou « **Déplacer** » un rendez-vous.
  - « **Écrire** ».
  - Télécharger ses reçus.
- **Étapes à simplifier** : les 6 écrans séparés de GAOV (profil, documents, suivi, paiements, factures, demandes) deviennent **un tableau de bord unique**.

### 12.3 Maquette navigable à valider en phase 1

Avant de décliner les autres écrans, une **maquette navigable** est livrée dans la plateforme, en local, avec des **données fictives**. Elle se présente sur ordinateur et sur téléphone, dans la charte Taysir. Elle comprend :
- **la nouvelle navigation**, déclinée pour les **8 postes** : barre latérale par groupes avec compteurs, barre supérieure (recherche, « + Créer », notifications), onglets en bas sur téléphone ;
- **« Ma journée »** de chaque poste ;
- **une liste** (dossiers) avec filtres, tri, sélection multiple et panneau latéral ;
- **une fiche dossier** avec ses volets : entretien (conseiller), orientation (agent d'orientation), procédure (agent de traitement), paiements (comptable) ;
- **le suivi côté client**, en parcours *études* et en parcours *voyage*.

**Tâches concrètes à essayer**, avec le résultat attendu :

| # | Tâche | Poste | Réussite si… |
| --- | --- | --- | --- |
| T1 | Confirmer un rendez-vous proposé par une commerciale ou demandé sur le site | Secrétaire | depuis la liste, en moins de 3 actions, sans changer de page ; statut « confirmé » |
| T2 | Reprendre le dossier dont le client vient de déposer une pièce | Agent de traitement | le dossier apparaît dans « Ma journée » ; on valide la pièce sans chercher |
| T3 | Comprendre quelle pièce manque et pourquoi | Client (études, puis voyage) | la pièce, sa raison et le bouton de dépôt sont visibles sans défiler sur téléphone |
| T4 | Attribuer un lot de prospects à une commerciale | Agent de prospection | sélection multiple, puis « Attribuer » ; les prospects apparaissent chez la commerciale |
| T5 | Transmettre un dossier orienté au traitement | Agent d'orientation | recommandation saisie, puis « Affecter » ; le dossier apparaît chez l'agent de traitement |
| T6 | Trouver un client par son nom ou son téléphone | tous les postes | résultat en moins de 2 secondes, limité au périmètre du poste |
| T7 | Tenter d'ouvrir le dossier d'un collègue par son adresse | Conseiller | refus (403) ; aucune trace du dossier dans les listes ni la recherche |
| T8 | Naviguer au clavier jusqu'à une fiche et revenir | tous | focus visible, aucun piège clavier |

**Ce que l'utilisateur valide** : le rendu dans la charte et la facilité d'utilisation. Ses retours sont consignés dans `gestion-phase-1.md`, corrigés, puis représentés **avant** de construire les autres écrans.

### 12.4 Compte rendu de chaque phase

Le document `gestion-phase-N.md` contient toujours **trois parties** :
1. **Livré** : écrans, fonctions, migrations, fichiers.
2. **Tests** : scénarios du §10 et tâches du §12.3 ; résultat attendu et résultat obtenu pour chacun.
3. **Corrections** : anomalies trouvées et correctifs appliqués, puis nouveau test.

**La phase suivante ne commence qu'après l'accord explicite de l'utilisateur.**
