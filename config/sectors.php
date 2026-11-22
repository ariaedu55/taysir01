<?php

declare(strict_types=1);

/*
 * Secteurs d'avenir présentés sur le site. Ordre du menu et de l'accueil.
 *
 * key       clé interne (colonne articles.sector)
 * slug      adresse de la page secteur (identique à WordPress)
 * category  catégorie WordPress des fiches métiers de ce secteur
 * name, description  textes affichés (repris de l'accueil)
 * image     photo du secteur (médiathèque WordPress, via media_url), panneaux de l'accueil
 */
return [
    ['key' => 'sante', 'slug' => 'metier-sante', 'category' => 'sante', 'name' => 'Santé', 'description' => 'Soins, santé publique et métiers paramédicaux.', 'image' => '2024/05/medcin.png'],
    ['key' => 'numerique', 'slug' => 'metier-du-numerique', 'category' => 'informatique', 'name' => 'Numérique', 'description' => 'Informatique, données et métiers du web.', 'image' => '2024/05/Design-sans-titre-14.png'],
    ['key' => 'petrole-gaz', 'slug' => 'petrole-et-gaz', 'category' => 'petrole_gaz', 'name' => 'Pétrole et gaz', 'description' => 'Ingénierie et métiers des industries extractives.', 'image' => '2024/05/Ingenieur-Petrolier.png'],
    ['key' => 'immobilier', 'slug' => 'metier-de-limmobilier', 'category' => 'immobilier', 'name' => 'Immobilier', 'description' => 'Construction, gestion et métiers de l’habitat.', 'image' => '2024/05/Ingenieur-en-securite-Safety-Engineer-5.png'],
    ['key' => 'agriculture', 'slug' => 'agriculture-et-agrobusiness', 'category' => 'agriculture_agrobusiness', 'name' => 'Agriculture et agrobusiness', 'description' => 'Production, transformation et gestion agricole.', 'image' => '2024/05/30745389-portrait-agriculteur-cueillette-des-legumes-ai-generatif-photo.jpeg'],
    ['key' => 'energies', 'slug' => 'les-energies-renouvelables', 'category' => 'energie_renouvelable', 'name' => 'Énergies renouvelables', 'description' => 'Solaire et métiers de la transition énergétique.', 'image' => '2024/05/Consultant-en-agro-business-1.png'],
];
