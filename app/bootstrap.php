<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Helpers/functions.php';
require BASE_PATH . '/app/Helpers/env.php';
load_env(BASE_PATH . '/.env');

date_default_timezone_set((string) config('app.timezone', 'UTC'));

$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($debug): void {
    error_log((string) $exception);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre>' . e((string) $exception) . '</pre>';
        return;
    }
    App\Core\View::render('errors/error', ['code' => 500]);
});

// Pas de session pour les scripts en ligne de commande (bin/).
if (PHP_SAPI !== 'cli') {
    App\Core\Session::start();
}
