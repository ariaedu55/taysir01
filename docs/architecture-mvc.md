# Architecture MVC complète

## Plan (20 novembre 2026)

Compléter l'arborescence créée lors de l'[initialisation](initialisation.md) pour accueillir tout le code de l'application en PHP natif, CSS et JavaScript, sans framework.

État constaté avant l'intervention :

- présents : `app/Controllers`, `app/Models`, `app/Views` (avec `layouts/` et `partials/`), `app/Core`, `app/Services`, `app/Middleware`, `config/`, `routes/`, `database/migrations`, `database/seeds`, `public/assets/{css,js,images}`, `storage/{logs,uploads}`, `tests/`, `docs/` ;
- l'accueil fonctionne déjà (`HomeController`, `HomeContent`, `home.php`, `layouts/main.php`) ;
- les dossiers `Core`, `Middleware`, `Services`, `routes`, `database/*`, `storage/*`, `tests` et `Views/partials` sont vides ;
- aucun emplacement n'est prévu pour l'administration, l'espace client, l'authentification, les pages de contenu, les erreurs, les e-mails, les fonctions utilitaires, le cache, les sessions ou les exports privés.

Dossiers à ajouter :

- `app/Controllers/Admin/` et `app/Controllers/Client/` ;
- `app/Helpers/` ;
- `app/Views/{pages,auth,client,admin,errors,emails}/` ;
- `public/assets/fonts/` ;
- `storage/{cache,sessions,exports}/` ;
- `tests/{Unit,Feature}/`.

Chaque dossier vide recevra un fichier `.gitkeep` pour être conservé dans le futur dépôt Git.

## Résultat

Les dossiers prévus ont été créés et vérifiés. Aucun fichier existant n'a été modifié.

```
app/
├── Controllers/        Contrôleurs publics (HomeController existe)
│   ├── Admin/          Back-office : dossiers, clients, paiements, universités
│   └── Client/         Espace client : suivi du dossier, documents
├── Core/               Routeur, Controller et Model de base, connexion PDO, session, requête
├── Helpers/            Fonctions utilitaires : échappement, URL, CSRF, formatage
├── Middleware/         Contrôles d'accès : authentification, rôles, CSRF
├── Models/             Données métier et requêtes SQL (HomeContent existe)
├── Services/           Traitements partagés : e-mails, envoi de fichiers, paiements
└── Views/
    ├── layouts/        Gabarits (main.php existe)
    ├── partials/       Fragments réutilisables : en-tête, pied de page, alertes
    ├── pages/          Pages publiques : études, voyage, ressources, à propos, contact
    ├── auth/           Connexion, inscription, mot de passe oublié
    ├── client/         Vues de l'espace client
    ├── admin/          Vues du back-office
    ├── errors/         Pages 404, 403 et 500
    └── emails/         Modèles d'e-mails
config/                 Configuration (home.php existe)
routes/                 Déclaration des routes (web.php, admin.php à venir)
database/
├── migrations/         Scripts SQL de création et d'évolution du schéma
└── seeds/              Données d'initialisation et comptes de test
public/                 Racine web Apache (index.php existe)
└── assets/{css,js,images,fonts}
storage/                Privé, hors racine web
├── cache/  sessions/  logs/
├── uploads/            Pièces des dossiers clients
└── exports/            Exports privés, dont les exports WordPress
tests/{Unit,Feature}/
docs/                   Documentation et historique des travaux
```

Les dossiers vides contiennent un fichier `.gitkeep`.

## Points restant à traiter

- `public/index.php` charge directement `HomeController`. Le routeur (`app/Core/`) et le fichier `routes/web.php` restent à écrire. L'accueil devra ensuite passer par eux.
- Fait le 20 novembre 2026, avec l'accord de l'utilisateur : l'export `taysirorientationvoyage.WordPress.2026-11-20.xml`, qui peut contenir des données personnelles, a été déplacé de la racine vers `storage/exports/`.
- Configurer Apache (WAMP) avec `public/` comme racine web, pour garder `storage/` inaccessible en HTTP.
