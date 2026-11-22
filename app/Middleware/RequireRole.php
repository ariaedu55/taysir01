<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Router;

/** « role:admin,conseiller » : réservé à ces rôles. À placer après « auth ». */
final class RequireRole
{
    public function handle(?string $parameter = null): void
    {
        $roles = array_filter(array_map('trim', explode(',', (string) $parameter)));
        if (!Auth::hasRole(...$roles)) {
            Router::abort(403);
        }
    }
}
