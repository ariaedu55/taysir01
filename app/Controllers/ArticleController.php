<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Article;
use App\Models\Sector;

/** Articles, pages secteur, liste des métiers et des écoles. Voir docs/metiers-orientation.md. */
final class ArticleController extends Controller
{
    private const STYLES = ['css/destination.css', 'css/content.css'];

    /** Article à son adresse WordPress d'origine (/medecin/, /unipro-d/…). */
    public function show(string $slug): void
    {
        $article = Article::findBySlug($slug) ?? $this->abort(404);
        $sector = $article['type'] === 'metier' ? Sector::byKey($article['sector']) : null;
        $school = $article['type'] === 'ecole';

        $this->render($school ? 'pages/school' : 'pages/article', [
            'article' => $article,
            'sector' => $sector,
            'related' => Article::related($article),
            'title' => $article['title'],
            'description' => $article['excerpt'] ?: $article['title'],
            'canonical' => url('/' . $slug . '/'),
            'heroHeader' => $school,
            'styles' => $school ? [...self::STYLES, 'css/institutions.css'] : self::STYLES,
            'scripts' => $school ? ['js/destination.js'] : [],
        ]);
    }

    /** Page d'un secteur : ses fiches métiers et les offres. */
    public function sector(string $slug): void
    {
        $sector = Sector::bySlug($slug) ?? $this->abort(404);

        $this->render('pages/sector', [
            'sector' => $sector,
            'careers' => Article::careers($sector['key']),
            'opportunities' => Article::byTypes(Article::OPPORTUNITIES, null, 3),
            'sectors' => Sector::all(),
            'title' => 'Métiers : ' . $sector['name'],
            'description' => 'Les métiers du secteur ' . $sector['name'] . ' : missions, compétences et formations. ' . $sector['description'],
            'canonical' => url('/' . $slug . '/'),
            'styles' => self::STYLES,
        ]);
    }

    /** /metiers/ : toutes les fiches métiers, filtrables par secteur et par recherche. */
    public function careers(): void
    {
        $this->render('pages/careers', [
            'careers' => Article::careers(),
            'sectors' => Sector::all(),
            'title' => 'Fiches métiers',
            'description' => 'Missions, compétences et formations : découvrez les fiches métiers de la santé, du numérique, de l’énergie, de l’immobilier, de l’agriculture et du pétrole.',
            'canonical' => url('/metiers/'),
            'styles' => self::STYLES,
            'scripts' => ['js/content.js'],
        ]);
    }

    /** /formations-profesionnelles/ : écoles de formation professionnelle. */
    public function schools(): void
    {
        $this->render('pages/schools', [
            'schools' => Article::byTypes(['ecole']),
            'title' => 'Écoles de formation professionnelle',
            'description' => 'BTS, licences et masters professionnels : découvrez les écoles partenaires de Taysir Orientation Voyage et leurs filières.',
            'canonical' => url('/formations-profesionnelles/'),
            'heroHeader' => true,
            'styles' => [...self::STYLES, 'css/institutions.css'],
            'scripts' => ['js/content.js'],
        ]);
    }

    /** /blog/ : toutes les publications, filtrables par famille et par recherche. */
    public function blog(): void
    {
        $articles = Article::published();
        $families = [];
        foreach (Article::FAMILIES as $key => [$label, , $page]) {
            $count = count(array_filter($articles, static fn (array $a) => Article::family($a) === $key));
            if ($count > 0) {
                $families[$key] = ['label' => $label, 'count' => $count, 'page' => $page];
            }
        }

        $this->render('pages/blog', [
            'articles' => $articles,
            'families' => $families,
            'title' => 'Publications',
            'description' => 'Fiches métiers, écoles, offres, bourses et salons : toutes les publications de Taysir Orientation Voyage.',
            'canonical' => url('/blog/'),
            'styles' => self::STYLES,
            'scripts' => ['js/content.js'],
        ]);
    }
}
