# Phase 7 — Communication, pilotage et conformité

Plan du 28/11/2026, décidé avec l’utilisateur. Tests nouveaux reportés à la recette d’ensemble (voir `gestion-phase-2.md` §7) ; à chaque lot : syntaxe, robots existants, répétition de la migration sur MySQL 8.4 et MariaDB 10.11.

## Décisions de l’utilisateur (28/11/2026)

| Sujet | Décision |
| --- | --- |
| SMS et WhatsApp | **Liens WhatsApp d’abord** : un bouton ouvre WhatsApp avec un message pré-rempli tiré d’un modèle ; l’agent l’envoie depuis son téléphone. Aucun fournisseur, aucun coût. L’envoi automatique de SMS viendra plus tard, avec un fournisseur choisi par l’agence. |
| Modèles de messages | **Messages de l’équipe** : modèles prêts à l’emploi (pièce manquante, relance de paiement, rappel de rendez-vous…) avec les noms, dates et montants remplis automatiquement. Les textes des e-mails automatiques restent dans le code. |
| Effacement | **Anonymisation** : identité et coordonnées remplacées, pièces jointes supprimées, notes vidées ; les reçus et écritures comptables restent (obligation légale). Administrateur seulement, double confirmation, journal. |

## Lot 7A — Messages de l’équipe

- Table `message_templates` : titre, texte avec champs `{prenom}`, `{nom}`, `{reference}`, `{piece}`, `{solde}`, `{date_rdv}`, `{agence}`, canal proposé (WhatsApp, e-mail ou les deux), actif ou non. Géré par l’administrateur (`/gestion/messages-modeles/`). Quatre modèles de départ, **à faire relire par l’agence**.
- Depuis la fiche personne et le dossier : « Envoyer un message » → choix du modèle → aperçu rempli et modifiable → « Ouvrir WhatsApp » (`wa.me` avec le texte) ou « Ouvrir l’e-mail » (`mailto:`).
- Chaque ouverture est **journalisée** (`message_logs` : personne, dossier, modèle, canal, auteur, date), sans recopier le texte. Le passage par le serveur (POST puis redirection) permet ce journal.
- Droits : les postes qui peuvent ouvrir la fiche ou le dossier ; jamais de lien sans numéro valide.

## Lot 7B — Pilotage et exports

- Page « Pilotage » (administrateur) : indicateurs du mois et des 12 derniers mois, peu nombreux et fiables :
  - dossiers ouverts, par service, et dossiers clos ;
  - rendez-vous honorés, absents, annulés ;
  - prospects : nouveaux, transmis à l’accueil, taux de transmission ;
  - encaissements du mois et reste à encaisser.
- Exports CSV (séparateur `;`, UTF-8 avec BOM pour Excel) : dossiers (administrateur), finance (administrateur, comptable), prospects (administrateur). **Chaque export est journalisé** (type, filtres, nombre de lignes).

## Lot 7C — Conformité

- **Export des données d’une personne** (administrateur) : un fichier JSON lisible avec la fiche, le compte, les rendez-vous, les dossiers, les notes partagées, les échéances, les règlements et la liste des pièces. Journalisé.
- **Anonymisation** (administrateur) :
  - double confirmation : taper le nom de la personne ;
  - refusée si un dossier est encore ouvert (le clore d’abord) ;
  - fiche, compte (désactivé), rendez-vous, dossiers, visites, prospect : identité et coordonnées remplacées par « Personne effacée » ;
  - notes : texte remplacé ; pièces : fichiers supprimés du disque, lignes conservées sans nom ;
  - **règlements et reçus conservés tels quels** (nom compris) pour la durée légale comptable ;
  - `people.erased_at` posé ; journal sans données personnelles.
- **Durées de conservation proposées, à faire valider par l’agence** : prospect inactif 2 ans ; dossier clos 5 ans ; pièces d’identité 1 an après la clôture ; pièces comptables 10 ans. Rien n’est purgé automatiquement en phase 7 : la page liste seulement les fiches arrivées au terme.

## Migration 024

`message_templates`, `message_logs`, `people.erased_at` ; clés étrangères en `RESTRICT` ; retour arrière avec garde-fous (refus s’il existe des journaux de messages ou une personne effacée).

## Résultat (28/11/2026)

Les trois lots sont faits et commités localement ; rien n'est poussé (la production reste en 1B).

- **7A** (commit 6dc4f65) : `MessageEquipeController`, `MessageTemplate`, `MessageEquipe` ; vues `message-equipe`, `message-envoi`, `messages-modeles`.
- **7B** (commit 6b0827b) : `PilotageController`, vue `pilotage`.
  - `/gestion/pilotage/` (module `pilotage`, administrateur) : 5 indicateurs, barres sur 12 mois (dossiers ouverts, encaissements), et par mois : dossiers par service, rendez-vous, prospection.
  - `/gestion/exports/{dossiers|finance|prospects}/` (module `exports`) : le contrôleur réserve dossiers et prospects à l'administrateur, et ouvre la finance à l'administrateur et au comptable. Les cellules qui commencent par `= + - @` sont neutralisées contre l'injection de formules. Journal `export` avec le type et le nombre de lignes. Bouton « Exporter (CSV) » sur « Soldes et échéances ».
  - `CaseFinance::synthese()` accepte une limite : 300 lignes à l'écran, aucune limite pratique pour l'export et les totaux.
- **7C** : `DonneesPersonnelles` (service), `DonneesController` ; vues `donnees-personne` et `conservation`.
  - `/gestion/personnes/{id}/donnees/` (module `donnees-personnelles`, administrateur) : résumé de ce que Taysir garde, export JSON (journal `export_personne`) et anonymisation. Pour l'anonymisation, il faut cocher la case et taper le prénom et le nom (journal `anonymisation`, sans le nom).
  - Écarts au plan, à l'issue de la revue :
    - l'export contient **toutes** les notes de l'équipe, qui entrent dans le droit d'accès ; la page demande de les relire avant l'envoi ;
    - l'anonymisation est refusée aussi pour une fiche liée à un compte de l'équipe ;
    - les fiches fusionnées sont traitées avec la fiche principale ;
    - les messages de contact et les e-mails envoyés à ces adresses sont vidés ; les e-mails en attente sont supprimés ;
    - les lignes de pièces restent, car `request_requirements` y renvoie.
  - Gardés : `finance_payments` (nom du client compris) et les annulations.
  - `/gestion/conservation/` (module `conservation`) : listes seulement, rien n'est supprimé.
- Robot HTTP **172/172** sur MySQL 8.4 et MariaDB 10.11, avec les nouveaux contrôles 7B et 7C. Robot agenda 12/12.
- Points à surveiller :
  - les bornes des mois sont calculées dans le fuseau de l'agence, alors que `created_at` suit l'horloge du serveur SQL. C'est identique à Dakar (UTC+0) si le serveur est en UTC ;
  - le journal d'audit antérieur peut contenir des noms dans son détail (imports de prospects, par exemple) : à revoir à la recette.

## Dette de test (recette d’ensemble)

Droits de chaque nouvelle route pour les 8 postes et le client ; lien WhatsApp avec un numéro invalide ; journal des envois et des exports ; CSV ouvert dans Excel ; export JSON d’une personne ; anonymisation refusée avec un dossier ouvert, puis effective, reçus intacts, fichiers supprimés ; captures 1440, 768 et 375 px.
