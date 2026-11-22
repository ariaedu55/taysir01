<?php

declare(strict_types=1);

/*
 * Pages des menus « Offres » et « Activités », à leurs adresses WordPress (docs/offres-activites.md).
 *
 * group     Offres ou Activités (bandeau et liens entre pages sœurs)
 * title     titre de la page
 * hero      illustration dans images/activities et libellé du lien vers la suite
 * lead      phrase d'accroche
 * content   true : texte repris de WordPress dans database/content/activities/<slug>.php
 * notice    encadré d'information (offres de 2024, rubrique sans contenu…)
 * sections  listes d'articles : [titre, types d'articles (App\Models\Article::TYPES), phrase d'introduction]
 * links     cartes de liens : [titre, texte, adresse]
 * video     vidéo externe chargée au clic : [identifiant YouTube, titre, source]
 * aside     encadré latéral : [surtitre, titre, texte, actions [[libellé, adresse, style]]]
 */

$whatsapp = '{whatsapp}';
$account = [
    'Mon espace Taysir',
    'Préparez la suite',
    'Votre compte garde vos demandes, vos documents et vos rendez-vous au même endroit.',
    [['Créer mon compte', '/inscription/', 'primary'], ['Prendre rendez-vous', '/rendez_vous_orientation/', 'light']],
];
$opportunities = [
    ['Formations certifiantes', ['formation'], 'Des certificats courts pour compléter votre profil.'],
    ['Bourses d’études', ['bourse'], 'Des programmes de bourses au Maroc, en Italie, au Canada et au Royaume-Uni.'],
];
$offersNotice = 'Les offres ci-dessous ont été publiées en juin 2024 et sont conservées en archive : elles ne sont sans doute plus ouvertes. Pour être accompagné dans votre recherche, prenez rendez-vous avec un conseiller.';

