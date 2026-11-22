<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /** Affiche une vue dans le gabarit app/Views/layouts/<layout>.php (null pour une vue sans gabarit). */
    protected function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    /** Redirige vers un chemin interne (« /contact ») ou une URL absolue. */
    protected function redirect(string $to, int $status = 302): never
    {
        $location = preg_match('#^https?://#', $to) ? $to : url($to);
        header('Location: ' . $location, true, $status);
        exit;
    }

    /** Revient au formulaire précédent avec les erreurs et les saisies, sauf les mots de passe. */
    protected function back(array $errors = [], string $fallback = '/'): never
    {
        Session::flash('_errors', $errors);
        Session::flash('_old_input', array_diff_key($_POST, array_flip(['password', 'password_confirmation', '_csrf'])));

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $sameHost = $referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        $this->redirect($sameHost ? $referer : $fallback);
    }

    protected function abort(int $code): never
    {
        Router::abort($code);
    }
}
