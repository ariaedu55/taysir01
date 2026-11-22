<?php

declare(strict_types=1);

// Informations communes à toutes les pages : en-tête, navigation et pied de page.
return [
    'name' => 'Taysir Orientation Voyage',
    // Ancien site WordPress : cible des liens vers les pages pas encore migrées et source des médias absents en local.
    'legacyUrl' => 'https://taysir-orientation.com',
    'logo' => '2022/07/cropped-LOGO_ORIENTATION_VOYAGE.png',
    'contact' => [
        'address' => 'Rond-point JVC, Dakar',
        'phone' => '+221 77 588 04 04',
        'email' => 'contact@taysir-orientation.com',
        'whatsapp' => 'https://wa.me/221775880404',
    ],
    // Agences (page Contact, rendez-vous). Le numéro de Paris publié sur WordPress était incomplet : non affiché.
    'offices' => [
        ['city' => 'Dakar', 'label' => 'Siège', 'address' => 'Rond-point JVC, Dakar', 'phone' => '+221 77 588 04 04', 'email' => 'contact@taysir-orientation.com'],
        ['city' => 'Ziguinchor', 'label' => 'Agence de Casamance', 'address' => null, 'phone' => '+221 78 718 87 87', 'email' => 'contact.zig@taysir-orientation.com'],
        ['city' => 'Paris', 'label' => 'Relais en France', 'address' => null, 'phone' => null, 'email' => 'paris@taysir-orientation.com'],
    ],
    // Horaires d'ouverture (repris de la page Contact WordPress). Le samedi après-midi, l'agence est fermée.
    'hours' => [
        ['Lundi au vendredi', '9 h – 18 h'],
        ['Samedi', '9 h – 14 h'],
        ['Dimanche', 'Fermé'],
    ],
    // Services de l'agence, dans l'ordre du parcours : [titre, description, adresse].
    'services' => [
        ['Orientation', 'Faire le point sur votre profil et choisir la filière et le pays qui vous correspondent.', '/rendez_vous_orientation/'],
        ['Admission', 'Sélectionner les établissements et constituer un dossier de candidature solide.', '/demande-dadmission/'],
        ['Prise en charge', 'Réunir les justificatifs financiers demandés par l’établissement et le consulat.', '/demande-de-prise-en-charge/'],
        ['Visa', 'Monter le dossier consulaire et préparer l’entretien.', '/demande-de-visa/'],
        ['Logement', 'Trouver un logement avant votre arrivée.', '/demande-de-logement/'],
        ['Billet d’avion', 'Réserver votre vol et préparer le départ.', '/vente-billet-davion/'],
    ],
    'navigation' => [
        ['Après le bac', [['Universités', '/universites/'], ['UCAD', '/ucad/'], ['Université numérique Cheikh Hamidou Kane', '/uvs/'], ['UAM', '/uamb/'], ['Université Iba Der Thiam', '/uidt/'], ['Université Gaston Berger', '/ugb/'], ['Université Alioune Diop', '/uadb/'], ['Université du Sine Saloum', '/usm/'], ['Université Assane Seck', '/uas/'], ['Formations professionnelles', '/formations-profesionnelles/'], ['Métiers', '/metiers/'], ['Conseils des anciens étudiants', '/avis-etudiants/']]],
        ['S’orienter', [['Rendez-vous', '/rendez_vous_orientation/'], ['Quelle filière ?', '/quelle-filiere/'], ['Quel secteur ?', '/quel-secteur/'], ['Quel métier ?', '/quel-metier/']]],
        ['Études à l’étranger', [['Toutes les destinations', '/destination/'], ['Témoignages', '/temoignage/'], ['Admission', '/demande-dadmission/'], ['Logement', '/demande-de-logement/'], ['Visa', '/demande-de-visa/'], ['Prise en charge', '/demande-de-prise-en-charge/'], ['Billets d’avion', '/vente-billet-davion/'], ['Réservation d’hôtel', '/reservation-hotel/'], ['Mon dossier', 'https://gaov.edutaysir.com/']]],
        ['Offres', [['Stages', '/offre_stage/'], ['Emplois', '/offre-demploie/'], ['Avis de recruteurs', '/avis-de-recruteurs/'], ['Avis de professionnels', '/avis-de-professionnels/']]],
        ['Activités', [['Parlons de voyage', '/parlons-de-voyage/'], ['Salon collégien', '/salon-collegien/'], ['Salons internationaux', '/salons-internationaux/'], ['Taysir Club Challenge', '/taysir-club-challenge/']]],
    ],
    'agencyLinks' => [
        ['Qui sommes-nous ?', '/about-us/'],
        ['Contact', '/contact/'],
        ['Questions fréquentes', '/faq/'],
        ['Témoignages', '/temoignage/'],
        ['Nos règles et valeurs', '/nos-regles-et-valeurs/'],
        ['Conditions et confidentialité', '/terms-conditions/'],
    ],
    'network' => [
        ['Marketplace Etaysir', 'https://www.etaysir.com/'],
        ['Taysir Technologies', 'https://taysir-technologies.com/'],
        ['Taysir Studio', 'https://taysir-studio.com/'],
        ['Taysir Académie', 'https://taysir-academie.com/'],
    ],
];
