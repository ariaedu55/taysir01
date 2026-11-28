# Phase 3 · Accès tuteur par dossier

## Parcours local

Après vérification hors outil du lien de tutelle et de l’identité du compte, l’administrateur ouvre **Accès tuteurs** depuis le dossier. Sur la fiche personne du parent, il relève l’identifiant du compte. La gestion de l’accès affiche le nom du compte correspondant à cet identifiant. Pour accorder la lecture, l’administrateur confirme la vérification. Il peut aussi autoriser le dépôt pour les pièces attendues. Le compte doit être actif, de rôle client et de profil parent.

Le parent retrouve le dossier dans **Mon espace → Dossiers partagés**. Il peut consulter l’état, l’historique visible côté client, les pièces attendues et les documents du dossier. Le récapitulatif intégral du formulaire reste réservé au titulaire. En mode dépôt, le parent peut envoyer un fichier depuis une pièce attendue éligible, avec les mêmes contrôles que le titulaire. Les droits accordés s’arrêtent à ce dossier. Les rendez-vous, les comptes rendus d’entretien, les notes internes, la messagerie et le dépôt libre relèvent de leurs autorisations existantes.

L’administrateur et le titulaire du dossier peuvent retirer l’accès depuis leurs écrans respectifs. La base contient des dates de naissance facultatives et déclaratives, sans indicateur d’âge confirmé. La règle locale autorise donc tout titulaire de dossier connecté à retirer un accès, quelle que soit la date déclarée. Pour identifier le titulaire, le parcours utilise la personne liée au compte, avec le secours historique par identifiant de compte pour les dossiers sans fiche personne.

## Données et migration

La migration 019 ajoute `request_tutor_access`. Le droit est attaché à l’identifiant du dossier et à l’identifiant du compte parent. L’adresse e-mail n’intervient pas dans la vérification du lien ni dans l’autorisation. La table conserve l’auteur et la date de chaque octroi et retrait. Un nouvel octroi après révocation reçoit un nouvel identifiant. Un compte devenu inactif ou ayant changé de rôle ou de profil perd l’accès tant qu’il ne remplit pas les conditions.

La migration se joue avec `php database/migrate.php`. Le rollback `database/rollback/019_acces_tuteurs.down.sql` retire la table et son entrée dans le registre des migrations quand elle est vide. L’exécuter avec un client SQL qui s’arrête sur erreur, sans `--force`. Dès qu’un octroi existe, même révoqué, le garde-fou arrête le rollback pour conserver la trace.

## Recette ciblée

`tests/Feature/tutor-access.php` utilise des comptes, documents et rendez-vous fictifs. La recette exige une base de recette vide et un serveur PHP qui annonce cette même base. Utiliser une copie de travail temporaire pour isoler également les sessions PHP. Exemple sous PowerShell, avec PHP et ses extensions `pdo_mysql`, `curl`, `mbstring`, `openssl` et `fileinfo` disponibles.

```powershell
$env:TAYSIR_DB = 'taysir_tuteurs_verification_recette'
php database/migrate.php
php -S 127.0.0.1:8087 -t public
```

Depuis un second terminal, dans la même copie, avec la même configuration SQL.

```powershell
$env:TAYSIR_DB = 'taysir_tuteurs_verification_recette'
php tests/Feature/tutor-access.php 8087
```

La recette couvre l’octroi explicite, les critères du compte, le partage dossier par dossier, les téléchargements, les limites du dépôt, les deux modes de révocation, la protection contre les formulaires périmés, le journal et le rollback.

Résultat local du 28 novembre 2026. Les 31 contrôles passent sur MariaDB 11.4.9 et MySQL 8.4.7 avec PHP 8.4.15. La syntaxe des fichiers PHP modifiés passe aussi la vérification. Le rendu visuel sur ordinateur et téléphone reste à contrôler dans un navigateur.
