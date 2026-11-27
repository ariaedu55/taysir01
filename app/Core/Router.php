<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Router
{
    /** @var array<string, array<string, callable|array{0: class-string, 1: string}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    /** @var array<string, array<string, list<string>>> Middlewares par méthode et par route. */
    private array $middleware = ['GET' => [], 'POST' => []];

    /**
     * Middlewares disponibles. Paramètre éventuel après « : », par exemple « role:admin,conseiller ».
     *  auth  : réservé aux personnes connectées (sinon redirection vers /connexion/)
     *  guest : réservé aux visiteurs non connectés (sinon redirection vers /mon-espace/)
     *  role  : réservé à certains rôles (sinon 403)
     */
    private const MIDDLEWARE = [
        'auth' => \App\Middleware\Authenticate::class,
        'guest' => \App\Middleware\RedirectIfAuthenticated::class,
        'role' => \App\Middleware\RequireRole::class,
        // « acces:dossiers » : module de l'espace équipe, selon la matrice des postes (refus journalisé)
        'acces' => \App\Middleware\RequireAccess::class,
    ];

    private static ?self $current = null;

    public function __construct()
    {
        self::$current = $this;
    }

    /** Routeur de la requête en cours, pour savoir quelles pages existent (voir legacy_url()). */
    public static function current(): ?self
    {
        return self::$current;
    }

    /** Une page GET répond-elle à ce chemin ? */
    public function has(string $path): bool
    {
        return $this->match('GET', $this->normalize((string) parse_url($path, PHP_URL_PATH)))[0] !== null;
    }

    /** Redirection permanente (301), par exemple d'une ancienne adresse WordPress vers la nouvelle. */
    public function redirect(string $from, string $to): void
    {
        $this->get($from, static function () use ($to): never {
            header('Location: ' . url($to), true, 301);
            exit;
        });
    }

    /** @param list<string> $middleware Par exemple ['auth'] ou ['auth', 'role:admin']. */
    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
        $this->middleware['GET'][$this->normalize($path)] = $middleware;
    }

    /** @param list<string> $middleware */
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
        $this->middleware['POST'][$this->normalize($path)] = $middleware;
    }

    /** Sous-dossier d'installation encodé pour une URL, par exemple « /projet%20taysir%20orientation/public » sous WAMP. */
    public static function basePath(): string
    {
        $directory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

        return implode('/', array_map('rawurlencode', explode('/', $directory)));
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $base = rawurldecode(self::basePath());
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_ends_with($path, '/index.php')) {
            $path = substr($path, 0, -strlen('index.php'));
        }

        // Adresses terminées par « / », comme sur l'ancien site WordPress : une seule adresse par page.
        if ($method === 'GET' && !str_ends_with($path, '/') && !str_contains(basename($path), '.')) {
            $query = parse_url($uri, PHP_URL_QUERY);
            header('Location: ' . url($path) . ($query ? '?' . $query : ''), true, 301);
            exit;
        }
        $path = $this->normalize($path);

        [$handler, $params, $route] = $this->match($method, $path);
        if ($handler === null) {
            $allowed = [];
            foreach (array_keys($this->routes) as $other) {
                if ($other !== $method && $this->match($other, $path)[0] !== null) {
                    $allowed[] = $other;
                }
            }
            if ($allowed !== []) {
                header('Allow: ' . implode(', ', $allowed));
                self::abort(405);
            }
            self::abort(404);
        }

        // Tout formulaire POST doit contenir csrf_field().
        if ($method === 'POST' && !Session::verifyCsrf($_POST['_csrf'] ?? null)) {
            self::abort(419);
        }

        // Chaque middleware laisse passer ou interrompt la requête (redirection ou erreur).
        foreach ($this->middleware[$method][$route] ?? [] as $name) {
            [$key, $parameter] = array_pad(explode(':', $name, 2), 2, null);
            $class = self::MIDDLEWARE[$key] ?? throw new RuntimeException("Middleware inconnu : {$key}");
            (new $class())->handle($parameter);
        }

        $this->call($handler, $params);
    }

    public static function abort(int $code): never
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
        View::render('errors/error', ['code' => $code]);
        exit;
    }

    /** @return array{0: callable|array|null, 1: list<string>, 2: string|null} */
    private function match(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = '#^' . preg_replace('/\\\\\{[a-zA-Z_][a-zA-Z0-9_]*\\\\\}/', '([^/]+)', preg_quote($route, '#')) . '$#';
            if (preg_match($pattern, $path, $matches)) {
                return [$handler, array_slice($matches, 1), $route];
            }
        }

        return [null, [], null];
    }

    private function call(callable|array $handler, array $params): void
    {
        if (is_callable($handler) && !is_array($handler)) {
            $handler(...$params);
            return;
        }

        [$class, $action] = $handler;
        if (!class_exists($class) || !method_exists($class, $action)) {
            throw new RuntimeException("Action introuvable : {$class}::{$action}");
        }
        (new $class())->$action(...$params);
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
