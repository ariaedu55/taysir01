<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    /**
     * Affiche app/Views/<view>.php avec les variables fournies.
     * Avec un gabarit, la vue est rendue d'abord, puis insérée dans app/Views/layouts/<layout>.php
     * sous la variable $slot. Le gabarit reçoit aussi toutes les variables de la vue.
     */
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        if ($layout === null) {
            self::include($view, $data);
            return;
        }

        ob_start();
        try {
            self::include($view, $data);
        } finally {
            $slot = ob_get_clean();
        }
        self::include('layouts/' . $layout, ['slot' => $slot] + $data);
    }

    private static function include(string $view, array $data): void
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vue introuvable : {$view}");
        }

        (static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        })($file, $data);
    }
}
