<?php

declare(strict_types=1);

/*
 * Registre des fiches pays. L'ordre est celui de l'affichage.
 *
 * slug      adresse de la page (celle de WordPress, ou celle de ses liens internes) pour garder le référencement
 * wordpress adresse de la page dans l'export WordPress, si elle diffère de slug
 * aliases   autres adresses, redirigées en 301 vers slug
 * in        nom précédé de sa préposition (« au Canada »)
 * photo     photo locale de public/assets/images/destinations/<photo>.webp (hero/<ville> : visuels du bandeau,
 *           avec une version <photo>-portrait.webp pour les formats hauts), sinon l'image de la page WordPress
 * city      ville de la photo, pour retrouver son crédit dans config/home.php (null : image reprise du site WordPress)
 * guidePhoto panorama de l’annuaire et de la fiche lorsque l’accueil utilise un autre visuel
 * airport   aéroport principal [code IATA, ville, latitude, longitude] : route, distance et direction depuis Dakar
 *           (cartes d'embarquement et radar de /destination/, docs/cartes-voyage.md)
 * focus     position horizontale du sujet dans un visuel panoramique (object-position), ex. '23%'
 * flag      drapeau local public/assets/images/destinations/<flag>.webp, null s'il manque
 *
 * Le contenu (introduction, rubriques) est dans database/content/destinations/<slug>.php,
 * produit par database/import/wordpress-destinations.php.
 */
return [
    ['slug' => 'aller-au-canada', 'name' => 'Canada', 'in' => 'au Canada', 'region' => 'Amérique du Nord', 'photo' => 'hero/calgary', 'city' => 'Calgary', 'flag' => 'ca', 'focus' => '73%', 'airport' => ['YUL', 'Montréal', 45.47, -73.74]],
    ['slug' => 'aller-en-france', 'wordpress' => 'aller-en-en-france', 'aliases' => ['aller-en-en-france'], 'name' => 'France', 'in' => 'en France', 'region' => 'Europe', 'photo' => 'hero/paris', 'city' => 'Paris', 'flag' => 'fr', 'focus' => '23%', 'airport' => ['CDG', 'Paris', 49.01, 2.55]],
    ['slug' => 'aller-en-angleterre', 'name' => 'Angleterre', 'in' => 'en Angleterre', 'region' => 'Europe', 'photo' => 'hero/londres', 'city' => 'Londres', 'flag' => 'gb', 'focus' => '73%', 'airport' => ['LHR', 'Londres', 51.47, -0.45]],
    ['slug' => 'aller-au-belgique', 'name' => 'Belgique', 'in' => 'en Belgique', 'region' => 'Europe', 'photo' => 'belgique', 'guidePhoto' => 'guides/bruxelles', 'city' => null, 'flag' => null, 'airport' => ['BRU', 'Bruxelles', 50.90, 4.48]],
    ['slug' => 'portugal', 'name' => 'Portugal', 'in' => 'au Portugal', 'region' => 'Europe', 'photo' => 'portugal', 'guidePhoto' => 'guides/lisbonne', 'city' => null, 'flag' => null, 'airport' => ['LIS', 'Lisbonne', 38.77, -9.13]],
    ['slug' => 'aller-au-russie', 'name' => 'Russie', 'in' => 'en Russie', 'region' => 'Europe', 'photo' => 'russie', 'guidePhoto' => 'guides/moscou', 'city' => null, 'flag' => null, 'airport' => ['SVO', 'Moscou', 55.97, 37.41]],
    ['slug' => 'aller-en-turquie', 'name' => 'Turquie', 'in' => 'en Turquie', 'region' => 'Europe', 'photo' => 'hero/istanbul', 'city' => 'Istanbul', 'flag' => 'tr', 'focus' => '73%', 'airport' => ['IST', 'Istanbul', 41.26, 28.74]],
    ['slug' => 'aller-en-maroc', 'name' => 'Maroc', 'in' => 'au Maroc', 'region' => 'Afrique du Nord', 'photo' => 'hero/marrakech', 'city' => 'Marrakech', 'flag' => 'ma', 'focus' => '23%', 'airport' => ['CMN', 'Casablanca', 33.37, -7.59]],
    ['slug' => 'aller-en-tunisie', 'name' => 'Tunisie', 'in' => 'en Tunisie', 'region' => 'Afrique du Nord', 'photo' => 'hero/tunis', 'city' => 'Tunis', 'flag' => 'tn', 'focus' => '23%', 'airport' => ['TUN', 'Tunis', 36.85, 10.23]],
    ['slug' => 'aller-en-arabie-saoudite', 'name' => 'Arabie saoudite', 'in' => 'en Arabie saoudite', 'region' => 'Moyen-Orient', 'photo' => 'arabie-saoudite', 'guidePhoto' => 'guides/riyad', 'city' => null, 'flag' => null, 'airport' => ['JED', 'Djeddah', 21.68, 39.16]],
    ['slug' => 'aller-en-chine', 'name' => 'Chine', 'in' => 'en Chine', 'region' => 'Asie', 'photo' => 'chine', 'guidePhoto' => 'guides/shanghai', 'city' => null, 'flag' => null, 'airport' => ['PEK', 'Pékin', 40.08, 116.58]],
    ['slug' => 'japon-2', 'aliases' => ['japon'], 'name' => 'Japon', 'in' => 'au Japon', 'region' => 'Asie', 'photo' => 'japon', 'guidePhoto' => 'guides/tokyo', 'city' => null, 'flag' => null, 'airport' => ['HND', 'Tokyo', 35.55, 139.78]],
];
