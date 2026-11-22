# Inscription, connexion et espaces protégés

Première brique du portail décrit dans [vision-plateforme.md](vision-plateforme.md).

## Plan (20 novembre 2026)

- Base MySQL `taysir_orientation`, table `users` (et `password_resets` pour la suite), outil de migration relançable.
- Service `Auth`, modèle `User` et middlewares `auth`, `guest` et `role:…` déclarés par route.
- Pages d'inscription et de connexion à l'identité du site, espace client d'accueil et premier tableau de bord du back-office.
- En-tête : « Connexion » pour les visiteurs, « Mon espace » pour les personnes connectées. L'ancien « Mon dossier » WordPress est redirigé vers l'espace client.

## Résultat

### Base de données

- `database/migrations/001_create_users.sql` :
  - table `users` : prénom, nom, e-mail unique, téléphone, profil (`eleve`, `etudiant`, `parent`, `particulier`), rôle (`client`, `conseiller`, `admin`), mot de passe chiffré, date de consentement, vérification d'e-mail, dernière connexion ;
  - table `password_resets` : empreinte SHA-256 du jeton, expiration, clé étrangère vers `users`.
- `database/migrate.php` : crée la base si besoin et applique, dans l'ordre, les fichiers `database/migrations/*.sql` pas encore appliqués (suivi dans la table `migrations`) :

  ```
  php -d extension=pdo_mysql database/migrate.php
  ```

  Sous WAMP en ligne de commande, ajouter `-d extension_dir=C:/wamp64/bin/php/php8.4.15/ext`. Les identifiants viennent de `config/database.php`, ou de `config/database.local.php` s'il existe : ne jamais mettre ceux de production dans le premier.
- La base locale `taysir_orientation` a été créée (MySQL 8.4 de WAMP, compte `root` sans mot de passe).

### Code

