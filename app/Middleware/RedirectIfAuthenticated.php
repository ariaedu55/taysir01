<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

/** Pages réservées aux visiteurs (connexion, inscription) : une personne connectée va dans son espace. */
final class RedirectIfAuthenticated
{
    public function handle(?string $parameter = null): void
    {
        if (Auth::check()) {
            redirect_to(Auth::hasRole('admin', 'conseiller') ? '/admin/' : '/mon-espace/');
        }
    }
}
