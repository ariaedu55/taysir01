# Déploiement sur VPS

## Plan

Initialiser le dépôt Git sur `main` et vérifier la destination GitHub. Inclure le code, les médias statiques, les migrations et une copie des articles publics qui permet d’installer le site sur une base neuve. Garder les fichiers locaux de configuration, les exports WordPress bruts et les données d’exploitation hors du dépôt. Documenter l’installation, la mise à jour et la sauvegarde du VPS, puis vérifier une installation propre avant l’envoi.

## Configuration du dépôt GitHub et du déploiement (22 novembre 2026)

Dépôt : `https://github.com/ariaedu55/taysir01` (branche `main`). **Il est public** : tout ce qui est versionné est visible de tous.

### Constat

- Git est initialisé, avec la remote `origin`, mais il n'y a encore aucun commit.
- `.gitignore` exclut déjà :
  - les configurations locales (`config/*.local.php`) ;
  - le contenu de `storage/` (sessions, documents des clients, journaux) ;
  - l'export WordPress brut.
- **`git add` échoue** sur les 9 PNG orphelins (anciens logos et drapeaux) que Windows rend illisibles. Leurs versions WebP sont utilisées à la place.
- **`database/seed.php` attend `database/seeds/articles.json`, qui n'existe pas.** Sur un VPS neuf, les 81 articles (métiers, blog, formations) resteraient vides, car ils viennent de l'export WordPress, qui n'est pas versionné.

### Plan

1. **`.gitignore`** : exclure les 9 PNG orphelins.
2. **`.gitattributes`** : fins de ligne LF (scripts shell compris) et fichiers binaires déclarés (images, polices, PDF).
3. **`database/seeds/articles.json`** : copie des articles publiés depuis la base locale. C'est du contenu public, sans donnée personnelle. Il permet d'installer le site sur une base neuve.
4. **Dossiers de `storage/`** : un `.gitkeep` dans chacun, pour qu'ils existent après un clonage, sans leur contenu.
5. **Fichiers de déploiement** :
   - `deploy/nginx.conf` : racine `public/`, PHP-FPM, `storage/` et fichiers cachés refusés, cache long des images et polices, compression ;
   - `deploy/crontab` : envoi des e-mails en file d'attente ;
   - `bin/deploy.sh` : mise à jour en une commande (`git pull`, migrations, droits, rechargement de PHP-FPM) ;
   - `README.md` : présentation, installation locale, renvoi vers ce guide ;
   - ce guide : installation initiale sur le VPS, mises à jour, sauvegardes.
6. **Audit avant le commit** :
   - aucun fichier de configuration locale, de session, de document client ni d'export ;
   - aucun mot de passe ni jeton ;
   - aucun fichier de plus de 2 Mo.
7. **Commit et envoi** sur `main`.

## Installation initiale sur le VPS (Ubuntu ou Debian, Nginx, PHP 8.4, MySQL)

Les commandes supposent le site dans `/var/www/taysir` et l'utilisateur web `www-data`. Elles sont à adapter si besoin.

1. **Paquets** :
   ```bash
   sudo apt update
   sudo apt install nginx mysql-server git certbot python3-certbot-nginx \
       php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-gd php8.4-curl php8.4-xml
   ```
   PHP 8.4 n'est pas dans les dépôts d'origine de toutes les versions d'Ubuntu ; ajouter au besoin le PPA `ondrej/php`. Les extensions `openssl` et `fileinfo` sont incluses dans PHP.
2. **Base de données** : créer une base `utf8mb4` et un compte dédié, avec un mot de passe fort, que vous choisissez :
   ```sql
   CREATE DATABASE taysir_orientation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'taysir'@'localhost' IDENTIFIED BY '…';
   GRANT ALL PRIVILEGES ON taysir_orientation.* TO 'taysir'@'localhost';
   ```
3. **Code** :
   ```bash
   sudo git clone https://github.com/ariaedu55/taysir01.git /var/www/taysir
   ```
4. **Configuration locale**, jamais versionnée :
   ```bash
   cd /var/www/taysir
   cp config/app.local.example.php config/app.local.php
   cp config/database.local.example.php config/database.local.php
   cp config/mail.local.example.php config/mail.local.php
   ```
   - `app.local.php` : `url` = `https://taysir-orientation.com`, `debug` = `false`.
   - `database.local.php` : le compte créé à l'étape 2.
   - `mail.local.php` : les identifiants SMTP.
   - Protéger ces fichiers : `chmod 640 config/*.local.php && chown root:www-data config/*.local.php`.
5. **Base et contenu** :
   ```bash
   php database/migrate.php
   php database/seed.php
   php bin/create-admin.php
   ```
   `seed.php` charge les 81 articles publics de `database/seeds/articles.json`. `create-admin.php` crée le premier compte administrateur.
6. **Droits** : `bash bin/deploy.sh` prépare `storage/` et vérifie la configuration. Dans le cas d'une première installation, la mise à jour du code ne change rien.
7. **Nginx et HTTPS** : suivre l'en-tête de `deploy/nginx.conf`, puis `certbot --nginx`.
8. **Tâches planifiées** : `sudo crontab -u www-data deploy/crontab`.

## Mise à jour

1. Sur le poste de développement, envoyer le code sur GitHub : `git push origin main`.
2. Sur le VPS :
   ```bash
   cd /var/www/taysir
   bash bin/deploy.sh
   ```
   Le script récupère `main` (avance rapide uniquement), vérifie la présence des fichiers `config/*.local.php`, applique les nouvelles migrations, remet les droits de `storage/` et recharge PHP-FPM.

## À sauvegarder sur le VPS

Ces éléments ne sont pas dans le dépôt :
- la base de données : `mysqldump taysir_orientation | gzip > sauvegarde-$(date +%F).sql.gz` ;
- `storage/uploads/`, qui contient les documents envoyés par les clients ;
- `config/*.local.php`.

## Hors du dépôt, volontairement

| Élément | Raison |
| --- | --- |
| `config/*.local.php` | mots de passe de la base et SMTP |
| `storage/*` (sessions, documents des clients, journaux, cache) | données d'exécution et données personnelles |
| `storage/exports/` (export WordPress, 922 Mo) | source des imports seulement, avec des documents personnels de clients ; à ne jamais mettre en ligne |
| 9 anciens PNG (logos, drapeaux) | remplacés par leur version WebP |

Toutes les images utilisées par le site (`public/assets/images/`, 35,6 Mo en WebP) sont versionnées : elles sont statiques et nécessaires au fonctionnement.
