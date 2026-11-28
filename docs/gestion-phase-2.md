# Phase 2 : du rendez-vous à l’ouverture du dossier

Référence : [gestion-plan.md](gestion-plan.md), §5 (phase 2), §4.1 (modèle), §6.1 (agenda), §6.2 (modèles de procédure), §8 (données personnelles), §10 (contrôles) et §12 (parcours par poste).

Statut : **plan validé** le 27/11/2026 par l’agent `validateur` (GO SOUS CONDITIONS, décision complète : [validations/2026-11-27-phase-2-plan.md](validations/2026-11-27-phase-2-plan.md)). Les options retenues, les exigences C4 à C9 et la recette complétée sont aux §8 à §10 ; elles **priment** sur les sections 2 à 5 lorsqu’elles les précisent.

---

## 1. Objectif et résultat attendu

Livrer le **premier parcours métier complet côté agence** :

> Une personne demande ou réserve un rendez-vous → l’accueil le confirme sur un créneau réel → le conseiller tient l’entretien et rédige un compte rendu (partagé ou non avec la personne) → il ouvre un dossier d’admission à partir d’un modèle de procédure → le dossier entre dans la chaîne de 1B (orientation, traitement).

**À la fin de la phase 2** :
- les conseillers et les agents d’orientation retrouvent **leurs** rendez-vous, avec le périmètre de la 1B. Les rendez-vous sont aujourd’hui réservés à l’administrateur et à la secrétaire (revue 1B, point 1) ;
- « Ma journée » montre les rendez-vous du jour, les tâches et les dossiers à reprendre ;
- la recherche porte sur les **personnes** et les dossiers du périmètre.

## 2. Décisions à prendre avant de coder

| N° | Question | Recommandation | Pourquoi |
| --- | --- | --- | --- |
| D1 | **Un seul type de dossier** ? Le plan prévoyait une table `cases` distincte des demandes, rapprochées en phase 3 (§4.3). | **Faire évoluer `service_requests` en table des dossiers** (ajout de la personne, du modèle de procédure et des étapes), sans créer de table parallèle. | Évite deux suivis en parallèle pendant les phases 2 et 3 ; tout le socle 1B (périmètre, files, affectations, pièces, journal, robot) repose déjà sur cette table. La « migration » de la phase 3 se réduit alors au rattachement des demandes existantes à une personne. |
| D2 | **Qui ouvre les créneaux** ? | Chaque conseiller et agent d’orientation définit **sa semaine type** (jours, heures, lieu ou visio), avec des exceptions (congés, absences). La secrétaire et l’administrateur peuvent les gérer pour tous. | Agenda réaliste sans saisie quotidienne ; l’accueil garde la main. |
| D3 | **Réservation en ligne** : demande à confirmer (comme aujourd’hui) ou choix direct d’un créneau ? | **Choix direct** d’un créneau ouvert pour les entretiens d’orientation et le premier rendez-vous ; **demande** conservée quand aucun créneau ne convient. | Moins d’allers-retours, moins de rendez-vous perdus ; l’accueil confirme seulement les demandes. |
| D4 | **Durées et horaires** | Durée par motif (30 min renseignement, 45 min orientation, 60 min admission), horaires par agence (Dakar : à confirmer par l’agence). | Paramétrables, sans code. |
| D5 | **Modèles de procédure de départ** | 3 modèles en **brouillon** à faire vérifier par l’agence : France (Campus France), Canada (permis d’études), Belgique (équivalence et inscription). | La phase 2 livre l’outil ; le **contenu** (étapes, pièces, sources officielles) reste à valider par l’agence, avec la date de vérification. |

**Prérequis côté utilisateur** :
- SMTP fourni : sans lui, aucune confirmation ni aucun rappel ne part ;
- premier administrateur créé en production ;
- GAOV protégé.

## 3. Constat sur l’existant (vérifié dans le code)

- **Rendez-vous** (`appointments`) :
  - formulaire public `/rendez_vous_orientation/` : motif, mode, date et demi-journée souhaitées ;
  - statuts `demande`, `confirme`, `termine`, `annule` ;
  - `scheduled_at` saisi à la main ; **pas de responsable ni de créneau** ;
  - e-mails `appointmentRequested` et `appointmentUpdated`.
- **Personnes** : il n’existe que des **comptes** (`users`). Un visiteur sans compte n’est qu’une ligne de rendez-vous ou de message.
- **Dossiers** : `service_requests`, créés seulement par le client en ligne. L’équipe ne peut pas en ouvrir.
- **Espace client** `/mon-espace/` : liste des demandes et des rendez-vous, avec l’ancienne présentation (la maquette 1A sert de référence).
- **E-mails** : file `mail_outbox`, tâche `bin/send-mails.php`. En production, le pilote est `log` (pas de SMTP) et **aucune tâche cron Taysir n’est en place**. Attention : la crontab de l’utilisateur `jomas` sert aussi d’autres sites, il ne faut jamais la remplacer, seulement y ajouter des lignes.

## 4. Étapes

Chaque étape se termine par une recette (robot étendu, captures 1440, 768 et 375 px) et un compte rendu, avant l’étape suivante.

### 2.0 Maquette ciblée (validation avant code)

Trois écrans nouveaux et denses, ajoutés à la maquette existante (`/gestion/maquette/`, données fictives) :
- **agenda** de la semaine, par personne et pour toute l’agence (vue secrétaire) ;
- **fiche personne** : identité, rendez-vous, comptes rendus, dossiers, bouton « Ouvrir un dossier » ;
- **choix d’un créneau** côté public.

Tâches à essayer :
1. La secrétaire confirme une demande sur un créneau libre.
2. Un conseiller retrouve son entretien de 10 h et en rédige le compte rendu.
3. Une personne réserve un créneau sur téléphone.

### 2A. Personnes

- **Migration 011** :
  - table `people` : identité, coordonnées, profil, agence, origine (site, visite, prospect, import), date de naissance facultative ;
  - `users.person_id` (au plus un compte par personne) ;
  - `service_requests.person_id` et `appointments.person_id`.
- **Reprise** : une personne par compte client existant, rattachée à ses demandes et à ses rendez-vous. Les rendez-vous sans compte sont rattachés par e-mail exact ; sinon une personne est créée. **Aucune fusion automatique** : les doublons sont traités en phase 4.
- **Périmètre des personnes** (§3.5, « Voir la liste des personnes ») : tout pour l’administrateur, la secrétaire et le comptable. Pour les postes affectables : les personnes dont ils tiennent un dossier ou un rendez-vous. Le filtre est unique, dans `Access`.
- **Écrans** : liste et fiche personne ; création par l’accueil (visite) et par le conseiller.
- **Recherche globale côté serveur** (`/gestion/recherche/?q=`) : personnes et dossiers du périmètre, au lieu de l’index embarqué dans la page de 1B, qui ne tiendrait pas le volume.

### 2B. Agenda

- **Migration 012** :
  - `appointments` : responsable (`assigned_to`), début et fin en **UTC**, agence, statuts complétés (`demande`, `confirme`, `honore`, `absent`, `reporte`, `annule`) ; reprise de `termine` en `honore` ;
  - tables `availability_rules` (semaine type) et `availability_exceptions` ;
  - **contrainte d’unicité** (responsable, début) sur les rendez-vous actifs.
- **Fuseaux** : stockage en UTC, affichage dans le fuseau de l’agence (`Africa/Dakar`) ; pour la visio, l’heure s’affiche aussi dans le fuseau de la personne.
- **Anti-double réservation** : verrouiller le responsable dans la transaction et contrôler les intervalles actifs, y compris s’ils ont des heures de début différentes. Deux réservations concurrentes incompatibles donnent une seule confirmation. Tester aussi deux créneaux contigus et la replanification.
- **Droits** :
  - administrateur et secrétaire : tout l’agenda ;
  - conseiller et agent d’orientation : **leurs** rendez-vous et ceux de leurs personnes ;
  - le module « Rendez-vous » leur est rouvert avec ce périmètre (liste, compteurs, modifications).
- **Public** : choix d’un créneau (D3) ou demande. Appliquer C5. Une saisie anonyme reste dans le périmètre sans compte, y compris si l’adresse appartient à un client. La réservation directe reste désactivée en production tant que les conditions C9 ne sont pas remplies.

### 2C. Compte rendu et ouverture du dossier

- **Migration 014** (013 est prise par les réglages d’agenda ; voir « 2C Plan détaillé ») :
  - `notes` : sujet personne ou dossier, rendez-vous lié facultatif, visibilité **interne** ou **partagée**, choisie explicitement à la saisie (§8) ;
  - `procedure_templates` : destination, démarche, version, étapes, pièces, délais types, sources officielles, date et auteur de la dernière vérification, actif ou non ;
  - `request_steps` (étapes d’un dossier, figées à l’ouverture).
- **Compte rendu d’entretien** : rédigé depuis le rendez-vous, rattaché à la personne. S’il est « partagé », il apparaît dans `/mon-espace/` et un e-mail « compte rendu disponible » part.
- **Modèles de procédure** (administrateur) :
  - liste, création, nouvelle version ;
  - un dossier garde la **version** de son ouverture ; une nouvelle version se **propose** au responsable sans modifier les dossiers en cours ;
  - **alerte de fraîcheur** à 6 mois, paramétrable.
- **Ouvrir un dossier** depuis la fiche personne :
  - choix du service, de la destination, de la rentrée et du modèle ;
  - le dossier naît **affecté au conseiller qui l’ouvre** ;
  - les étapes sont copiées du modèle ;
  - il suit ensuite la chaîne de la 1B (transmission à l’orientation, puis au traitement).