| Fichier | Rôle |
| --- | --- |
| `app/Core/Router.php` | `get()` et `post()` acceptent une liste de middlewares, exécutés après la vérification CSRF. |
| `app/Middleware/Authenticate.php` (`auth`) | Personne non connectée : mémorise la page demandée, message « Connectez-vous… », redirection vers `/connexion/`. |
| `app/Middleware/RedirectIfAuthenticated.php` (`guest`) | Personne connectée sur la connexion ou l'inscription : redirection vers `/mon-espace/` (ou `/admin/` pour l'équipe). |
| `app/Middleware/RequireRole.php` (`role:admin,conseiller`) | Rôle insuffisant : 403. |
| `app/Core/Auth.php` | `check()`, `user()` (sans le mot de passe, une lecture par requête), `hasRole()`, `login()` (identifiant de session régénéré), `logout()`, `attempt()` (temps de réponse comparable que le compte existe ou non, rehachage automatique), blocage de 5 minutes après 5 échecs. |
| `app/Models/User.php` | `findByEmail()`, `emailExists()`, `create()` (rôle `client`, `password_hash`, date de consentement), `touchLogin()`, `updatePassword()`, profils proposés. E-mails enregistrés en minuscules. |
| `app/Controllers/AuthController.php` | Formulaires, validation, inscription (connexion automatique), connexion (retour à la page demandée, ou `/admin/` pour l'équipe), déconnexion en POST. |
| `app/Controllers/Client/DashboardController.php` | `/mon-espace/` : accueil, modules, informations du compte. |
| `app/Controllers/Admin/DashboardController.php` | `/admin/` : nombre de clients et dernières inscriptions. |
| `app/Helpers/functions.php` | Ajout de `redirect_to()` (303). |

### Adresses

| Adresse | Accès | Contenu |
| --- | --- | --- |
| `/inscription/` (GET, POST) | Visiteurs | Prénom, nom, e-mail, téléphone ou WhatsApp (facultatif), profil, mot de passe et confirmation, consentement |
| `/connexion/` (GET, POST) | Visiteurs | E-mail et mot de passe ; lien « Mot de passe oublié ? » ([mot-de-passe-oublie.md](mot-de-passe-oublie.md)) |
| `/deconnexion/` (POST) | Connectés | Ferme la session |
| `/mon-espace/` | Connectés | Tableau de bord client |
| `/admin/` | `admin`, `conseiller` | Tableau de bord du back-office ; comptes de l'équipe dans `/admin/equipe/` (`admin` seulement), voir [comptes-equipe.md](comptes-equipe.md) |
| `/suivre-son-dossier/` | Tous | 301 vers `/mon-espace/` (ancienne page WordPress) |

### Interface

- `app/Views/auth/register.php` et `login.php` : écran partagé, avec à gauche une photo de destination (Paris ou Toronto), le logo, une phrase en Playfair et trois avantages en verre ; à droite le formulaire. Sous 62rem, le formulaire seul.
- Fragments : `partials/form-field.php` (libellé, aide, erreur reliée par `aria-describedby`, `aria-invalid`, bouton pour afficher le mot de passe), `partials/flash.php` (messages de succès ou d'information), `partials/auth-aside.php` (panneau visuel).
- `app/Views/client/dashboard.php` : initiales, « Bonjour *Prénom* », profil, modules (Prendre rendez-vous, Faire une demande, et « Bientôt » pour Suivre mes demandes et Mes documents), carte Mon compte, déconnexion.
- `app/Views/admin/dashboard.php` : compteur et tableau des dernières inscriptions.
- `public/assets/css/auth.css`, `public/assets/js/auth.js` (afficher ou masquer le mot de passe). Erreurs en bleu marine avec une pastille rouge : aucun texte rouge.
- En-tête : « Connexion » ou « Mon espace » avec une icône, sur ordinateur. Dans le menu mobile : « Se connecter » et « Créer mon compte », ou « Mon espace ».

### Vérifications (serveur PHP avec `pdo_mysql`, port 8081)

1. `/mon-espace/` sans connexion : 303 vers `/connexion/`, puis retour à `/mon-espace/` après connexion.
2. Inscription vide ou invalide : 6 erreurs affichées et saisies conservées, sauf les mots de passe.
3. Inscription valide : 303 vers `/mon-espace/`, « Bienvenue Awa… », « Bonjour *Awa* ». En base : rôle `client`, hachage `$2y$`, consentement et dernière connexion renseignés.
4. Connecté : `/connexion/` redirige vers l'espace, `/admin/` renvoie 403, l'en-tête affiche « Mon espace ».
5. POST sans jeton : 419. Déconnexion : 303 et message.
6. Adresse déjà utilisée : message dédié. Mauvais mot de passe : message générique. E-mail en majuscules accepté.
7. Après 5 échecs : « Trop de tentatives. Réessayez dans 5 minute(s). » et bouton désactivé, même avec le bon mot de passe.
8. Compte passé `admin` : `/admin/` en 200, et l'en-tête mène à `/admin/`.
9. Rendu vérifié à 1 280 et 375 px, sans débordement. Le compte de test a été supprimé.

### À savoir

- Le serveur de développement doit charger `pdo_mysql`. L’ancien serveur sans extensions du port 8080 a été remplacé, à la demande de l’utilisateur, par celui-ci :

  ```
  C:/wamp64/bin/php/php8.4.15/php.exe -n -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -S 127.0.0.1:8080 -t public
  ```

  Sous Apache/WAMP, `pdo_mysql` est normalement actif.
- `config/app.php` a `debug => true` : en production, le passer à `false` pour ne pas afficher les erreurs.

### Prochaines étapes

1. Réinitialisation du mot de passe par e-mail (table prête), vérification de l'adresse e-mail et envoi d'e-mails (service `app/Services/Mailer`).
2. Rendez-vous en ligne, puis demandes de service (voir [vision-plateforme.md](vision-plateforme.md)). Les modules de l'espace client pointent en attendant vers les formulaires WordPress.
3. Création des comptes `conseiller` et `admin` depuis le back-office. Pour l'instant, il faut changer le rôle directement en base.
4. Profil modifiable, changement de mot de passe et suppression du compte (droits sur les données).
