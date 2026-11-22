<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Article;

/** Pages des menus « Offres » et « Activités » (config/activities.php). Voir docs/offres-activites.md. */
final class ActivityController extends Controller
{
    /** @return list<array> */
    public static function pages(): array
    {
        return config('activities');
    }

    public function show(string $slug): void
    {
        $pages = self::pages();
        $page = null;
        foreach ($pages as $candidate) {
            if ($candidate['slug'] === $slug) {
                $page = $candidate;
            }
        }
        $page ??= $this->abort(404);

        $intro = '';
        $file = BASE_PATH . '/database/content/activities/' . $slug . '.php';
        if (!empty($page['content']) && is_file($file)) {
            $intro = (require $file)['intro'];
        }

        $sections = [];
        foreach ($page['sections'] ?? [] as [$title, $types, $text]) {
            $articles = Article::byTypes($types);
            if ($articles !== []) {
                $sections[] = ['title' => $title, 'text' => $text, 'articles' => $articles];
            }
        }

        $this->render('pages/activity', [
            'page' => $page,
            'intro' => $intro,
            'sections' => $sections,
            'siblings' => array_values(array_filter($pages, static fn ($other) => $other['group'] === $page['group'] && $other['slug'] !== $slug)),
            'title' => $page['title'],
            'description' => $page['lead'],
            'canonical' => url('/' . $slug . '/'),
            'heroHeader' => true,
            'styles' => ['css/destination.css', 'css/content.css', 'css/portal.css', 'css/institutions.css'],
            'scripts' => ['js/content.js'],
        ]);
    }

    /** Adresse d'une action : « {whatsapp} » renvoie vers le WhatsApp de l'agence. */
    public static function href(string $href): string
    {
        if ($href === '{whatsapp}') {
            return (string) config('site.contact.whatsapp');
        }

        return str_starts_with($href, '/') ? url($href) : $href;
    }
}