### 2D. E-mails, rappels, « Ma journée », tâches

- **E-mails** par la file existante :
  - confirmation avec date, heure locale, lieu ou lien ;
  - **rappel la veille** ;
  - report ou annulation ;
  - compte rendu disponible.
- **Tâche cron Taysir** (`bin/rappels.php`, toutes les 10 minutes), **ajoutée** à la crontab de `jomas` sans toucher aux autres lignes : rappels, plus relance de la file d’e-mails. Elle n’a d’effet réel qu’une fois le SMTP en place.
- **Tâches** (minimales) : intitulé, échéance, personne ou dossier lié, responsable ; créées depuis une fiche ou un compte rendu ; cochées depuis « Ma journée ».
- **« Ma journée »** par poste : rendez-vous du jour (les siens, ou ceux de l’agence pour la secrétaire), tâches dues, dossiers à reprendre (files de la 1B).

## 5. Recette

**Robot `bin/recette.php`**, étendu ; les 83 contrôles de la 1B restent :

| N° | Scénario | Attendu |
| --- | --- | --- |
| P1 | Réservation publique d’un créneau ouvert | 303 ; rendez-vous `confirme` en base, personne créée ou retrouvée par e-mail, e-mail en file |
| P2 | **Deux réservations simultanées** du même créneau | une seule réussit ; l’autre reçoit les créneaux suivants ; aucun doublon en base |
| P3 | Demande sans créneau, puis confirmation par la secrétaire | statut `confirme`, horaire stocké en UTC, affiché à l’heure de Dakar |
| P4 | Conseiller A sur le rendez-vous ou la fiche personne du conseiller B | 403, journalisé ; absent de ses listes, compteurs et recherche |
| P5 | Compte rendu **interne** | jamais visible du client, ni d’un poste non affecté |
| P6 | Compte rendu **partagé** | visible dans `/mon-espace/`, e-mail en file |
| P7 | Ouverture d’un dossier depuis la fiche | dossier affecté à l’ouvreur, étapes copiées du modèle et version enregistrée ; une nouvelle version du modèle ne change pas ce dossier |
| P8 | Double soumission de l’ouverture | aucun doublon (jeton à usage unique) |
| P9 | Rappel de la veille | exactement un e-mail par rendez-vous confirmé du lendemain, jamais deux |
| P10 | Fuseaux | un rendez-vous de 10 h à Dakar s’affiche à 10 h ; en visio, il s’affiche aussi dans le fuseau de la personne |
| P11 | Reprise 011 | une personne par compte client ; rendez-vous et demandes rattachés ; aucune fusion |

**Également** :
- répétition des migrations 011 à 013 (aller, retour arrière, reprise après interruption) en local puis **en préproduction MariaDB** sur une copie de la production, comme en 1B ;
- captures sur ordinateur, tablette et téléphone ;
- scénario de bout en bout, partie A (§5 du plan : étapes 1 à 3), joué dans le navigateur avec des comptes fictifs.

## 6. Hors phase 2

- Pièces attendues côté client, dépôt, rappels d’échéances, résultats d’admission, accès parent : phase 3. C’est là que `/mon-espace/` prend le design de la maquette.
- Doublons et fusion : phase 4. Finance : phase 5. Prospection et commerciale : phase 6.
- Périmètre entre agences : avant l’activation d’une deuxième agence (suivi de la revue 1B).

## 7. Ordre de grandeur

5 étapes (2.0 à 2D), chacune livrée, testée et validée séparément. Les étapes 2A et 2B sont les plus lourdes (données et agenda) ; 2C et 2D s’appuient dessus.

---

## 8. Décisions validées (C1)

Validation du 27/11/2026 : options retenues et hypothèses.

**D1 – Un seul type de dossier.** `service_requests` devient la table des dossiers ; pas de table `cases`.
- `user_id` devient facultatif (personne sans compte).
- `person_id` est obligatoire pour tout **nouveau** dossier.

**D2 – Créneaux.** Chaque conseiller et agent d’orientation tient sa semaine type et ses exceptions ; la secrétaire et l’administrateur gèrent pour tous.
- Un membre ne modifie que **ses** disponibilités.
- Un changement de disponibilité **n’annule jamais seul** un rendez-vous confirmé : les rendez-vous touchés (par une exception ou par un membre désactivé) sont **signalés** à la secrétaire.
- L’agenda de la commerciale est reporté en phase 6 ; son module reste fermé, et c’est testé.

**D3 – Réservation en ligne.** Choix direct d’un créneau, derrière un **interrupteur désactivé par défaut en production**. Il vaut pour les motifs paramétrés (par défaut : orientation-filières, orientation-métiers, renseignement). La demande à confirmer reste toujours possible.
- Le public voit des heures, jamais un nom ; le responsable est attribué automatiquement parmi les disponibles.
- Seules les agences actives proposent des créneaux.
- Délai minimal de 24 h et horizon de 6 mois, paramétrables.
- Un client connecté peut annuler le sien ; déplacer revient à annuler puis réserver.
- Pour une personne sans compte, l’annulation passe par un lien à jeton envoyé par e-mail, une fois le SMTP en place.

**D4 – Durées et horaires.**
- Une durée pour chacun des 8 motifs de `Appointment::MOTIVES`, en base et modifiable par l’administrateur.
- Horaires provisoires de Dakar, **à faire confirmer par l’agence** : du lundi au vendredi 9 h–13 h et 14 h–18 h ; le samedi 9 h–14 h ; fermé le dimanche.

**D5 – Modèles de procédure.** France (Campus France), Canada (permis d’études) et Belgique, en **brouillon**.
- Un brouillon n’est pas proposé pour un dossier réel en production, n’est jamais montré au client et n’est jamais indexé par l’assistant.
- On peut ouvrir un dossier **sans modèle**.
- Question pour l’agence : le CAQ pour le Canada.

**Découpage.**
- Les migrations de la phase 2 sont **additives** : chaque étape peut partir en production seule.
- Rien de la phase 2 ne part en production avant la validation de la 1B en conditions réelles.

## 9. Exigences ajoutées par la validation (avant le code de 2A)

### C5. Personnes et visibilité

- **Visibilité côté client**
  - Le client voit ce qui porte le `person_id` de son compte, selon une **seule règle** pour les listes et les compteurs (`forUser` et les compteurs ne filtrent plus sur `user_id`).
  - Les événements écrits par l’équipe sont **internes par défaut**. Aujourd’hui, `request_events.visible` vaut 1 par défaut : c’est à corriger.
- **Rattachement par e-mail**
  - Une réservation anonyme, ou un rendez-vous sans compte repris en 011, n’est **jamais** rattaché automatiquement à une personne qui a un compte. Sinon, quelqu’un qui saisirait l’e-mail d’autrui serait reçu comme cette personne.
  - Vers une personne sans compte, il faut une correspondance **unique** de l’e-mail et du nom normalisés. Sinon, on crée une nouvelle personne marquée « à rapprocher ».
  - La réponse publique est la même dans tous les cas : elle ne révèle jamais si l’e-mail existe.
- **Périmètre de l’équipe**
  - Un poste affectable voit aussi les personnes qu’il a créées (`created_by`).
  - Un dossier seulement « dans la file » montre son en-tête, pas la fiche personne.
  - La vue transversale (secrétaire, comptable) n’inclut **jamais** les notes internes.
- Le fuseau de la personne est une colonne de `people`.

### C6. Agenda, fuseaux et concurrence

- **Dates**
  - Elles sont stockées en **UTC** et converties en PHP (`DateTimeZone`).
  - Jamais de `CONVERT_TZ` avec des noms de fuseaux : sous MariaDB, il renvoie NULL quand les tables de fuseaux ne sont pas chargées.
  - Jamais de `NOW()` dans une comparaison métier.
- **Chevauchements**, trois protections :
  - une transaction avec `SELECT … FOR UPDATE` sur le responsable ;
  - un contrôle de chevauchement entre début et fin ;
  - en dernier filet, une colonne générée `STORED` avec un index unique (MariaDB n’a pas d’index partiel).
- **Contrôle serveur** : tout créneau soumis est recalculé par le serveur. Un créneau falsifié, passé, situé dans une exception ou dans une agence inactive est refusé.
- **Statuts**
  - Actifs : `confirme`, et `demande` quand il a un responsable et un début.
  - `reporte` libère le créneau et garde le lien avec le nouveau rendez-vous.
- **Rappels**
  - La veille est calculée dans le fuseau de l’agence.
  - Ils sont idempotents : `reminded_at` est posé de façon atomique.
  - Aucun rappel pour un rendez-vous annulé, reporté, ou confirmé moins de 24 h avant.
  - La tâche cron est protégée par `flock`.
- **Anti-abus** : limites par adresse IP et par e-mail, et un nombre maximal de rendez-vous actifs par e-mail. La limite actuelle, par session, se contourne trop facilement.

### C7. Migrations 011 à 013 (MySQL 8.4 et MariaDB 10.11)

- Clés étrangères en `RESTRICT`, sans cascade.
- Reprises relançables (`INSERT … WHERE NOT EXISTS`) ; chaque `UPDATE` garde `updated_at = updated_at`.
- Passage de `termine` à `honore` en trois temps : élargir l’ENUM, convertir, puis resserrer.
- Retour arrière avec une correspondance explicite (`honore` et `absent` → `termine`, `reporte` → `annule`), testé avec une ligne dans chaque nouveau statut. Les scripts `.down.sql` sont suivis par git.
- **Préproduction MariaDB avec un jeu synthétique**, puisque la production ne contient encore ni compte ni demande :
  - des comptes clients avec des demandes et des rendez-vous ;
  - des rendez-vous sans compte, dont deux avec le même e-mail ;
  - une réservation anonyme avec l’e-mail d’un compte ;
  - tous les anciens statuts.
