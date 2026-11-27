<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\Audit;
use App\Services\Coquille;

/** Base des pages de l'espace équipe réel : rendu dans la coquille (layouts/espace.php) et refus journalisé. */
abstract class EspaceController extends Controller
{
    /** Page de l'espace équipe. $actif : module mis en avant dans la navigation. */
    protected function page(string $view, string $actif, string $title, array $data = []): void
    {
        require_once BASE_PATH . '/app/Views/gestion/icones.php';
        require_once BASE_PATH . '/app/Views/gestion/outils.php';
        $this->render('gestion/' . $view, $data + [
            'title' => $title,
            'me' => Auth::user(),
            'coquille' => Coquille::pour(Auth::user(), $actif),
        ], 'espace');
    }

    /** Accès hors périmètre : journalisé, puis 403 (jamais de 404 qui masquerait une tentative). */
    protected function refuser(string $type, string $ref, string $motif): never
    {
        Audit::log('acces_refuse', $type, $ref, ['motif' => $motif, 'adresse' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 200)]);
        $this->abort(403);
    }

    protected function filter(string $key): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
