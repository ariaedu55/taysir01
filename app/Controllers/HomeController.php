<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Article;
use App\Models\Destination;
use App\Models\HomeContent;
use App\Models\Sector;

final class HomeController extends Controller
{
    public function index(): void
    {
        $content = HomeContent::get();

        // Secteurs d'avenir : fiches métiers groupées par secteur (docs/accueil-secteurs.md).
        $careers = [];
        foreach (Article::careers() as $article) {
            $careers[$article['sector']][] = $article;
        }

        $this->render('home', [
            'content' => $content,
            'destinations' => Destination::all(),
            'sectors' => Sector::all(),
            'careers' => $careers,
            'documentTitle' => 'Taysir Orientation Voyage — Études et voyage',
            'description' => 'De l’orientation au premier jour sur le campus : admission, visa, logement et voyage. Taysir accompagne élèves, étudiants et voyageurs au Sénégal et à l’étranger.',
            'canonical' => url('/'),
            'heroHeader' => true,
            'styles' => ['css/home.css', 'css/journey.css'],
            'scripts' => ['js/home.js', 'js/journey.js'],
            'photoCredits' => $content['photoCredits'],
        ]);
    }
}