- Chaque répétition joue l’aller, le retour arrière à l’identique et la reprise après interruption, avec un compte SQL temporaire limité.
- Aucune fonction propre à un seul moteur.

### C9. Mise en production

- La 1B est d’abord validée en conditions réelles.
- Chaque fin d’étape exige un rapport du `verificateur` `CONFORME` ou `CONFORME AVEC RÉSERVES`, puis un GO du `validateur`.
- **Pas de tâche cron en production tant que le pilote d’e-mail reste `log`.** Ensuite : sauvegarder la crontab de `jomas`, ajouter **une** ligne, puis vérifier qu’aucune autre n’a changé.
- La réservation directe reste désactivée tant que le SMTP et des disponibilités réelles ne sont pas en place.

## 10. Recette complétée (C8)

Les 83 contrôles de la 1B et P1 à P11 (§5) restent. **P10 est remplacé** par P10 bis : Africa/Dakar étant en UTC+0 toute l’année, une erreur de conversion y passerait inaperçue.

| N° | Scénario | Attendu |
| --- | --- | --- |
| P1 bis | Double soumission publique | un seul rendez-vous |
| P2 bis | Deux réservations réellement parallèles, et deux chevauchements avec des débuts différents | une seule réussit |
| P4 bis | Deux comptes du même poste (conseiller, orientation) | chacun voit les siens ; commerciale, prospection, traitement et client : 403 |
| P5 bis | Note interne | invisible de la secrétaire et du comptable, absente de la recherche et des e-mails |
| P9 bis | Deux exécutions simultanées du cron ; un rendez-vous reporté | un seul rappel, à la nouvelle date |
| P10 bis | Visio en Europe/Paris de part et d’autre du changement d’heure (26 et 30 mars 2027), agence fictive à Paris | heures justes dans les deux fuseaux |
| P12 | Créneau falsifié ; CSRF absent sur un nouveau formulaire | refus ; 419 |
| P13 | Réservation anonyme avec l’e-mail d’un compte | n’apparaît pas dans l’espace de ce compte |
| P14 | Le client annule son rendez-vous, puis tente celui d’un autre | 303, puis 403 |
| P15 | Membre désactivé ou exception sur un rendez-vous confirmé | signalé à la secrétaire, jamais supprimé |
| P16 | Tâches | même périmètre que les dossiers |
| P17 | Recherche | respecte `Access`, échappe ses caractères, limite ses résultats |
| P18 | Demandes existantes | non-régression |
| P19 | Index de l’assistant | rien d’interne |
| P20 | Modèle en brouillon | non proposé ; ouverture d’un dossier sans modèle possible |

## 11. Réservé à l’utilisateur (rappel)

- SMTP et premier administrateur en production.
- GAOV.
- Le push de chaque étape (qui vaut mise en production).
- L’activation de la réservation directe.
- L’ajout de la ligne cron dans la crontab de `jomas`.
- **À faire vérifier par l’agence** : horaires de Dakar (et de Ziguinchor avant son activation), durées des motifs, contenu des 3 modèles et question du CAQ, textes des e-mails, conservation et cadre légal.

---

## 2.0 Résultat : maquette ciblée (2026-11-27)

Données fictives, dans la maquette existante (`/gestion/maquette/`, active seulement avec `GESTION_MAQUETTE=1`).

### 1. Livré

| Écran | Adresse | Contenu |
| --- | --- | --- |
| Agenda | `/gestion/maquette/agenda/` | **Secrétaire et administratrice** : vue « jour » de toute l’agence (une colonne par membre, donc aucun chevauchement visuel), vue « semaine » d’un membre, demandes à placer avec les créneaux libres et le cas « créneau déjà pris, voici les suivants ». **Conseiller et orientation** : leur semaine, avec leurs absences. Pour une visio, l’heure de Dakar et celle de la personne (Paris, Toronto). **Autres postes** : refus en 403 ; l’agenda de la commerciale est annoncé pour la phase 6. |
| Fiche personne | `/gestion/maquette/personnes/` | Identité, coordonnées, lien familial à vérifier ; comptes rendus et notes avec un choix **interne ou partagé obligatoire, sans valeur par défaut** ; rendez-vous ; dossiers avec progression ; tâches ; « Ouvrir un dossier » : modèle publié, **brouillons signalés et non sélectionnables**, ou sans modèle. **Secrétaire et comptable** : notes internes filtrées côté serveur (leur texte est absent du HTML) ; prospection et commerciale : refus en 403. |
| Réservation publique | `/gestion/maquette/rendez-vous/` | Objet (durée affichée), mode, jour et heure (aucun nom de membre), « déjà pris, voici les heures encore libres », « aucun horaire ne convient → demande », double fuseau en visio, coordonnées, confirmation active seulement après le choix d’une heure. |

**Fichiers** :
- vues : `app/Views/gestion/maquette/agenda.php`, `personne.php`, `reservation.php` ;
- données : `donnees-phase2.php` ;
- contrôleur `MaquetteController` (3 actions) et 3 routes ;
- navigation de la maquette ;
- styles : fin de `gestion.css` et de `gestion-client.css` ;
- icône (favicon) ajoutée au gabarit public `layouts/main.php`.

### 2. Tests

- **Agent `verificateur`** : rapport [verifications/2026-11-27-maquette-2-0.md](verifications/2026-11-27-maquette-2-0.md), verdict **CONFORME AVEC RÉSERVES**.
  - Les 6 éléments de la condition C2 sont présents et fonctionnent.
  - Charte C3 : 0 bordure colorée, 0 dégradé, texte noir, Prata et Poppins, rouge d’action.
  - Aucun débordement ni erreur PHP sur 11 pages × 3 largeurs.
  - Robot 83/83 (non-régression).
- **Agent `validateur`** : décision [validations/2026-11-27-fin-etape-2-0.md](validations/2026-11-27-fin-etape-2-0.md), **GO SOUS CONDITIONS** : étape close.
  - K1 (commit de la maquette seule), K2 (plans à jour) et K3 (3 captures refaites) : remplies avant 2A.
  - K4 à K7 : contrôlées à la fin de 2A.
- **Mes essais dans le navigateur** :
  - placement d’une demande : 4 créneaux, puis 3 après « déjà pris », confirmation, la demande quitte la liste ;
  - compte rendu : erreur sans choix, accepté avec choix ;
  - réservation à 375 px : visio « 09:00 / 10:00 Paris », « déjà pris », bouton actif après le choix ;
  - aucun défilement horizontal.
- **Tâches de la maquette** (§4, étape 2.0) :
  1. la secrétaire confirme une demande sur un créneau libre : **jouable** ;
  2. le conseiller retrouve son entretien de 10 h et en rédige le compte rendu : **jouable** (rendez-vous du jour à 10:00, lien « Compte rendu », formulaire titré « entretien d’aujourd’hui à 10:00 ») ;
  3. une personne réserve un créneau sur téléphone : **jouable**.
- **Captures** (`docs/captures/gestion-2-0/`, 11 fichiers) :
  - agenda : agence à 1440 et 768 px, conseiller à 375 px ;
  - fiche : conseiller à 1440, 768 et 375 px, secrétaire à 1440 px ;
  - réservation : 1440, 768 et 375 px, et le cas « déjà pris ».
  - condition K3 du validateur : `375-agenda-conseiller`, `375-fiche-personne` et `375-reservation-deja-pris` refaites après les corrections (rendez-vous de 10 h, « entretien d’aujourd’hui », nouveau bandeau de fuseau).

### 3. Corrections

| Défaut | Correction |
| --- | --- |
| Vue « toute l’agence » : rendez-vous de deux membres superposés, blocs trop serrés | Vue « jour » avec une colonne par membre, vue « semaine » par membre, lignes plus hautes, blocs courts sur une ligne |
| Deux motifs en dégradé (lignes d’heure, absences rayées), interdits par la charte | Lignes en éléments simples, absences en surface neutre |
| Bandeau de fuseau mal mis en page sur téléphone | Texte regroupé |
| Absences invisibles dans la liste du téléphone | Absences affichées dans la liste |
| Tablette : liste au lieu de la grille ; heures de réservation trop larges | Grille dès 640 px, sélecteur de jour masqué en liste, heures à largeur fixe |
| **Rapport du vérificateur** (7 réserves mineures) : agenda dans le menu de la commerciale ; refus en 200 ; le traitement ne pouvait pas écrire de note ; créneaux passés proposés ; tâche 2 non jouable ; captures incomplètes ; icône du site absente | Menu corrigé ; refus en **403** ; notes ouvertes au traitement (ouvrir un dossier reste au conseiller et à l’administrateur) ; créneaux calculés à partir de demain, sans dimanche ; rendez-vous de 10 h ajouté ; captures complétées ; favicon ajouté |

**Reporté au code réel** (noté pour 2A à 2C) :
- règle « affecté » sur les fiches personnes (C5) ;
- refus journalisé (`acces_refuse`) ;
- 404 vérifiée sans `GESTION_MAQUETTE` par le robot.

---

## 2A Plan détaillé : personnes (2026-11-27)

Point de départ : commit `d4f87de` (maquette 2.0 close, décision [fin-etape-2-0](validations/2026-11-27-fin-etape-2-0.md)). Conditions à tenir : C5, C7, K4 à K7.

