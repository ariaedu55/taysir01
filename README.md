# Taysir Orientation Voyage

Site et plateforme de Taysir Orientation Voyage (Dakar) : orientation scolaire, études à l'étranger, visas et voyages.
Il comprend un espace client (demandes, rendez-vous, documents) et un back-office pour l'équipe.

- PHP 8.4 natif, architecture MVC sans framework (`app/`), contrôleur frontal `public/index.php`.
- MySQL / MariaDB (`database/migrations/`), aucune dépendance Composer.
- CSS et JavaScript natifs (`public/assets/`), images en WebP.

## Arborescence

| Dossier | Contenu |
| --- | --- |
| `app/` | noyau MVC, contrôleurs, modèles, services, vues |
| `config/` | configuration (`*.local.php` par environnement, non versionnés) |
| `database/` | migrations, contenus éditoriaux, imports WordPress, articles de départ (`seeds/`) |
| `public/` | seule racine web : `index.php` et les ressources statiques |
| `routes/` | routes du site |
| `bin/` | scripts : administrateur, e-mails, images, déploiement |
| `deploy/` | configuration Nginx et tâches planifiées du VPS |
| `storage/` | sessions, documents des clients, journaux (non versionné) |
| `docs/` | documentation de chaque chantier |

## Installation locale (WAMP)

```bash
php database/migrate.php
php database/seed.php
php bin/create-admin.php
php -S 127.0.0.1:8080 -t public
```

Sous WAMP en ligne de commande, ajouter `-n -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -d extension=fileinfo`.

## Déploiement sur le VPS

Installation et mises à jour : [docs/deploiement-vps.md](docs/deploiement-vps.md). Une mise à jour se fait en une commande sur le serveur :

```bash
bash bin/deploy.sh
```
