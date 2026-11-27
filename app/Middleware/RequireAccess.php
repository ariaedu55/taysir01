<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Router;
use App\Services\Access;
use App\Services\Audit;

/**
 * « acces:dossiers » : module de l'espace équipe réservé aux postes de la matrice (app/Services/Access.php).
 * À placer après « auth ». Un refus est journalisé (acces_refuse), puis 403.
 */
final class RequireAccess
{
    public function handle(?string $parameter = null): void
    {
        $module = (string) $parameter;
        if (!Access::peut(Auth::user(), $module)) {
            Audit::log('acces_refuse', 'module', $module, ['adresse' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 200)]);
            Router::abort(403);
        }
    }
}