### 1. Migration 011 `011_personnes.sql` (+ `rollback/011_personnes.down.sql`)

- **Table `people`** :
  - identité et coordonnées : `first_name`, `last_name`, `email` (facultatif), `phone` (facultatif), `profile` (mêmes valeurs que `users.profile`), `birth_date` (facultative) ;
  - `timezone` (défaut `Africa/Dakar`, C5), `agency_id` (clé étrangère `RESTRICT`) ;
  - `origin` : `site`, `visite`, `prospect`, `import`, `reprise` ;
  - `to_reconcile` (0/1) : « à rapprocher » (C5) ;
  - `import_ref` (unique, facultatif) : `user:<id>` ou `rdv:<id>`, ce qui rend la reprise relançable et servira à l’import GAOV (phase 4) ;
  - `created_by` (clé étrangère `RESTRICT` vers `users`), `created_at`, `updated_at` ;
  - index sur `email` et sur `(last_name, first_name)`.
- **Colonnes** :
  - `users.person_id` : unique (au plus un compte par personne), `RESTRICT` ;
  - `service_requests.person_id` : `RESTRICT` ; `service_requests.user_id` devient facultatif (D1) ;
  - `appointments.person_id` : `RESTRICT`.
- **C5** : `request_events.visible` passe à 0 par défaut (les événements de l’équipe sont internes par défaut ; le code client continue d’écrire `visible = 1` explicitement là où il le faut).
- **Reprise relançable** (`INSERT … SELECT … WHERE NOT EXISTS`, `UPDATE … SET updated_at = updated_at`) :
  1. une personne par compte client sans `person_id` (`import_ref = user:<id>`, origine `reprise`), puis `users.person_id` ;
  2. demandes et rendez-vous d’un compte : `person_id` du compte ;
  3. rendez-vous **sans compte** : rattachés à une personne **sans compte** si l’e-mail et le nom normalisés (`LOWER(TRIM())`) correspondent à **une seule** personne ; sinon une nouvelle personne `to_reconcile = 1`. Jamais vers une personne qui a un compte (C5) ;
  4. aucune fusion.
- Portable MySQL 8.4 et MariaDB 10.11 : pas de fonction propre à un moteur, pas de `CHECK` ni de colonne générée.

### 2. Code

- **Modèle `Person`** : création (accueil, conseiller, orientation, administrateur), lecture, liste paginée, recherche.
- **`Access`** (filtre unique, K4) :
  - `personnesSql()` : tout pour administrateur, secrétaire et comptable ; pour les postes affectables, les personnes d’un dossier dont ils sont responsables, d’un rendez-vous qui leur est attribué, ou qu’ils ont créées (`created_by`) ;
  - dossier seulement « dans la file » : en-tête visible, fiche personne refusée ;
  - prospection et commerciale : aucun accès en phase 2 (403).
- **Refus** : 403 journalisé `acces_refuse` dans `audit_log` (K4).
- **Côté client** : `forUser` et les compteurs filtrent sur `person_id` du compte, avec `user_id` en secours tant que la reprise n’est pas passée.
- **Création des nouvelles demandes et rendez-vous** : `person_id` renseigné (compte → sa personne ; anonyme → règle 3 ci-dessus, réponse publique identique dans tous les cas).
- **Écrans** (charte de la maquette) :
  - `/gestion/personnes/` : liste du périmètre, recherche, pagination ;
  - `/gestion/personnes/{id}/` : fiche (identité, coordonnées, rendez-vous, dossiers ; notes et compte rendu en 2C) ;
  - `/gestion/personnes/nouvelle/` : création (origine `visite` pour l’accueil) ;
  - menu « Personnes » selon la matrice §3.5.
- **Recherche globale** `/gestion/recherche/?q=` : personnes et dossiers du périmètre, requêtes préparées, `LIKE` échappé, 20 résultats au plus ; remplace l’index embarqué de 1B.

### 3. Tests (K5, K7)

- **Robot** : les 83 contrôles de 1B, plus :
  - P4 bis sur les fiches personnes (deux conseillers, deux orientations ; prospection, commerciale, traitement non affecté, client : 403 journalisé) ;
  - P11 (reprise), P13 (réservation anonyme avec l’e-mail d’un compte), P17 (recherche : périmètre, `%` et `_` échappés, limite), P18 (demandes existantes) ;
  - `/gestion/maquette/` en 404 sans `GESTION_MAQUETTE`.
- **Répétition des migrations** : 011 sur MySQL 8.4 local, puis MariaDB 10.11 sur la copie de production **et** sur le jeu synthétique C7 ; aller, retour arrière à l’identique, reprise après interruption ; compte SQL limité.
- **Captures** 1440, 768, 375 px ; puis `verificateur`, puis `validateur`.

---

## Charte des boutons (demande de l’utilisateur, 2026-11-27)

Le rouge n’est plus la couleur des actions courantes. Il est réservé au danger.

| Couleur | Usage | Classes |
| --- | --- | --- |
| Bleu | action courante : ouvrir, noter, affecter, placer, envoyer | `g-btn-primary`, `c-btn` |
| Vert | valider, enregistrer, confirmer, réactiver | `g-btn-success`, `c-btn-success` |
| Orange | avertir : désactiver, suspendre | `g-btn-warning`, `c-btn-warning` |
| Rouge | danger : annuler, supprimer, refuser | `g-btn-danger`, `c-btn-danger` |

- Les boutons secondaires restent blancs (`g-btn`, `c-btn-light`).
- Les marqueurs non urgents passent au bleu : onglet actif, étape en cours, jour d’aujourd’hui, prochaine action du client.
- Le rouge reste aux alertes : point de la cloche, erreur de champ, pièce refusée.
- Jetons `--btn-*` dans `app.css`. Le site public n’est pas concerné.
- Appliqué aux écrans réels (dossier, équipe, messages, rendez-vous) et à toute la maquette. Les écrans de 2A suivent cette charte.

---

## 2A Résultat : personnes (2026-11-27)

### 1. Livré

| Élément | Contenu |
| --- | --- |
| Migration 011 `011_personnes.sql` | Table `people` (identité, coordonnées, profil, fuseau, agence, origine, « à rapprocher », `import_ref`, `created_by`) ; `users.person_id` unique ; `service_requests.person_id` et `user_id` facultatif (D1) ; `appointments.person_id` ; `request_events.visible` à 0 par défaut (C5). Six clés étrangères en `RESTRICT`. |
| Reprise (dans 011) | Une fiche par compte client ; demandes et rendez-vous du compte rattachés ; rendez-vous sans compte : une fiche par e-mail et nom normalisés, jamais vers une fiche qui a un compte, « à rapprocher » si l’e-mail est déjà connu ou partagé. Relançable, `updated_at` conservés, aucune fusion. |
| Retour arrière `rollback/011_personnes.down.sql` | Garde-fou portable : s’arrête avant tout changement s’il existe un dossier sans compte (sinon MySQL aurait remplacé les NULL par 0). |
| `Person` (modèle) | Création, fiche d’un compte (`pourCompte`), fiche d’une réservation anonyme (`pourAnonyme`, règle C5), liste et recherche du périmètre, `%` et `_` échappés. |
| `Access` | Module `personnes` ; `personnesSql()` : tout pour administrateur, secrétaire, comptable ; pour conseiller, orientation, traitement : les personnes des dossiers qu’ils **tiennent** et celles qu’ils ont créées (un dossier seulement dans la file ne donne pas la fiche) ; création par administrateur, secrétaire, conseiller, orientation. Menu « Personnes ». |
| Écrans | `/gestion/personnes/` (liste, recherche, pagination), `/gestion/personnes/{id}/` (fiche : identité, coordonnées, dossiers du périmètre, rendez-vous), `/gestion/personnes/nouvelle/` (création). Refus : 403 journalisé `acces_refuse`. |
| Recherche globale | `/gestion/recherche/?q=` (page et JSON) : personnes et dossiers du périmètre, 20 résultats au plus. La palette Ctrl+K interroge le serveur ; l’index embarqué dans chaque page est supprimé. |
| Espace client (C5) | Listes, compteurs et fiche lus par la personne du compte (`CLIENT_SCOPE`), `user_id` en secours ; nouvelles demandes et réservations rattachées à une fiche ; fiche créée à l’inscription. |

### 2. Tests

- **Robot `bin/recette.php` : 116 sur 116** (les 83 de la 1B, plus 33) :
  - P4 bis : deux agents d’orientation, chacun ses personnes ; 403 journalisé ; dossier dans la file : fiche refusée ; traitement non affecté, prospection, commerciale, client : 403 ; vue transversale complète ; menu ;
  - création : secrétaire (visite), conseiller (e-mail connu → « à rapprocher ») ; comptable et traitement refusés ; P12 sans jeton : 419 ;
  - P17 : périmètre, `%` et `_` sans effet joker, 20 résultats au plus, plus d’index embarqué, dossier retrouvé par sa référence ;
  - P18 : le client voit ses demandes, pas celles des autres ;
  - P13 : réservation anonyme avec l’e-mail d’un compte : autre fiche, « à rapprocher », absente de l’espace du compte ;
  - K4 : `/gestion/maquette/` en 404 sans `GESTION_MAQUETTE` ;
  - affichage sans erreur des nouveaux écrans pour chaque poste concerné.
