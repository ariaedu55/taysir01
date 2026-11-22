<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Session;

/** Réservé aux personnes connectées. Sinon : retour à la connexion, puis à la page demandée. */
final class Authenticate
{
    public function handle(?string $parameter = null): void
    {
        if (Auth::check()) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            Session::set('_intended', $_SERVER['REQUEST_URI'] ?? '/');
        }
        Session::flash('_notice', 'Connectez-vous pour accéder à votre espace.');
        redirect_to('/connexion/');
    }
}
