# Phase 11 — Configuration des notifications

Phase ajoutée le 28/11/2026 à la demande de l’utilisateur, après la mise en service de la gestion (phases 1 à 10).

## Objectif

Configurer les notifications du système pour que chacun soit prévenu au bon moment, sans devoir ouvrir l’application :

- **e-mails**, avec les paramètres SMTP fournis par l’utilisateur ;
- **tâches automatiques** : envoi de la file d’e-mails, rappels de rendez-vous la veille, rappels d’échéances ;
- **notifications push**, dont les éléments seront fournis par l’utilisateur.

## Étape 1 — E-mails et tâches automatiques

1. **L’utilisateur** crée le fichier `/home/jomas/taysir/config/mail.local.php` sur le VPS. Il copie `config/mail.local.example.php` et le remplit : serveur, port, chiffrement, identifiant, mot de passe, adresse d’expédition. Ce fichier n’est jamais versionné.
2. **Ensuite, je** :
   - envoie un e-mail d’essai vers une boîte de test ;
   - contrôle la file `mail_outbox` ;
   - ajoute les deux lignes de [`deploy/crontab-goo-bridge`](../deploy/crontab-goo-bridge) à la crontab de `jomas` : file d’e-mails chaque minute, rappels toutes les quinze minutes. Je sauvegarde la crontab avant ;
   - vérifie un rappel de bout en bout ;
   - crée le premier administrateur (`bin/create-admin.php`), qui reçoit alors son invitation.

## Étape 2 — Notifications push

À préciser avec les éléments fournis par l’utilisateur :

- service choisi ;
- clés du projet ;
- appareils visés (navigateur, téléphone) ;
- événements à notifier ;
- profils concernés (client, 8 postes).

Le plan détaillé sera écrit ici avant tout code :

- décisions ;
- tables ;
- consentement de l’utilisateur ;
- désabonnement ;
- journal ;
- tests.

## État

- 28/11/2026 : phase consignée. Le modèle SMTP est prêt (`config/mail.local.example.php`). En attente des paramètres SMTP, puis des éléments pour les notifications push.
