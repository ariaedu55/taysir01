<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Destination;

final class DestinationController extends Controller
{
    /** Liste des pays : /destination/ */
    public function index(): void
    {
        $countries = Destination::all();

        $this->render('pages/destinations', [
            'countries' => $countries,
            'title' => 'Destinations',
            'description' => 'Canada, France, Maroc, Turquie, Chine… Découvrez les pays où Taysir Orientation Voyage accompagne vos études et votre départ.',
            'canonical' => url('/destination/'),
            'heroHeader' => true,
            'styles' => ['css/destination.css', 'css/institutions.css'],
            'scripts' => ['js/destinations.js'],
            'photoCredits' => Destination::photoCredits($countries),
        ]);
    }

    /** Fiche d'un pays, à son adresse WordPress d'origine. */
    public function show(string $slug): void
    {
        $destination = Destination::find($slug) ?? $this->abort(404);
        $guide = config('destination-guides.' . $slug, []);
        $updatedSections = $guide[4] ?? [];
        foreach ($destination['sections'] as $index => $section) {
            $destination['sections'][$index]['html'] = $updatedSections[$section['title']] ?? $section['html'];
        }
        // 3 suggestions : une rangée complète de cartes d’embarquement (docs/cartes-voyage.md)
        $others = Destination::others($destination, 3);

        $this->render('pages/destination', [
            'destination' => $destination,
            'others' => $others,
            'guide' => $guide,
            'title' => 'Partir ' . $destination['in'],
            'description' => Destination::summary($destination),
            'canonical' => url('/' . $slug . '/'),
            'heroHeader' => true,
            'styles' => ['css/destination.css', 'css/institutions.css', 'css/journey.css'],
            'scripts' => ['js/destination.js', 'js/journey.js'],
            'photoCredits' => Destination::photoCredits([$destination, ...$others]),
        ]);
    }
}
