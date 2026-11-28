# Phase 9 — Recette d’ensemble et préparation de la mise en service

Plan de gestion version 2.4 (28/11/2026). **Aucune reprise de l’ancienne application GAOV** : elle n’est plus utilisée, et l’agence travaille sur la suite Office en attendant le nouveau système. Les référentiels (destinations, établissements, modèles) seront saisis dans la nouvelle plateforme.

Les tests reportés depuis la 2C sont soldés ici. Sans sous-agents : je vérifie moi-même, et nous décidons ensemble.

## Lots

### 9A — Matrice des droits, automatique

- **Couverture.** Chaque page de `/gestion/` sans paramètre, plus les trois exports, est appelée par les profils suivants :
  - les 8 postes ;
  - un client ;
  - un visiteur non connecté.
- **Résultat attendu, écrit à la main dans le robot** d’après le §3.5 du plan, et **pas lu dans `Access`** : un test qui recopie le code ne vérifie rien.
  - 200 pour un poste autorisé ;
  - 403 pour un poste non autorisé et pour le client ;
  - redirection vers la connexion pour le visiteur.
- **Contrôle d’ensemble.** Toute route `/gestion/` en GET qui n’est pas dans la matrice fait échouer le contrôle. Une nouvelle page ne peut donc pas échapper au test.

### 9B — Scénarios métier reportés

Les dettes notées à chaque étape :

- **2C** (phase 2, §dette) :
  - un compte à deux postes ;
  - les notes du client B, invisibles pour le client A ;
  - le jeton CSRF (419) sur chaque nouveau formulaire ;
  - une note ne peut être ni modifiée ni supprimée ;
  - modèles de procédure publiés en deux versions ;
  - fraîcheur des pièces à 5 et à 7 mois ;
  - dossier ouvert visible par la bonne personne seulement ;
  - dossier sans e-mail ;
  - ouverture en double par processus parallèles (P7, P8).
- **2D** :
  - rappels : exécutions simultanées, rendez-vous reporté, confirmation tardive, veille calculée hors UTC+0 (P9) ;
  - tâches : périmètre, cocher, refus journalisé (P16) ;
  - e-mail de report ;
  - « Ma journée » des 8 postes.
- **Phase 4** : fusion d’une fiche liée à un compte ; qualification sur MariaDB.
- **Phase 7** :
  - lien WhatsApp avec un numéro invalide ;
  - journal des messages et des exports ;
  - anonymisation : reçus intacts et fichiers supprimés.

Les scénarios déjà couverts par le robot (172 contrôles) ne sont pas refaits. Je liste ceux qui manquent, puis je les ajoute.

### 9C — Préparation de la mise en service

- Répétition des migrations 011 à 024 sur une **copie** de la base de production, sur un MariaDB 10.11 isolé. Jamais sur la base de production elle-même.
- Sauvegarde restaurable, avec un test de restauration.
- Ordre de mise à jour, rédigé pour `bin/deploy-goo-bridge.sh`.
- Comptes réels de l’équipe, avec leurs invitations.
- Tâches planifiées : e-mails et rappels. Le cron reste inactif tant que l’envoi d’e-mails est en mode `log`.
- La mise en ligne (push) reste faite par l’utilisateur.

## Hors de cette phase

- **Captures d’écran et contrôle visuel complet** : déplacés à la phase 10 (refonte des interfaces). Les écrans vont changer, et les capturer maintenant serait du travail perdu.
- **Phase 8** (signature et encaissement en ligne) : en attente du choix des prestataires.

## Résultat

### 9A — fait le 28/11/2026

