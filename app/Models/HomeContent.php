<?php

declare(strict_types=1);

namespace App\Models;

final class HomeContent
{
    /** Contenu éditorial de l'accueil (config/home.php). Les informations communes sont dans config/site.php. */
    public static function get(): array
    {
        return config('home');
    }
}
