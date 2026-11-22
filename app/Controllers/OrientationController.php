<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Sector;

/** Pages d'orientation (« Quel secteur ? »…) et page d'aiguillage « Après le bac ». */
final class OrientationController extends Controller
{
    public const PAGES = [
        'quelle-filiere' => ['Quelle filière ?', 'Choisir une formation en accord avec votre profil et préparer une demande d’admission solide.'],
        'quel-secteur' => ['Quel secteur ?', 'Identifier les secteurs qui vous correspondent et qui recrutent.'],
        'quel-metier' => ['Quel métier ?', 'Trouver le métier qui vous ressemble et le parcours pour y arriver.'],
    ];

    public function show(string $slug): void
    {
        [$title, $lead] = self::PAGES[$slug] ?? $this->abort(404);
        $file = BASE_PATH . '/database/content/orientation/' . $slug . '.php';

        $this->render('pages/orientation', [
            'slug' => $slug,
            'pageTitle' => $title,
            'lead' => $lead,
            'intro' => is_file($file) ? (require $file)['intro'] : '',
            'sectors' => Sector::all(),
            'title' => $title,
            'description' => $lead . ' Un conseiller Taysir vous accompagne.',
            'canonical' => url('/' . $slug . '/'),
            'styles' => ['css/destination.css', 'css/content.css'],
        ]);
    }

    public function afterBac(): void
    {
        $this->render('pages/after-bac', [
            'title' => 'Après le bac',
            'description' => 'Universités, écoles de formation professionnelle, métiers, études à l’étranger : toutes les pistes pour préparer votre après-bac avec Taysir.',
            'canonical' => url('/apres-le-bac/'),
            'styles' => ['css/destination.css', 'css/content.css'],
        ]);
    }
}