- **Répétition `bin/repetition-011.php` : 36 sur 36** (MySQL 8.4 local), jeu synthétique C7 : aller (P11), relance de la reprise sans doublon, garde-fou, retour arrière à l’identique, interruption après l’instruction 3 puis `--reprendre`.
- **Répétition 1B `bin/repetition-migrations.php` : 22 sur 22** (désormais limitée à 010).
- **Captures** `docs/captures/gestion-2a/` : liste (1440, 768, 375), fiche (1440, 768, 375), création et recherche (1440).

### 3. Corrections

| Défaut | Correction |
| --- | --- |
| Garde-fou du retour arrière par `INSERT … SELECT NULL` : MySQL a converti NULL au lieu d’échouer, puis `MODIFY … NOT NULL` a mis 0 | Sous-requête à deux lignes (erreur 1242), vérifiée sur une base d’essai |
| Deux fiches anonymes au même e-mail, créées dans la même instruction, non marquées « à rapprocher » | `UPDATE` après insertion, par table dérivée (portable) |
| Contrôle T7 de la 1B lisait l’index embarqué | Contrôle par la recherche serveur |
| Formulaire de création en 500 (variable `$errors`) ; le robot ne faisait que poster | Corrigé ; le robot affiche désormais chaque nouvel écran |
| Initiales invisibles dans la liste ; libellés longs qui décalaient la grille du formulaire | Règle d’avatar ; formulaire sur deux colonnes |
| Outil 1B : appliquait aussi 011 ; variable `$options` écrasée avec `--depuis` | `--jusqua=010` ; variable renommée |

**Préproduction MariaDB 10.11.15 (VPS, 27/11)**, dans `/root/taysir-preprod-20261127-2a` (code du commit `2f833c6` par `git archive`, compte SQL temporaire limité à `taysir_%_recette`, supprimé ensuite) :
- `repetition-011.php --depuis=<copie de jomas_taysir>` : **36 sur 36**, après correction de l’outil (le nombre de fiches attendu était écrit en dur ; la copie contient un rendez-vous réel sans compte, d’où 8 fiches et non 7 ; commit `f3dff26`) ;
- `repetition-011.php`, jeu synthétique seul : **36 sur 36** ;
- `recette.php` : **116 sur 116** ;
- copie de la base supprimée, aucune base `_recette` ni compte temporaire restant ; production inchangée (010).

**Reste pour clore 2A** : `verificateur`, puis `validateur`.

### Reprise de la 2A et décisions d’étape

Le vérificateur contrôle le code et la recette. Le validateur décide du passage à l’étape suivante et consigne son avis dans `docs/validations/`. L’agent de déploiement prépare la révision isolée et intervient pour les publications autorisées. Ces trois missions sont réutilisables par les agents qui reprennent le projet.

- Un jeton par formulaire de création empêche les doublons au renvoi dans la même session. Une nouvelle ouverture permet de créer une autre fiche volontairement.
- Le compteur de dossiers réutilise le périmètre de la fiche. Les rendez-vous de la fiche et leur compteur suivent les droits du module Agenda. La visibilité d’une personne via un rendez-vous attribué relève de la 2B, avec un contrôle entre deux agents du même poste.
- Le rattachement d’un compte sans fiche verrouille le compte dans la transaction de création. Le retour arrière de 011 bloque aussi en présence d’une fiche créée après reprise.
- Les notes de dossier sont internes par défaut. Le partage est une action explicite.
- Recette locale du lot corrigé sur MySQL 8.4 : **118/118**. Répétition de la migration 011 et des garde-fous : **37/37**. Les résultats MariaDB plus haut portent sur le lot précédent.

### 2B — Intégration locale en cours

La migration 012 ajoute les responsables, les intervalles UTC et les disponibilités. L’agenda de l’accueil affiche la journée par responsable et l’agent voit sa semaine. Les listes, compteurs et fiches personnes réutilisent le périmètre Agenda. La visio affiche deux fuseaux. Les membres peuvent déclarer leurs plages et absences. Une absence ou un compte désactivé signale les confirmations à replanifier.

La recette générale a réussi 118 contrôles sur MySQL 8.4 avant le dernier signalement de replanification. Le robot de concurrence a réussi ses quatre scénarios sur des transactions distinctes. Le vérificateur a contrôlé l’aller-retour de la migration sur MySQL. Le validateur maintient la 2B ouverte.

La suite est de brancher les plages sur le calcul des créneaux publics, de terminer le report et l’annulation client, puis de vérifier les parcours complets et la migration corrigée sur MariaDB. Aucune mise en ligne. La demande publique existante reste disponible.

### 2B — Créneaux publics et annulation client

Le formulaire peut proposer les heures calculées d’après les plages, absences et rendez-vous actifs. Le serveur recalcule sous verrou avant d’attribuer le rendez-vous. Les trois motifs directs proposés sont les deux motifs d’orientation et le renseignement, avec un délai minimum de 24 h. Un compte connecté peut annuler un rendez-vous à venir dans son périmètre. `AGENDA_RESERVATION_DIRECTE=0` reste le réglage de référence.

La recette générale a réussi 118 contrôles pendant l’intégration. La recette ciblée finale a réussi cinq scénarios sous MySQL, dont la disponibilité publique, une absence et le refus d’un créneau devenu indisponible. Le vérificateur a relevé des écarts dans le rejeu et la conservation du créneau au retour du formulaire. Ces deux points sont corrigés dans le code et leur recette HTTP reste à compléter.

Le validateur maintient la 2B ouverte. Le report lié, les réglages administrables des durées, le parcours d’annulation sans compte et la recette MariaDB restent en attente de vérification ou d’implémentation. La production n’a pas été modifiée.

### 2B — Report et contrôle de l’annulation

Le report d’un rendez-vous confirmé conserve son heure d’origine, crée son remplacement et les relie dans la même transaction. Un conflit laisse l’original intact. Le changement d’heure d’une confirmation complète passe également par ce mécanisme. La nouvelle heure doit être future et l’agence active.

L’annulation par le client inscrit un événement dans l’historique. Une tentative hors périmètre reçoit un 403 journalisé. La recette agenda couvre six scénarios sur MySQL, dont le report lié et le conflit. MariaDB 11.4 est disponible localement pour la répétition. La compatibilité avec MariaDB 10.11 du VPS reste à établir avant la publication.

Le validateur donne son GO local pour le report lié. La recette HTTP finale réussit **119/119**, dont P14 avec annulation d’un rendez-vous confirmé, créneau de nouveau proposé par l’API et refus 403 journalisé pour une tentative hors périmètre. La 2B reste ouverte pour les durées administrables, l’annulation sans compte et la qualification MariaDB. La réservation directe reste désactivée par défaut.

### 2B — Durées et annulation sans compte

La migration 013 ajoute les durées par motif et les empreintes des liens d’annulation. L’administrateur règle les durées depuis l’agenda. Elles s’appliquent au calcul public et aux confirmations manuelles sans heure de fin. Le lien reçu par e-mail ouvre un formulaire. Seule sa confirmation annule le rendez-vous et consomme le jeton. Chaque onglet possède son propre formulaire.

La recette HTTP finale sur MySQL passe **120/120**, dont deux liens ouverts en parallèle dans la même session. L’ouverture des liens laisse les rendez-vous intacts et confirmer le formulaire A annule uniquement A. Les huit scénarios ciblés passent, dont le rejet du rejeu du lien et l’effet d’une durée modifiée sur les créneaux. Le vérificateur ne relève aucun défaut critique dans les corrections. La qualification MariaDB reste nécessaire avant la clôture. Aucun changement sur le VPS.

---

## 2B : conditions B1 à B5 de la décision de fin d’étape (2026-11-27)

Décision : [validations/2026-11-27-fin-etape-2b.md](validations/2026-11-27-fin-etape-2b.md). B6 (préalables de mise en ligne) reste à faire au moment de la publication.

### 1. Livré

| Condition | Correction |
| --- | --- |
| **B1** anti-abus (C6) | `App\Services\BookingLimiter`, côté serveur, à la place du compteur de session : 5 envois par IP et par heure, 15 par jour ; 3 envois par e-mail et par heure ; 3 rendez-vous actifs au plus par e-mail. IP et e-mail stockés seulement en empreinte salée par le jour (`storage/cache/rendez-vous/`). Même message pour toutes les limites, qui ne révèle pas si l’adresse est connue (C5). Seuils relevables par l’environnement, pour le robot. |
| **B2** fuseau de l’agence | `Appointment::zone()` lit `agencies.timezone`. Les plages hebdomadaires sont lues en heure locale de l’agence et converties en UTC, changement d’heure compris. Le fuseau de l’agence s’applique aussi à : la journée et la semaine de l’agenda, la confirmation manuelle et ses champs, les absences, les e-mails, la confirmation publique, l’espace client et la fiche personne. Les libellés « heure de … » viennent de l’agence. Aucun changement à Dakar (UTC+0). |
| **B3** scénarios manquants | Robot HTTP : P1 bis (double soumission réellement simultanée → un seul rendez-vous) ; P12 (créneau falsifié ou hors plage → rien d’enregistré) ; P3 (demande confirmée par l’accueil, heure de Dakar stockée en UTC, durée appliquée, affichée à 15:00) ; deux agents d’orientation face à leurs rendez-vous attribués (listes, 403 journalisé sur celui du collègue, fiches personnes) ; P15 (absence et compte désactivé → « à replanifier », rien supprimé). Robot agenda : P10 bis (10:00 locales à Paris = 09:00 UTC le 26/03/2027 et 08:00 UTC le 30/03/2027), et les limites B1. |
| **B4** captures | `docs/captures/gestion-2b/` : agenda de l’accueil à 1440, 768 et 375 px ; formulaire public avec créneaux à 375 px. Titre du jour réduit et date alignée à l’occasion. |
| **B5** répétition rejouable | `bin/repetition-012-013.php`, repris du script de qualification de Codex. Options : `--port` (instance de recette séparée), `--sql-user`/`--sql-password`, `--exiger=10.11` (refus si ce n’est pas la bonne version). `TAYSIR_DB_PORT` est reconnu en mode recette (`config/recette.php`, `Database`, `migrate.php`, robots). |

