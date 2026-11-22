<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\University;

final class UniversityController extends Controller
{
    /** Liste : /universites/ */
    public function index(): void
    {
        $this->render('pages/universities', [
            'universities' => University::all(),
            'title' => 'Universités du Sénégal',
            'description' => 'UCAD, UGB, UAM, UIDT, UN-CHK… Découvrez les universités publiques du Sénégal et préparez votre admission avec Taysir Orientation Voyage.',
            'canonical' => url('/universites/'),
            'heroHeader' => true,
            'styles' => ['css/destination.css', 'css/institutions.css'],
        ]);
    }

    /** Fiche d'une université, à son adresse WordPress d'origine. */
    public function show(string $slug): void
    {
        $university = University::find($slug) ?? $this->abort(404);

        $this->render('pages/university', [
            'university' => $university,
            'others' => University::others($university),
            'title' => $university['name'] . ' (' . $university['acronym'] . ')',
            'description' => University::summary($university),
            'canonical' => url('/' . $slug . '/'),
            'heroHeader' => true,
            'styles' => ['css/destination.css', 'css/institutions.css', 'css/journey.css'],
            'scripts' => ['js/destination.js', 'js/journey.js'],
        ]);
    }
}
