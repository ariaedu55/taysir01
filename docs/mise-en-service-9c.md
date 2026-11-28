# Mise en service sur Goo Bridge

## Qualification du 28/11/2026

Avant la bascule, le VPS était à la migration 010 sur MariaDB 10.11.15. Les extensions PHP CLI et les droits d’écriture du compte du site ont été vérifiés. La copie de qualification reprend le schéma et les volumes de production avec des valeurs fictives. Elle a passé 20 contrôles, dont la restauration, les migrations 011 à 024, le retour à 010 et la reprise. Voir le [compte rendu](gestion-phase-9.md#9c--préparation-et-vérifications-du-28112026).

## Mise en service du 28/11/2026

- Révision `4922becb20a9b6f8229e035dbc4dc21124c9d829` publiée sur GitHub `main` et déployée sur `taysir.goo-bridge.com` depuis `403c4dd`. Une branche de publication dédiée rassemble le lot de gestion et les interfaces de phase 10. Les travaux locaux sur l’assistant restent dans la branche de travail d’origine.
- Sauvegardes chiffrées de la base, des pièces et de la configuration enregistrées dans `/root/taysir-bascule-20261128-DAacsx2Y`, sur le VPS. Les flux déchiffrés étaient lisibles et les empreintes des archives correspondent. L’emplacement de la clé est consigné dans ce répertoire protégé.
- Les 14 migrations 011 à 024 sont appliquées. Le site Taysir a été placé temporairement en maintenance. Nginx a repris sa configuration d’origine après les vérifications HTTPS. La page publique, la connexion, les destinations, la réservation et le blog répondent en 200. Les espaces privés redirigent vers la connexion et la maquette est inaccessible publiquement.
- Le pilote e-mail est encore `log`. L’agence doit désigner le premier administrateur et fournir la liste de l’équipe. Un essai SMTP réussi précède les invitations et l’activation des tâches planifiées.

## Avant la fenêtre de mise à jour

1. Relever le commit en service, la dernière migration enregistrée et la version du moteur. Préparer les droits du premier administrateur et relever les comptes actifs depuis « Équipe ». L’agence fournit, pour chaque personne à inviter, le nom, l’adresse professionnelle, le poste et l’agence. L’affectation des missions complémentaires se fait depuis la même page.
2. Préparer une sauvegarde MariaDB mono-base, sans `--databases`, hors du dossier web, avec `--single-transaction --quick --skip-lock-tables`. Conserver aussi `storage/uploads/requests` et les configurations locales. Les copies contenant des données personnelles restent dans un emplacement protégé, avec permissions restrictives et conservation chiffrée.
3. Pour rejouer la qualification, restaurer la copie sans données personnelles dans une instance MariaDB 10.11 dédiée, sur un port séparé, avec un compte temporaire dont les droits sont limités aux bases de recette. Exécuter `bin/repetition-mise-en-service.php --depuis=CHEMIN --exiger=10.11`, en passant le port et les identifiants temporaires par `TAYSIR_DB_PORT`, `TAYSIR_DB_USER` et `TAYSIR_DB_PASSWORD`. Pour les pièces présentes lors de la bascule, vérifier leur intégrité et tester leur restauration vers un répertoire protégé et séparé.
4. Contrôler en préproduction la révision qui sera publiée et la matrice des droits. Vérifier les extensions PHP `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`, et `zip` et `SimpleXML` pour XLSX. Préparer une boîte destinataire de test pour la validation SMTP.

## Ordre sur le VPS

1. Suspendre les tâches Taysir et les écritures du seul site pendant la fenêtre. Maintenir les autres sites et leur configuration en service. Refaire une sauvegarde de bascule si des écritures sont intervenues depuis la copie répétée.
2. Après le push effectué par le responsable, vérifier que la révision distante correspond au lot relu. Lancer, en root, `bash /home/jomas/taysir/bin/deploy-goo-bridge.sh`. Le script récupère `main` en avance rapide, vérifie les configurations, applique les migrations numérotées, prépare les dossiers de stockage et recharge PHP-FPM. Conserver la sortie de la commande dans le journal d’exploitation.
3. Vérifier la migration 024, puis la connexion depuis `/connexion/`, l’aiguillage de l’équipe vers `/gestion/`, celui d’un client vers `/mon-espace/` et l’ouverture d’un dossier selon ses droits. Vérifier aussi le catalogue public, la lecture d’une pièce de recette et un reçu PDF de recette. Réouvrir les écritures après ces vérifications.
4. Configurer le SMTP, valider un vrai envoi vers la boîte de test, puis inviter les personnes approuvées depuis `/gestion/equipe/`. Les personnes choisissent leur mot de passe avec le lien d’invitation. Ajouter les deux entrées de [la crontab Webuzo](../deploy/crontab-goo-bridge) à la crontab existante de `jomas`. La file est traitée chaque minute et les rappels sont mis en file toutes les quinze minutes. Les deux commandes planifiées sortent sans traiter la file ni les rappels lorsque le pilote est `log`.

## Retour arrière

Suspendre les écritures et prendre une copie protégée de l’état après incident. Noter la dernière migration enregistrée et la dernière instruction réussie avant toute reprise. Pour revenir à l’état de bascule, utiliser ensemble le commit relevé, la sauvegarde de base et la copie des pièces correspondante. Après réouverture des écritures, réconcilier les nouvelles opérations avant toute restauration. Les scripts SQL de retour du robot visent la répétition et refusent les suppressions lorsqu’ils rencontrent des données de gestion protégées.