### 2. Tests

| Outil | MySQL 8.4 | MariaDB 10.11.15 isolée |
| --- | --- | --- |
| `bin/recette.php` | **127/127** | **127/127** |
| `bin/recette-agenda.php` | **10/10** | **10/10** |
| `bin/repetition-012-013.php` | **13/13** | **13/13** |
| `bin/repetition-011.php` | **37/37** | — |
| `bin/repetition-migrations.php` | **22/22** | — |

### 3. Corrections en cours de route

| Défaut | Correction |
| --- | --- |
| Confirmation d’un rendez-vous par l’accueil : 302 après succès (règle du projet : 303) | `redirect_to()` vers le jour consulté, sans adresse externe |
| Outils de recette : le compte SQL n’était transmis aux processus enfants que s’il différait de celui de la configuration | transmis dès qu’il est donné en option |
| Titre de l’agenda démesuré ; date mal alignée sur téléphone | taille et alignement |

### 4. Reprise après le rapport du vérificateur ([conditions-2b](verifications/2026-11-27-conditions-2b.md), NON CONFORME)

| Anomalie | Correction |
| --- | --- |
| **A4** (B4) : 4 captures sur 18 | **18 captures** : agenda de l’accueil, semaine d’un agent, disponibilités et absences (panneau ouvert), durées, réservation avec créneaux, annulation sans compte, chacun à 1440, 768 et 375 px |
| **A1** : seuils réglables en production par le `.env` | seuils réglables **seulement en recette** (`config/recette.php`), valeurs fixes sinon |
| **A2** : envois simultanés entre la vérification et le comptage | `BookingLimiter::proteger()` : vérification, création et comptage sous **verrou exclusif** ; robot : 6 envois simultanés, 6 navigateurs sans cookie commun, même e-mail → **3 acceptés, 3 en base** |
| **A9** : empreinte sans clé | **HMAC-SHA256** avec une clé secrète propre au serveur (`.cle`, 0600, jamais versionnée) |
| **A10** : alias, remise à zéro à minuit, IPv6 | alias « +… » ramenés à l’adresse, **24 heures glissantes**, IPv6 regroupée par /64 ; testés dans `recette-agenda.php` |
| **A5** (B3) | créneau dans le passé (P12), rafale simultanée (B1), plafond de 15 sur 24 h, alias et IPv6 |
| **A6, A7** (B2) | libellé du formulaire public lu sur l’agence ; date et demi-journée d’un report en heure locale |
| **A11** : page d’annulation collée sous l’en-tête | page refaite dans le gabarit des confirmations, avec le rappel du rendez-vous (sans consommer le lien) et « Garder mon rendez-vous » ; `storage/recette/` ignoré par git |
| Écrans | colonnes de la semaine titrées « Lundi 07/12 » ; panneaux repliables avec en-tête ; plages, absences et durées sur surfaces teintées, champs alignés |

**Non repris, avec motif** :
- **A3**, compteurs en base plutôt qu’en fichiers : le verrou exclusif suffit sur un seul serveur, et une table demanderait une migration réservée à la 2C (014).
- **A8**, valeurs par défaut sur Dakar sans agence : comportement voulu tant que Dakar est l’agence de référence.
- **A12**, deux bases `_recette` orphelines créées par une session antérieure : pas créées par moi, laissées à l’utilisateur.

**Tests** : `recette.php` **129/129** et `recette-agenda.php` **11/11**, sur MySQL 8.4 comme sur MariaDB 10.11.15 ; `repetition-012-013.php` **13/13** sur les deux.

### 5. Contre-vérification ([conditions-2b-reprise](verifications/2026-11-27-conditions-2b-reprise.md), CONFORME AVEC RÉSERVES)

| Réserve | Suite |
| --- | --- |
| **R1** : le test « rafale » HTTP ne prouvait pas le verrou (serveur PHP intégré, une requête à la fois) | `recette-agenda.php` lance **6 processus PHP réellement simultanés** (barrière de départ commune), création volontairement lente sous le verrou : **3 acceptés, 3 refusés, 3 en base**. **Essai de mutation** : verrou retiré, le test **échoue** (4 acceptés) ; verrou remis, il passe. |
| **R2** : `::ffff:a.b.c.d` regroupé sous un /64 commun | ramené à l’IPv4 |
| **R3** : sans fichier de verrou, poursuite sans verrou | verrou nommé SQL de secours (`GET_LOCK`, MySQL et MariaDB), sinon refus prudent |
| **R4** : libellé public toujours « Dakar » | « heure locale de l’agence » (les créneaux de chaque mode sont calculés dans le fuseau de son agence) |
| **R7** : page d’annulation proposant d’annuler une demande à date passée | même condition que l’annulation elle-même |
| **R5** : six captures montrent l’ancien triangle du panneau replié | accepté : écart cosmétique, l’écran livré est celui des captures des durées et disponibilités |
| **R6** : `LOWER(TRIM(email))` sans index | accepté pour la 2B : volume faible ; un index normalisé ira avec la migration 014 si le volume l’exige |
| **R7** : bulle de l’assistant sur le lien à 375 px ; date non centrée sur la capture 768 (corrigée depuis dans le code) | accepté : l’assistant est hors du lot de publication 2B |
| **A8** (accepté sous condition) | à reprendre avant d’ouvrir au public une agence hors UTC+0 (Paris) |

**Tests** : `recette.php` **129/129** et `recette-agenda.php` **12/12** sur MySQL 8.4 comme sur MariaDB 10.11.15.

---

## 2C Plan détaillé : compte rendu et ouverture du dossier (2026-11-27)

Point de départ : commit `ebe049c` (2B close en local, [décision](validations/2026-11-27-conditions-2b-levees.md)). Conditions : **C-2C-1** (ce plan validé d’abord ; e-mail normalisé indexé dans 014 ; interne ou partagé tranché), **C-2C-2** (C7 appliquée à 014 sur MySQL 8.4 et MariaDB 10.11.15). La numérotation devient **014** (013 est prise par les réglages d’agenda).

### 1. Décisions tranchées

| N° | Question | Décision |
| --- | --- | --- |
| E1 | Interne ou partagé | **Choix obligatoire à chaque note, sans valeur par défaut** (deux boutons radio, aucun coché ; le serveur refuse l’envoi sans choix). Une note **ne change jamais** de visibilité après coup : une erreur se corrige par une nouvelle note (journal intact, rien ne fuit par une modification). |
| E2 | Qui lit une note **interne** | les postes qui tiennent la personne dans leur périmètre (`Access::personnesSql`) **sauf** la secrétaire et le comptable (C5), et l’administrateur. Jamais le client, jamais un e-mail, jamais la recherche, jamais l’assistant. |
| E3 | Qui lit une note **partagée** | les mêmes, plus la secrétaire et le comptable, plus **le client** dans `/mon-espace/` si la personne a un compte. Un e-mail « compte rendu disponible » part à la personne (sans le texte de la note). |
| E4 | Qui écrit | administrateur, conseiller, orientation, traitement, sur une personne de leur périmètre. Secrétaire, comptable, prospection, commerciale : non (403 journalisé). |
| E5 | Compte rendu d’entretien | une note liée à un rendez-vous (`appointment_id`). Seul le responsable du rendez-vous (ou l’administrateur) l’écrit, une fois l’heure de début passée. Un rendez-vous `confirme` passe alors à `honore`. Un rendez-vous a **au plus un** compte rendu (index unique) ; les compléments sont des notes ordinaires. |
| E6 | Notes de dossier existantes | `request_events` (1B) reste le fil du dossier. Les notes de la 2C portent sur la **personne**, avec un dossier lié facultatif. Pas de double saisie, pas de reprise. |
| E7 | Ouvrir un dossier | administrateur et conseiller, depuis la fiche personne. Le dossier naît **affecté au conseiller qui l’ouvre** (un administrateur choisit un conseiller ou la file des conseillers), puis suit la chaîne de la 1B. Jeton de formulaire à usage unique (P8). |
| E8 | Modèles | versionnés : une **version est une ligne** (clé + numéro). Statuts `brouillon`, `publie`, `retire`. Une seule version publiée par clé. Seule une version **publiée** est proposée à l’ouverture (D5) ; on peut ouvrir **sans modèle**. Le dossier garde l’identifiant de la version : une nouvelle version **se propose** au responsable, sans rien modifier. |
| E9 | Fraîcheur | alerte si la vérification d’une version publiée date de plus de 6 mois (`config/gestion.php`, réglable), sur la liste des modèles et au moment d’ouvrir un dossier. |
| E10 | Champs obligatoires du dossier | `service_requests.email` et `phone` deviennent **facultatifs** (une personne peut n’avoir que l’un des deux) ; retour arrière protégé par garde-fou, comme 011. |

### 2. Migration 014 `014_notes_modeles.sql` (+ `rollback/014_notes_modeles.down.sql`)