- La matrice couvre **24 pages** : 21 pages de gestion et 3 exports. Chaque page est appelée par les 8 postes, le client et un visiteur, soit **240 appels**. Le contrôle d’ensemble vérifie que toutes les routes déclarées sont dans la matrice.
- Robot : **197/197** (172 contrôles existants, plus 25 nouveaux). Aucun écart entre les droits attendus et les droits réels.
- **Test de mutation.** J’ai faussé volontairement la matrice : une page retirée, et un droit ajouté à la secrétaire sur « Fiches à rapprocher ». Les deux contrôles échouent, comme il se doit (194/196). Le test sait donc détecter une erreur.
- **Limite.** Les pages à paramètre (dossier, fiche, pièce, reçu) ne sont pas dans cette matrice.

### 9B — fait le 28/11/2026

Le robot compte 27 contrôles de plus. Il passe à **224/224 sur MySQL 8.4 et sur MariaDB 10.11**, et le robot agenda reste à 12/12. **Aucun défaut n’a été trouvé dans l’application** : les seuls écarts venaient de mes données de test, et je les ai corrigés.

| Dette | Contrôle ajouté |
| --- | --- |
| 2C E1 | Une note sans visibilité, ou avec une visibilité inconnue, est refusée et rien n’est enregistré. |
| 2C E4 | Une note écrite par la secrétaire, le comptable, la prospection, la commerciale ou un conseiller hors périmètre reçoit 403, et le refus est journalisé (5 sur 5). |
| 2C P5, P5 bis | **Note interne :** lue par le conseiller, l’administrateur et le traitement affecté. Absente pour le client, la secrétaire et le comptable, dans les résultats de recherche et dans les e-mails. |
| 2C P6, N12 | **Note partagée :** visible par le client, la secrétaire et le comptable. Un e-mail part vers le compte, sans le texte. Rien chez un autre client. |
| 2C N4 | Modifier une note : 404, et la note reste intacte. |
| 2C N1 | Secrétaire avec la mission « traitement » : elle lit la note interne de la personne qu’elle tient à ce titre, jamais celles des autres fiches. |
| 2C E8, N5, E9 | **Modèles de procédure :** publication refusée sans vérification ; la version 2 retire la 1 ; jamais deux versions publiées en même temps ; alerte « À revérifier » à 7 mois, aucune à 5 mois. |
| 2C P7, N6, P20, P8, N9, N10, N11 | **Ouverture d’un dossier :** <ul><li>affecté à l’ouvreur, avec les étapes copiées et la version du modèle figée ;</li><li>une version 3 publiée est proposée sans rien modifier ;</li><li>refusée à 6 postes et à un conseiller hors périmètre ;</li><li>modèle en brouillon refusé ; ouverture possible sans modèle ;</li><li>double ouverture **simultanée** : un seul dossier ;</li><li>visible par le bon client seulement ;</li><li>personne sans e-mail : aucun e-mail mis en file ;</li><li>étapes cochées par l’administrateur seulement.</li></ul> |
| 2C E5, N3 | **Compte rendu :** refusé avant l’heure et pour un autre agent. Le premier fait passer le rendez-vous à « honoré », et il n’y en a qu’un par rendez-vous. L’e-mail part vers le compte, jamais vers l’adresse du rendez-vous. |
| 2C, 419 | Note, ouverture, modèle, tâche et compte rendu envoyés sans jeton : 419 partout. |
| 2D P16 | **Tâches :** créées une seule fois malgré un double envoi ; visibles de leur seul responsable ; le refus pour un autre est journalisé ; le responsable peut cocher. |
| 2D F1, P9 bis | **Rendez-vous :** <ul><li>e-mail « rendez-vous déplacé » lors d’un report ;</li><li>rendez-vous reporté : un seul rappel, à la nouvelle date, aucun pour l’ancien ;</li><li>confirmation à moins de 24 h du début : aucun rappel.</li></ul> |
| Phase 4 | Fusion d’une fiche liée à un compte client : refusée, rien n’est déplacé. |
| Phase 7 | **Messages :** pour un numéro invalide, ni bouton WhatsApp ni ligne de journal ; une ouverture d’e-mail est journalisée sans le texte. **Anonymisation :** le fichier de la pièce est supprimé, le dossier est anonymisé, le règlement et le reçu restent intacts. |