return [
    [
        'slug' => 'offre_stage', 'group' => 'Offres', 'title' => 'Offres de stage',
        'hero' => ['stages', 'Consulter les offres de stage'],
        'lead' => 'Explorez les annonces de stage relayées par Taysir et les ressources pour préparer votre première expérience professionnelle.',
        'notice' => $offersNotice,
        'sections' => [['Stages', ['offre-stage'], null], ...$opportunities],
        'aside' => $account,
    ],
    [
        'slug' => 'offre-demploie', 'group' => 'Offres', 'title' => 'Offres d’emploi',
        'hero' => ['emplois', 'Consulter les offres d’emploi'],
        'lead' => 'Retrouvez les annonces d’emploi relayées par Taysir au Sénégal et des ressources pour préparer votre candidature.',
        'notice' => $offersNotice,
        'sections' => [['Emplois', ['offre-emploi'], null], ...$opportunities],
        'aside' => $account,
    ],
    [
        'slug' => 'avis-de-recruteurs', 'group' => 'Offres', 'title' => 'Avis de recruteurs',
        'hero' => ['recruteurs', 'Découvrir la rubrique'],
        'lead' => 'La parole aux entreprises qui recrutent des stagiaires et des jeunes diplômés accompagnés par Taysir.',
        'notice' => 'Aucun avis n’est encore publié dans cette rubrique.',
        'links' => [
            ['Offres de stage', 'Les stages relayés par Taysir.', '/offre_stage/'],
            ['Offres d’emploi', 'Les postes à pourvoir.', '/offre-demploie/'],
            ['Fiches métiers', 'Missions, compétences et formations.', '/metiers/'],
        ],
        'aside' => [
            'Vous recrutez ?',
            'Partagez votre expérience',
            'Entreprise, cabinet ou école : écrivez-nous pour publier une offre ou témoigner de votre collaboration avec Taysir.',
            [['Écrire sur WhatsApp', $whatsapp, 'primary'], ['contact@taysir-orientation.com', 'mailto:contact@taysir-orientation.com', 'light']],
        ],
    ],
    [
        'slug' => 'avis-de-professionnels', 'group' => 'Offres', 'title' => 'Avis de professionnels',
        'hero' => ['professionnels', 'Découvrir la rubrique'],
        'lead' => 'Des professionnels racontent leur métier, leur parcours et leurs conseils aux élèves et aux étudiants.',
        'notice' => 'Aucun témoignage n’est encore publié dans cette rubrique.',
        'links' => [
            ['Fiches métiers', 'Découvrir un métier avant de choisir sa filière.', '/metiers/'],
            ['Quel métier ?', 'Trouver le métier qui vous ressemble.', '/quel-metier/'],
            ['Écoles professionnelles', 'BTS, licences et masters.', '/formations-profesionnelles/'],
        ],
        'aside' => [
            'Vous êtes professionnel ?',
            'Inspirez la relève',
            'Médecin, ingénieur, commercial, artisan : partagez votre métier avec les jeunes que Taysir accompagne.',
            [['Écrire sur WhatsApp', $whatsapp, 'primary'], ['contact@taysir-orientation.com', 'mailto:contact@taysir-orientation.com', 'light']],
        ],
    ],
    [
        'slug' => 'parlons-de-voyage', 'group' => 'Activités', 'title' => 'Parlons de voyage',
        'hero' => ['voyage', 'Préparer mon départ'],
        'lead' => 'Visa, billet, logement, hôtel : tout ce qu’il faut préparer avant de partir étudier ou voyager, avec un seul interlocuteur.',
        'links' => [
            ['Demande de visa', 'Dossier consulaire et préparation à l’entretien.', '/demande-de-visa/'],
            ['Billet d’avion', 'Le vol adapté à votre date de rentrée et à votre budget.', '/vente-billet-davion/'],
            ['Logement', 'Résidence, colocation ou studio avant votre arrivée.', '/demande-de-logement/'],
            ['Réservation d’hôtel', 'Pour un séjour, un salon ou vos premiers jours.', '/reservation-hotel/'],
            ['Prise en charge', 'Les garanties financières demandées.', '/demande-de-prise-en-charge/'],
            ['Nos 12 destinations', 'Canada, France, Maroc, Turquie, Chine…', '/destination/'],
        ],
        'aside' => [
            'Un projet de départ ?',
            'Parlons-en',
            'Un conseiller fait le point avec vous sur votre destination, votre calendrier et votre budget.',
            [['Prendre rendez-vous', '/rendez_vous_orientation/?motif=voyage', 'primary'], ['Écrire sur WhatsApp', $whatsapp, 'light']],
        ],
    ],
    [
        'slug' => 'salon-collegien', 'group' => 'Activités', 'title' => 'Salon collégien',
        'hero' => ['salon-collegien', 'Découvrir les forums'],
        'lead' => 'Forums d’orientation et des métiers dans les collèges et les lycées : Taysir va à la rencontre des élèves pour les aider à choisir leur voie.',
        'sections' => [['Nos derniers forums', ['salon'], null]],
        'aside' => [
            'Établissements scolaires',
            'Invitez Taysir',
            'Directeurs, enseignants, parents d’élèves : organisons ensemble un forum d’orientation dans votre établissement.',
            [['Prendre rendez-vous', '/rendez_vous_orientation/?motif=renseignement', 'primary'], ['Écrire sur WhatsApp', $whatsapp, 'light']],
        ],
    ],
    [
        'slug' => 'salons-internationaux', 'group' => 'Activités', 'title' => 'Salons internationaux',
        'hero' => ['salons-internationaux', 'Découvrir les salons'],
        'lead' => 'Études, immigration, insertion professionnelle : les grands rendez-vous internationaux pour préparer un projet à l’étranger.',
        'content' => true,
        'video' => ['phd4V28eeQQ', 'Panel : l’immigration au Québec', 'Vidéo YouTube de la chaîne T. A. C.'],
        'links' => [
            ['Étudier au Canada', 'Universités, coût de la vie, démarches.', '/aller-au-canada/'],
            ['Demande de visa', 'Taysir monte votre dossier consulaire.', '/demande-de-visa/'],
            ['Nos 12 destinations', 'Comparer les pays avant de choisir.', '/destination/'],
        ],
        'aside' => [
            'Projet à l’étranger',
            'Faites le point',
            'Un conseiller vous aide à choisir votre destination et à préparer admission, visa et départ.',
            [['Prendre rendez-vous', '/rendez_vous_orientation/', 'primary'], ['Créer mon compte', '/inscription/', 'light']],
        ],
    ],
    [
        'slug' => 'taysir-club-challenge', 'group' => 'Activités', 'title' => 'Taysir Club Challenge',
        'hero' => ['club-challenge', 'Découvrir le challenge'],
        'lead' => 'Des défis en équipe pour les élèves : communication, créativité et esprit d’équipe, avec des prix à la clé.',
        'content' => true,
        'skills' => ['Communication', 'Collaboration', 'Pensée critique', 'Résolution de problèmes'],
        'aside' => [
            'Élèves et établissements',
            'Rejoindre le club',
            'Inscrivez votre équipe ou votre école aux prochains challenges : écrivez-nous ou prenez rendez-vous.',
            [['Écrire sur WhatsApp', $whatsapp, 'primary'], ['Prendre rendez-vous', '/rendez_vous_orientation/?motif=renseignement', 'light']],
        ],
    ],
];
