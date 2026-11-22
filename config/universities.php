<?php

declare(strict_types=1);

/*
 * Registre des universités publiques du Sénégal présentées sur le site. Ordre du menu « Après le bac ».
 *
 * slug   adresse de la page (identique à WordPress, pour le référencement)
 * acronym sigle affiché en grand
 * name   nom complet
 * city   ville ou mode d'enseignement
 * image  photo d'origine dans la médiathèque WordPress (chemin sous wp-content/uploads)
 * former ancien nom, s'il a changé
 *
 * Le contenu (introduction, rubriques, liens de contact) est dans database/content/universities/<slug>.php,
 * produit par database/import/wordpress-universities.php.
 */
return [
    ['slug' => 'ucad', 'acronym' => 'UCAD', 'name' => 'Université Cheikh Anta Diop', 'city' => 'Dakar', 'image' => '2023/03/Image_UCAD.jpg'],
    ['slug' => 'uvs', 'acronym' => 'UN-CHK', 'name' => 'Université numérique Cheikh Hamidou Kane', 'city' => 'Enseignement en ligne', 'image' => '2023/03/Image_UVS-scaled.jpg', 'former' => 'Université virtuelle du Sénégal (UVS)'],
    ['slug' => 'uamb', 'acronym' => 'UAM', 'name' => 'Université Amadou Mahtar Mbow', 'city' => 'Dakar · Diamniadio', 'image' => '2023/03/Image_UAMB.jpg'],
    ['slug' => 'uidt', 'acronym' => 'UIDT', 'name' => 'Université Iba Der Thiam', 'city' => 'Thiès', 'image' => '2023/03/Image_UIDT.jpg'],
    ['slug' => 'ugb', 'acronym' => 'UGB', 'name' => 'Université Gaston Berger', 'city' => 'Saint-Louis', 'image' => '2023/03/Universite_Gaston_Berger_de_Saint_Louis_.jpeg.jpg'],
    ['slug' => 'uadb', 'acronym' => 'UADB', 'name' => 'Université Alioune Diop de Bambey', 'city' => 'Bambey', 'image' => '2023/04/uadb.jpeg'],
    ['slug' => 'usm', 'acronym' => 'USSEIN', 'name' => 'Université du Sine Saloum El Hadj Ibrahima Niass', 'city' => 'Kaolack', 'image' => '2023/03/Image_UEIN.jpg'],
    ['slug' => 'uas', 'acronym' => 'UASZ', 'name' => 'Université Assane Seck de Ziguinchor', 'city' => 'Ziguinchor', 'image' => '2023/03/Image_UASZ.jpg'],
];