**Encore à couvrir**, avec la raison :

- **P19 (aucune note dans l’assistant)** : le code de l’assistant n’est pas encore commité, il est hors lot. Le contrôle est à ajouter quand il le sera.
- **R6 (`EXPLAIN` sur `email_normalized`)** : vérifié lors de la répétition 014. Le contrôle n’est pas refait dans le robot.
- **Rappel la veille pour une agence hors UTC+0** : la seule agence est à Dakar (UTC+0). À couvrir si une agence dans un autre fuseau est ouverte.
- **« Ma journée » des 8 postes** : la page répond pour chacun (matrice 9A). Le contenu par poste sera contrôlé à l’œil en phase 10.

**Note d’exécution.** Un passage du robot a pris plus de 10 minutes, avec un appel tombé en délai dépassé (code 0). Les passages suivants ont pris environ 1 min 30 sur les deux moteurs. Le ralentissement venait de la machine, pas d’un verrou : le bloc 9B tourne en 8 secondes. À surveiller s’il revient. Leur périmètre est vérifié par les scénarios existants : T7, 2A, 5A, 7A, 7C.

### 9C — Préparation et vérifications du 28/11/2026

- MariaDB **10.11.15 isolé**. La répétition du schéma 010 vers 024 réussit à **19/19** sur un jeu fictif avec comptes, dossiers, rendez-vous et pièces référencées. Le retour à 010 retrouve le schéma et les données comparées, puis la réapplication de 024 réussit.
- Une sauvegarde SQL **fictive** produite avec le client de sauvegarde MariaDB a été restaurée dans une base de recette vide, avant de rejouer la séquence complète. Résultat **20/20**, puis suppression du fichier, des bases de test et du compte temporaire. Le mode `--depuis` exige MariaDB 10.11, un port local isolé et un compte limité aux schémas de test.
- Les entrées cron Webuzo et le [plan de mise en service](mise-en-service-9c.md) sont prêts. Le robot vérifie que les deux commandes planifiées sont inactives avec le pilote `log`. La recette d’ensemble atteint **227/227** sur MySQL et sur MariaDB 10.11.
- Le relevé en lecture seule du VPS indique MariaDB **10.11.15**, la migration **010**, **zéro compte équipe**, et le pilote e-mail **`log`**.

**Qualification à partir du schéma du VPS.** Après le refus de transfert d’une sauvegarde intégrale, une copie de recette a été reconstruite à partir du schéma en service, du registre des migrations et de comptages agrégés. Le rendez-vous, les 81 articles et les deux e-mails ont été remplacés par des valeurs fictives tout en conservant leurs volumes et catégories. Le VPS contenait zéro compte, zéro dossier et zéro pièce téléversée au moment du relevé. Les contenus, coordonnées et fichiers réels sont restés sur le VPS.

Sur MariaDB 10.11.15 isolé, la restauration de cette copie et la séquence **010 → 024 → 010 → 024** passent à **20/20**. Le schéma et les lignes comparées correspondent après retour arrière. La copie, les fichiers de recette et le compte SQL temporaire ont été supprimés. Cette qualification complète la répétition sur le jeu fictif riche décrit ci-dessus.

**Prévol du VPS.** La révision en service est `403c4dd`. PHP CLI 8.4.28 charge `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip` et `SimpleXML`. Le compte du site peut écrire dans les cinq répertoires d’exécution contrôlés. Le VPS a uniquement été consulté en lecture.

**Mise en service.** Le lot de gestion et la phase 10 sont publiés dans la révision `4922bec`. La base de production est à 024. Les sauvegardes chiffrées ont été contrôlées, le VPS répond en HTTPS et les espaces privés redirigent vers la connexion. Reste à créer le premier administrateur, vérifier les parcours en production avec les comptes réels, puis valider le SMTP avant les invitations et l’activation du cron. Voir le [journal de mise en service](mise-en-service-9c.md#mise-en-service-du-28112026).
