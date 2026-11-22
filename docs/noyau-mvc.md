# Noyau MVC et routeur

## Plan (20 novembre 2026)

Remplacer l'appel direct à `HomeController` dans `public/index.php` par un point d'entrée unique avec routeur, en PHP natif sans Composer.

- `app/bootstrap.php` : chargement automatique des classes de l'espace de noms `App\` depuis `app/`, configuration, fuseau horaire, gestion des erreurs et session.
- `app/Core/` :
  - `Router` : routes GET et POST, paramètres `{id}`, prise en compte du sous-dossier WAMP, réponses 404 et 405 ;
  - `Controller` : méthodes de base `render`, `redirect` et `abort` ;
  - `View` : rendu d'une vue PHP avec ses variables ;
  - `Database` : connexion PDO unique, ouverte au premier usage ;
  - `Model` : classe de base des modèles, avec accès à PDO ;
  - `Session` : démarrage sécurisé, messages flash et jeton CSRF.
- `app/Helpers/functions.php` : `e()`, `url()`, `asset()`, `config()`, `csrf_field()`, `old()`.
- `config/app.php` et `config/database.php`.
- `routes/web.php` : déclaration de la route `/`.
- `app/Views/errors/404.php`, `405.php` et `500.php`.
- `public/.htaccess` : réécriture des URL vers `index.php`.
- Passer `HomeController` et `HomeContent` dans les espaces de noms `App\Controllers` et `App\Models`, sans modifier les vues de l'accueil.
- Vérifier la syntaxe, l'accueil, une page 404 et le serveur PHP local.

## Résultat

Toutes les requêtes passent désormais par `public/index.php`, qui charge `app/bootstrap.php`, déclare les routes de `routes/web.php` puis appelle `Router::dispatch()`.

| Fichier | Rôle |
| --- | --- |
| `app/bootstrap.php` | Constante `BASE_PATH`, chargement automatique `App\` → `app/`, helpers, fuseau `Africa/Dakar`, erreurs converties en exceptions, journal `storage/logs/php-error.log`, démarrage de la session. |
| `app/Core/Router.php` | `get()` et `post()`, paramètres `{nom}` passés à l'action, gestion du sous-dossier WAMP, 404, 405 avec en-tête `Allow`, **vérification CSRF automatique sur tout POST** (419 si absent ou faux), `Router::abort($code)`. |
| `app/Core/Controller.php` | `render()`, `redirect()`, `back($errors)` (renvoie les erreurs et les saisies, sauf les mots de passe), `abort()`. |
| `app/Core/View.php` | Affiche `app/Views/<vue>.php` avec ses variables. |
| `app/Core/Database.php` | Connexion PDO MySQL unique, ouverte au premier usage, avec exceptions et requêtes préparées natives. |
| `app/Core/Model.php` | Base des modèles : `$table`, `query()` préparée, `find()`, `all()`. |
| `app/Core/Session.php` | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS, sessions dans `storage/sessions/`, messages flash, `regenerate()`, `destroy()`, jeton CSRF. |
| `app/Helpers/functions.php` | `e()`, `config('fichier.clé')`, `url()`, `asset()`, `csrf_field()`, `old()`, `errors()`. |
| `config/app.php`, `config/database.php` | Configuration. Un fichier `config/<nom>.local.php` remplace `config/<nom>.php` s'il existe, pour les identifiants de production. |
| `app/Views/errors/error.php` | Page d'erreur unique pour 403, 404, 405, 419 et 500, aux couleurs de [DESIGN.md](DESIGN.md). Elle remplace les trois fichiers prévus dans le plan. |
| `public/.htaccess` | Réécriture vers `index.php` et désactivation du listage des dossiers. |

`HomeController` hérite de `App\Core\Controller` et `HomeContent` lit `config('home')`. Les vues de l'accueil n'ont pas changé.

### Conventions pour la suite

- Une classe par fichier, avec un espace de noms qui suit le dossier : `App\Controllers\Admin\DossierController` dans `app/Controllers/Admin/DossierController.php`.
- Chaque formulaire POST contient `<?= csrf_field() ?>`. Tout affichage d'une donnée passe par `e()`.
- Les liens internes utilisent `url('/chemin')` et les fichiers statiques `asset('css/…')`, pour fonctionner aussi dans un sous-dossier WAMP.
- Les requêtes SQL passent les valeurs en paramètres de `query()`, jamais par concaténation.

### Vérifications

- `php -l` sans erreur sur tous les fichiers PHP (PHP 8.4.15).
- Serveur local `php -S 127.0.0.1:8080 -t public` : `/` et `/index.php` répondent 200, l'accueil s'affiche comme avant dans le navigateur, `/page-inexistante` renvoie la page 404, `home.css` est servi, un POST sur `/` renvoie 405.
- Script de test avec un `SCRIPT_NAME` de sous-dossier WAMP : paramètres `/dossiers/42/documents/7` → `42,7`, `url()` et `asset()` préfixés par `/projet%20taysir%20orientation/public`, POST avec jeton valide accepté, jeton faux → page 419.
- Correction pendant les tests : `Router::abort()` n'appelle plus `http_response_code()` si l'en-tête est déjà envoyé.
- Non testés : la connexion MySQL (aucune base n'existe encore) et le passage par Apache/WAMP avec `.htaccess`.

### Points restant à traiter

- Fait, voir [gabarit-partials.md](gabarit-partials.md). `app/Views/layouts/main.php` était propre à l’accueil : il inclut `home.php` en dur et utilise des chemins `assets/…` relatifs, qui ne fonctionneront pas sur des URL comme `/etudes/canada`. Le découper en `partials/` (en-tête, pied de page) et passer la vue de contenu en paramètre avant de créer d'autres pages.
- Aucun middleware d'authentification ou de rôle pour l'instant. Le routeur devra accepter un middleware par route quand l'espace client et l'administration seront créés.
- Créer la base `taysir_orientation` et les premières migrations dans `database/migrations/`.
- Passer `debug` à `false` dans `config/app.php` en production.