- **`notes`** : `person_id` (obligatoire), `request_id` et `appointment_id` (facultatifs), `visibility` ENUM(`interne`, `partagee`) **sans défaut**, `body`, `author_id`, `created_at`. Clé unique `(appointment_id)` (un compte rendu par rendez-vous ; plusieurs NULL autorisés). Index `(person_id, created_at)`. Pas de `updated_at` : les notes ne se modifient pas.
- **`procedure_templates`** : `template_key`, `version`, `title`, `service`, `destination`, `status` ENUM(`brouillon`, `publie`, `retire`), `steps` JSON (titre, pièces, délai indicatif en jours), `sources` JSON (URL officielles), `verified_at`, `verified_by`, `created_by`, dates. Clé unique `(template_key, version)`.
- **`request_steps`** : `request_id`, `position`, `title`, `documents` JSON, `due_days`, `done_at`, `done_by`. Copiées du modèle à l’ouverture, ensuite indépendantes du modèle.
- **`service_requests`** : `template_id` (version utilisée, facultatif), `intake` (rentrée visée, texte court), `opened_by` ; `email` et `phone` facultatifs (E10).
- **`appointments.email_normalized`** (condition R6) : minuscules, sans espaces, sans alias « +… », **indexé** ; reprise relançable en SQL portable (`LOWER`, `TRIM`, `SUBSTRING_INDEX`, `CONCAT`) ; tenu à jour par l’application ; `BookingLimiter::actifs()` l’utilise.
- **Données de départ** : les 3 modèles de D5 (France Campus France, Canada permis d’études, Belgique) en **brouillon**, contenu marqué « à faire vérifier par l’agence », sans date de vérification.
- **C7** : clés étrangères en `RESTRICT`, reprise relançable avec `updated_at = updated_at`, aucun `CHECK` nouveau, retour arrière avec garde-fous (refus si des notes, des étapes ou un dossier sans e-mail ou sans téléphone existent), `bin/repetition-014.php` : aller, retour à l’identique, interruption et reprise, sur MySQL 8.4 et MariaDB 10.11.15.

### 3. Code et écrans

- **Modèles** `Note`, `ProcedureTemplate` ; `Access` : `peutEcrireNote`, `notesVisiblesSql` (filtre unique, E2 et E3), `peutOuvrirDossier`, module `modeles` (administrateur).
- **Fiche personne** : bloc « Comptes rendus et notes » (fil daté, pastille Interne ou Partagé, formulaire avec choix obligatoire) ; bloc « Ouvrir un dossier » (service, destination, rentrée, modèle publié, ou sans modèle ; les brouillons sont signalés et non sélectionnables).
- **Agenda** : lien « Compte rendu » sur un rendez-vous passé dont on est responsable ; formulaire titré « Compte rendu de l’entretien du … ».
- **Dossier** : étapes avec avancement ; mention de la version du modèle ; bandeau « nouvelle version disponible » pour le responsable.
- **Modèles** `/gestion/modeles/` (administrateur) : liste avec alerte de fraîcheur, création, nouvelle version, publication et retrait, date et auteur de la vérification.
- **Espace client** : « Comptes rendus » partagés de la personne du compte ; e-mail `report-shared` sans le contenu.
- **Charte** : bleu pour les actions courantes, vert pour enregistrer et publier, orange pour retirer une version, rouge réservé au danger ; surfaces teintées, sans bordure colorée.

### 4. Recette (robot HTTP, en plus des 129 contrôles)

| N° | Scénario | Attendu |
| --- | --- | --- |
| P5 | Note interne | absente de `/mon-espace/`, de la fiche vue par un poste non affecté, de la recherche et du journal des e-mails |
| P5 bis | Secrétaire et comptable sur la fiche | les notes internes sont absentes du HTML ; les partagées sont visibles |
| P6 | Note partagée | visible dans `/mon-espace/` du compte, un e-mail en file **sans le texte** |
| E1 | Envoi sans choix de visibilité, ou visibilité inconnue | refus, rien d’enregistré |
| E4 | Écriture par la secrétaire, le comptable, la prospection, la commerciale, ou hors périmètre | 403 journalisé |
| E5 | Compte rendu avant l’heure du rendez-vous, ou par un autre agent ; second compte rendu | refusé ; le premier fait passer le rendez-vous à `honore` |
| P7 | Ouverture depuis la fiche | dossier affecté à l’ouvreur, étapes copiées, version enregistrée ; publier une version 2 ne change pas ce dossier et la propose au responsable |
| P8 | Double soumission de l’ouverture, simultanée | un seul dossier |
| P20 | Modèle en brouillon | absent des choix et refusé s’il est forcé ; ouverture sans modèle possible |
| P19 | Assistant | aucune note dans son index |
| R6 | Plafond anti-abus | lit `email_normalized` (index utilisé, vérifié par `EXPLAIN`) |

Ensuite : `repetition-014.php` sur les deux moteurs, 18 captures (fiche avec notes, ouverture d’un dossier, compte rendu, modèles, dossier avec étapes, espace client ; à 1440, 768 et 375 px), puis le `verificateur` et le `validateur`.

### 5. Réservé à l’agence

Le contenu des 3 modèles (étapes, pièces, sources officielles, question du CAQ pour le Canada), le texte de l’e-mail « compte rendu disponible », et le délai de fraîcheur (6 mois proposés).

### 6. Amendements de la validation du plan (N1 à N12)

Décision : [validations/2026-11-27-plan-2c.md](validations/2026-11-27-plan-2c.md), **GO SOUS CONDITIONS**. Ces amendements **remplacent** les points correspondants des §1 à §4 ci-dessus ; chacun sera contrôlé par le `verificateur` en fin d’étape.

| N° | Amendement |
| --- | --- |
| N1 | Les notes **internes** ne se lisent que par l’administrateur, ou par un poste **affectable** qui tient la personne (dossier tenu, rendez-vous tenu, fiche créée), calculé par la branche affectable de `personnesSql()`, **jamais** par le raccourci de la vue transversale. Un compte « secrétaire avec la mission traitement » ne lit que les notes internes des personnes qu’il tient au titre du traitement. Un seul filtre `Access::notesVisiblesSql()` pour la fiche, l’agenda, le dossier, la recherche et les compteurs. Le comptable ne lit aucune note interne en 2C. |
| N2 | **Pas de `notes.request_id`** : `request_events` reste l’unique fil du dossier (E6 amendée). |
| N3 | Compte rendu accepté seulement sur un rendez-vous `confirme` (qui passe à `honore`) ou déjà `honore` ; refusé sur `demande`, `absent`, `reporte`, `annule`, sans responsable, ou avant l’heure de début (UTC, en PHP). Écriture et changement de statut dans **une transaction**. |
| N4 | `notes.author_poste` obligatoire. Le client voit le prénom et le poste de l’auteur, rien d’autre. Aucune route de modification ni de suppression. |
| N5 | Version publiée **unique par la base** : colonne générée `STORED` (la clé si `publie`, sinon NULL) avec index unique. Publier retire la précédente dans la même transaction. Seul un `brouillon` se modifie ; une version publiée ou retirée n’accepte qu’une revérification (`verified_at`, `verified_by`). Publication refusée sans vérification ni au moins une source. Rien n’est jamais supprimé. |
| N6 | Ouverture en **une transaction** : dossier, affectation `conseiller` (personne ou file), copie des étapes, événement d’ouverture (`visible = 0`). Personne obligatoire et dans le périmètre de l’ouvreur (403 journalisé). Conseiller choisi actif et du poste `conseiller`. `template_id` revérifié : version publiée. |
| N7 | Double soumission bloquée **en base** : jeton d’ouverture dans une colonne unique de `service_requests`. Prouvé par des processus réellement parallèles, avec essai de mutation. |
| N8 | `appointments.email_normalized` : colonne générée `STORED`, indexée, même expression portable sur les deux moteurs. `BookingLimiter::email()` aligné sur elle, et un test compare PHP et SQL (majuscules, espaces, alias, « + » sans partie locale, sans « @ », deux « @ »). `actifs()` interroge la colonne nue, `EXPLAIN` montre l’index. Jamais utilisée pour rattacher une personne. |
| N9 | Dossier sans e-mail : aucune notification, aucune ligne dans `mail_outbox`, aucune erreur ; les dossiers existants restent identiques (P18). |
| N10 | Cocher une étape : agent de traitement affecté et administrateur ; conseiller, orientation et comptable lisent ; les autres reçoivent 403. Le client ne voit pas les étapes en 2C. « Sans modèle » ouvre un dossier sans étapes ; le texte de la maquette est corrigé. |
| N11 | Recette ajoutée : compte à deux postes (N1), client A sur les notes du client B, 419 sur chaque nouveau formulaire, `/gestion/modeles/` (403 pour les 7 autres postes et le client), ouverture (403 pour secrétaire, orientation, traitement, comptable, prospection, commerciale, et conseiller hors périmètre), droits sur les étapes, modification ou suppression d’une note (404 ou 405, note intacte), deux versions publiées (refus), fraîcheur (alerte à 7 mois, aucune à 5), dossier ouvert visible par le client de la personne et refusé à un autre, passage à l’orientation par la chaîne 1B, dossier sans e-mail, garde-fous du retour arrière de 014, matrice des 8 postes et du client sur chaque nouvelle route. |
| N12 | E-mail « compte rendu disponible » : seulement si la personne a un **compte actif**, vers `users.email`, jamais vers l’adresse du rendez-vous ou de la fiche ; sans le texte ni l’auteur ; rien pour une personne sans compte. |

### 7. Tests de la 2C reportés (décision de l’utilisateur, 2026-11-27)

