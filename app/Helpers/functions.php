<?php

declare(strict_types=1);

use App\Core\Router;
use App\Core\Session;

/** Échappe une valeur pour l'affichage HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Lit une valeur de configuration avec la notation « fichier.clé », par exemple config('app.debug').
 * config/<fichier>.local.php remplace config/<fichier>.php s'il existe.
 */
function config(string $key, mixed $default = null): mixed
{
    static $loaded = [];
    [$file, $path] = array_pad(explode('.', $key, 2), 2, null);

    if (!array_key_exists($file, $loaded)) {
        $local = BASE_PATH . "/config/{$file}.local.php";
        $main = BASE_PATH . "/config/{$file}.php";
        $loaded[$file] = is_file($local) ? require $local : (is_file($main) ? require $main : []);
    }

    $value = $loaded[$file];
    if ($path === null) {
        return $value;
    }
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * URL interne, préfixée du sous-dossier d'installation (par exemple sous WAMP).
 * Les pages se terminent par « / », comme sur l'ancien site : url('/contact') donne « /contact/ ».
 */
function url(string $path = '/'): string
{
    preg_match('/^([^?#]*)(.*)$/s', $path, $parts);
    [, $route, $suffix] = $parts;
    $route = '/' . ltrim($route, '/');
    if (!str_ends_with($route, '/') && !str_contains(basename($route), '.')) {
        $route .= '/';
    }

    return Router::basePath() . $route . $suffix;
}

/**
 * URL absolue (e-mails, tâches planifiées) : config('app.url') suivie du chemin, avec le « / » final des pages.
 * Contrairement à url(), ne dépend pas de la requête en cours.
 */
function absolute_url(string $path = '/'): string
{
    preg_match('/^([^?#]*)(.*)$/s', $path, $parts);
    [, $route, $suffix] = $parts;
    $route = '/' . ltrim($route, '/');
    if (!str_ends_with($route, '/') && !str_contains(basename($route), '.')) {
        $route .= '/';
    }

    return rtrim((string) config('app.url', ''), '/') . $route . $suffix;
}

/** Redirection interne (303 : la page suivante est chargée en GET, même après un formulaire POST). */
function redirect_to(string $path, int $status = 303): never
{
    header('Location: ' . url($path), true, $status);
    exit;
}

/** URL d'un fichier de public/assets/. */
function asset(string $path): string
{
    return Router::basePath() . '/assets/' . ltrim($path, '/');
}

/**
 * Lien vers une page qui existait sur l'ancien site WordPress.
 * Si la page a été migrée (une route GET existe), le lien pointe vers la nouvelle application,
 * sinon vers l'ancien site. Les ancres « #… » visent l'accueil, les adresses externes sont inchangées.
 */
function legacy_url(string $path): string
{
    if (str_starts_with($path, '#')) {
        return url('/') . $path;
    }
    if (!str_starts_with($path, '/')) {
        return $path;
    }

    return Router::current()?->has($path) ? url($path) : config('site.legacyUrl') . $path;
}

/** Média de la médiathèque WordPress : copie locale dans public/assets/images/wordpress/ si elle existe, sinon l'URL d'origine. */
function media_url(string $path): string
{
    static $manifest = null;
    $manifest ??= is_file(BASE_PATH . '/database/content/media-manifest.php')
        ? require BASE_PATH . '/database/content/media-manifest.php'
        : [];
    $encode = static fn (string $value): string => implode('/', array_map('rawurlencode', explode('/', $value)));

    // 1. Version optimisée publiée par database/import/publish-media.php (WebP pour les images).
    if (isset($manifest[$path])) {
        return asset($encode($manifest[$path]));
    }
    // 2. Copie locale à l'identique (premiers médias de l'accueil).
    if (is_file(BASE_PATH . '/public/assets/images/wordpress/' . $path)) {
        return asset('images/wordpress/' . $encode($path));
    }

    // 3. En dernier recours, l'ancien site.
    return config('site.legacyUrl') . '/wp-content/uploads/' . $encode($path);
}

/** Inclut app/Views/partials/<name>.php avec ses propres variables. */
function partial(string $name, array $data = []): void
{
    App\Core\View::render('partials/' . $name, $data);
}

/** Champ caché contenant le jeton CSRF, à placer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrfToken()) . '">';
}

/** Valeur saisie avant la dernière redirection, pour pré-remplir un formulaire. */
function old(string $field, string $default = ''): string
{
    $old = Session::get('_old_input', []);

    return is_array($old) && isset($old[$field]) && is_string($old[$field]) ? $old[$field] : $default;
}

/** Erreurs de validation renvoyées par Controller::back(). */
function errors(): array
{
    $errors = Session::get('_errors', []);

    return is_array($errors) ? $errors : [];
}