L’utilisateur a demandé de passer à la suite **sans tester la 2C maintenant** : les tests se feront **à la fin du projet de gestion**, dans une recette d’ensemble.

**État livré, non testé** (commit `ee1e087`) : notes et comptes rendus, ouverture d’un dossier, modèles de procédure, étapes, espace client. Seuls ont été vérifiés : la syntaxe de tous les fichiers, le robot existant (**129/129**, aucune régression) et la migration 014 (**17/17** sur MySQL 8.4 et MariaDB 10.11).

**Dette de test à solder avant toute mise en ligne de la 2C** (rien de la 2C ne part en production sans elle) :
- scénarios de la recette 2C (§4) et de N11 : compte à deux postes (N1), client A face aux notes du client B, 419 sur chaque nouveau formulaire, matrice des 8 postes et du client sur chaque nouvelle route (`/gestion/personnes/{id}/notes`, `/gestion/personnes/{id}/dossier`, `/gestion/rendez-vous/{réf}/compte-rendu`, `/gestion/modeles/…`, `/gestion/dossiers/{réf}/etapes/{id}`), modification ou suppression d’une note (404 ou 405), deux versions publiées, fraîcheur à 5 et 7 mois, dossier ouvert visible par le client de la personne et refusé à un autre, passage à l’orientation par la chaîne 1B, dossier sans e-mail (N9), E-mail N12 ;
- P7 et P8 : ouverture, puis double soumission par **processus réellement parallèles** avec essai de mutation (N7) ;
- P5, P5 bis, P6, P19, P20 ;
- captures des écrans 2C à 1440, 768 et 375 px ;
- rapport du `verificateur`, puis décision de fin d’étape du `validateur`.

Le même report s’applique aux étapes suivantes tant que l’utilisateur n’en décide pas autrement : chaque étape note sa propre dette de test ici, pour la recette d’ensemble.

---

## 2D Plan détaillé : e-mails, rappels, « Ma journée », tâches (2026-11-27)

Point de départ : commit `0fda20e`. Tests reportés à la fin du projet (§7) : seuls la syntaxe, les robots existants et la répétition de la migration 015 sont joués à cette étape.

### 1. Décisions

| N° | Question | Décision |
| --- | --- | --- |
| F1 | E-mails de rendez-vous | Confirmation (existe : date et heure locales, lieu) complétée de l’adresse de l’agence de Ziguinchor ; **report** : un e-mail propre « votre rendez-vous est déplacé » avec l’ancienne et la nouvelle heure (aujourd’hui il réutilise « confirmé ») ; annulation (existe) ; compte rendu disponible (2C, N12). Visio : pas de lien automatique en 2D (aucun outil choisi) ; le texte annonce que le lien est envoyé par le conseiller. |
| F2 | Rappel la veille | `bin/rappels.php`, protégé par `flock`. Pour chaque agence, « demain » est calculé **dans le fuseau de l’agence** puis converti en UTC. Un rappel par rendez-vous `confirme` de demain, posé de façon **atomique** (`UPDATE … SET reminded_at = UTC_TIMESTAMP() WHERE id = ? AND reminded_at IS NULL AND status = 'confirme'`, e-mail seulement si une ligne change). Aucun rappel si la confirmation date de moins de 24 h avant le début (dernier événement « confirme » de `appointment_events`, sinon `created_at`). Un report crée un nouveau rendez-vous : son rappel suit la nouvelle date. Le script relance ensuite la file d’e-mails. |
| F3 | Tâche planifiée | **Pas de ligne cron en production** tant que le pilote d’e-mail reste `log` (C9) ; ensuite, sur décision de l’utilisateur : une seule ligne ajoutée à la crontab de `jomas`, toutes les 10 minutes, après sauvegarde de la crontab. |
| F4 | Tâches | Table `tasks` (migration **015**) : intitulé, échéance (date, facultative), personne **ou** dossier lié (facultatifs), responsable (obligatoire), auteur, `done_at`/`done_by`. Visibles par leur **responsable**, leur **auteur** et l’**administrateur** ; jamais par le client. Création depuis la fiche personne et depuis le dossier, pour une personne ou un dossier qu’on peut ouvrir. Responsable : soi-même ; l’administrateur et la secrétaire peuvent désigner un membre actif. Cochées par le responsable ou l’administrateur. Pas de suppression : une tâche se coche ou se rouvre. |
| F5 | « Ma journée » | Par poste : **rendez-vous du jour** dans le fuseau de l’agence du compte (les siens pour conseiller et orientation ; ceux de l’agence pour l’administrateur et la secrétaire, à partir du périmètre de l’agenda) ; **tâches dues** (en retard, aujourd’hui, puis à venir) cochables sur place ; **dossiers à reprendre** (files de la 1B, existant). Charte : surfaces teintées, cases vertes, rien de rouge hors retard. |

### 2. Migration 015 `015_taches.sql` (+ `rollback/015_taches.down.sql`)

Table `tasks` : clés étrangères en `RESTRICT` (personne, dossier, responsable, auteur, auteur de la clôture), index `(assigned_to, done_at, due_date)`. Retour arrière avec garde-fou : refus s’il existe une tâche. Répétition `bin/repetition-015.php` sur MySQL 8.4 et MariaDB 10.11.

### 3. Dette de test de la 2D (pour la recette d’ensemble)

P9 et P9 bis (deux exécutions simultanées du script ; un rendez-vous reporté : un seul rappel, à la nouvelle date ; confirmation tardive : aucun rappel ; veille calculée hors UTC+0), P16 (tâches : périmètre, cocher, 403 journalisé pour un autre responsable, 419), e-mail de report, « Ma journée » de chacun des 8 postes, captures à 1440, 768 et 375 px.

### 4. Amendements de la validation du plan 2D (G1 à G12)

Décision : [validations/2026-11-27-plan-2d.md](validations/2026-11-27-plan-2d.md), **GO SOUS CONDITIONS**. Ces amendements priment sur F1 à F5.

| N° | Amendement |
| --- | --- |
| G1 | Le `UPDATE` du rappel et la mise en file de l’e-mail dans **une même transaction** ; e-mail seulement si une ligne change ; bornes de date revérifiées dans le `WHERE`. |
| G2 | Tout changement d’heure d’un rendez-vous confirmé (report ou déplacement sur place) remet `reminded_at` à NULL et met en file l’e-mail « déplacé ». |
| G3 | `update()` écrit **un événement par nature de changement** (statut, affectation, déplacement). Référence des 24 h : le dernier événement qui a fixé l’heure actuelle (création, confirmation, déplacement), sinon `created_at`. |
| G4 | La veille est calculée en PHP pour chaque fuseau, y compris celui par défaut (rendez-vous sans agence). Ni `CONVERT_TZ`, ni `NOW()`, ni `CURDATE()`. |
| G5 | E-mails : heure dans le fuseau de l’agence et, en visio, aussi dans celui de la personne ; lieu tiré de l’agence (l’adresse de Ziguinchor est à demander à l’agence) ; aucune donnée interne ; textes à faire vérifier par l’agence. |
| G6 | Les rappels partent à partir d’une heure locale réglable (9 h) ; pas de rattrapage le jour même. |
| G7 | `bin/rappels.php` : ligne de commande seulement, verrou distinct de `send-mails`, sortie sans donnée personnelle. |
| G8 | Une tâche a au plus un lien (personne ou dossier), contrôlé pour l’auteur et le responsable (403 journalisé), revérifié à la lecture ; responsable actif ; jamais côté client, recherche, e-mail ni assistant. |
| G9 | `tasks.form_token CHAR(64)` avec index unique, ajouté à 015 (pas encore appliquée hors du poste local) ; cocher et rouvrir par `UPDATE` conditionnels, sans effet s’ils sont répétés. |
| G10 | Création, clôture et réouverture d’une tâche au journal ; CSRF sur chaque nouveau formulaire. |
| G11 | « Ma journée » : page normale pour chacun des 8 postes avec ses seuls blocs ; « aujourd’hui » et « en retard » calculés en PHP dans le fuseau de l’agence du compte. |
| G12 | Répétition de 015 après G9 sur MySQL 8.4 et MariaDB 10.11.15 ; robot existant à 129/129. |

### 2D — Résultat et clôture locale

« Ma journée » affiche les rendez-vous du jour dans le périmètre du compte, les files existantes et les tâches ouvertes ou clôturées. Les agents créent les tâches depuis l’accueil, les personnes et les dossiers. Le modèle contrôle le lien à la création et à la lecture, empêche les doubles soumissions et inscrit les changements d’état au journal dans la même transaction.

Le script de rappels calcule la veille dans le fuseau de l’agence. Il marque le rendez-vous et ajoute l’e-mail dans une même transaction. La file vérifie encore le statut, l’heure et l’expiration avant envoi. Les courriels de report précisent l’ancien et le nouveau créneau. La migration 016 stocke les bornes de validité des rappels et permet plusieurs liens d’annulation valides, chaque annulation consommant tous les liens du rendez-vous.

**Validation locale** approuvée dans [la décision 2D](validations/2026-11-27-fin-etape-2d.md). MySQL 131/131, MariaDB 130/130 avant le dernier scénario. Migration 015 à 12/12 sur les deux moteurs. Migration 016 répétée sur les deux moteurs avec garde-fous et reprise, puis appliquée à la base WAMP de navigation locale.

La couverture en conditions réelles reste dans la recette d’ensemble. Le verrou de livraison prévient deux envois concurrents par l’application. Une coupure après l’acceptation SMTP et avant l’enregistrement du résultat peut encore conduire à un renvoi. Le cron de production reste inactif.
