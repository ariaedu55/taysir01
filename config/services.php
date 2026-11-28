<?php

declare(strict_types=1);

/*
 * Services ouverts aux demandes en ligne (docs/demandes-rendez-vous.md).
 *
 * key        valeur de service_requests.service
 * slug       adresse de la page (identique à WordPress)
 * title      titre de la page et du formulaire
 * short      libellé court (listes, espace client)
 * lead       phrase d'accroche
 * steps      déroulé de l'accompagnement
 * identity   true : numéro de passeport ou CNI proposé (facultatif)
 * documents  true : envoi de documents proposé
 * fields     champs propres au service : [nom, libellé, type, requis, options ou aide]
 *            types : text, date, number, select, country (pays couverts + « Autre pays »), purpose (motifs), textarea
 * purposes   motifs de la démarche (phase 12), seulement quand ils changent les pièces à fournir : clé => libellé
 */

$levels = ['Seconde', 'Première', 'Terminale', 'Bac obtenu', 'Licence 1', 'Licence 2', 'Licence 3', 'Master 1', 'Master 2', 'Formation professionnelle', 'Autre'];

return [
    [
        'key' => 'admission', 'slug' => 'demande-dadmission', 'title' => 'Demande d’admission', 'short' => 'Admission',
        'lead' => 'Taysir crée votre dossier sur les plateformes de candidature des établissements et vous accompagne jusqu’à la réponse.',
        'steps' => ['Étude de votre profil et de vos résultats', 'Choix des établissements et des filières', 'Constitution et dépôt du dossier', 'Suivi jusqu’à la réponse d’admission'],
        'identity' => false, 'documents' => true,
        'purposes' => ['licence' => 'Licence (bachelor)', 'master' => 'Master', 'bts' => 'BTS ou DUT', 'ecole' => 'École d’ingénieurs ou de commerce', 'autre' => 'Autre formation'],
        'fields' => [
            ['destination', 'Pays visé', 'country', true, null],
            ['purpose', 'Niveau visé', 'purpose', true, null],
            ['level', 'Niveau actuel', 'select', true, $levels],
            ['school', 'Établissement actuel', 'text', true, null],
            ['track', 'Filière ou série', 'text', true, null],
            ['wished', 'Formation souhaitée', 'text', false, 'Domaine ou diplôme visé, si vous le savez déjà'],
        ],
    ],
    [
        'key' => 'logement', 'slug' => 'demande-de-logement', 'title' => 'Demande de logement', 'short' => 'Logement',
        'lead' => 'Résidence étudiante, colocation ou studio : Taysir vous aide à trouver un logement avant votre arrivée.',
        'steps' => ['Analyse de votre budget et de votre ville d’études', 'Recherche de logements adaptés', 'Montage du dossier de réservation', 'Préparation de votre arrivée'],
        'identity' => false, 'documents' => true,
        'fields' => [
            ['destination', 'Pays de destination', 'country', true, null],
            ['city', 'Ville de destination', 'text', true, null],
            ['school', 'Établissement qui vous a accepté', 'text', true, null],
            ['arrival', 'Date d’arrivée prévue', 'date', false, null],
            ['budget', 'Budget mensuel', 'text', false, 'Par exemple : 400 € par mois'],
        ],
    ],
    [
        'key' => 'visa', 'slug' => 'demande-de-visa', 'title' => 'Demande de visa', 'short' => 'Visa',
        'lead' => 'Taysir assure toute la démarche, de la constitution du dossier consulaire à la préparation de l’entretien, jusqu’à l’obtention du visa.',
        'steps' => ['Vérification des pièces exigées par le consulat', 'Constitution du dossier et prise de rendez-vous', 'Préparation à l’entretien', 'Suivi jusqu’à la décision'],
        'identity' => true, 'documents' => true,
        'purposes' => ['etudes' => 'Études', 'travail' => 'Travail', 'tourisme' => 'Tourisme', 'visite-familiale' => 'Visite familiale', 'affaires' => 'Affaires'],
        'fields' => [
            ['destination', 'Pays de destination', 'country', true, null],
            ['purpose', 'Type de visa', 'purpose', true, null],
            ['departure', 'Date de départ envisagée', 'date', false, null],
        ],
    ],
    [
        'key' => 'prise-en-charge', 'slug' => 'demande-de-prise-en-charge', 'title' => 'Demande de prise en charge', 'short' => 'Prise en charge',
        'lead' => 'Attestation de prise en charge, justificatifs de ressources : Taysir vous aide à réunir les garanties financières demandées par l’établissement et le consulat.',
        'steps' => ['Point sur les exigences de l’établissement et du consulat', 'Liste des justificatifs à réunir', 'Préparation de l’attestation', 'Vérification du dossier complet'],
        'identity' => false, 'documents' => true,
        'fields' => [
            ['destination', 'Pays de destination', 'country', true, null],
            ['city', 'Ville de destination', 'text', true, null],
            ['school', 'Établissement qui vous a accepté', 'text', true, null],
        ],
    ],
    [
        'key' => 'billet', 'slug' => 'vente-billet-davion', 'title' => 'Billet d’avion', 'short' => 'Billet d’avion',
        'lead' => 'Taysir recherche pour vous le vol le plus adapté à votre date de rentrée et à votre budget.',
        'steps' => ['Recherche des vols selon vos dates', 'Proposition des meilleures options', 'Réservation et émission du billet', 'Conseils pour le jour du départ'],
        'identity' => true, 'documents' => false,
        'fields' => [
            ['from', 'Ville de départ', 'text', true, 'Par exemple : Dakar'],
            ['destination', 'Destination', 'country', true, null],
            ['trip', 'Type de billet', 'select', true, ['Aller simple', 'Aller-retour']],
            ['departure', 'Date de départ', 'date', true, null],
            ['return', 'Date de retour', 'date', false, 'Pour un aller-retour'],
            ['travellers', 'Nombre de voyageurs', 'number', true, null],
        ],
    ],
    [
        'key' => 'hotel', 'slug' => 'reservation-hotel', 'title' => 'Réservation d’hôtel', 'short' => 'Hôtel',
        'lead' => 'Pour un séjour, un salon ou les premiers jours avant votre logement, Taysir réserve votre hôtel.',
        'steps' => ['Recherche selon votre ville et vos dates', 'Proposition d’hôtels', 'Réservation', 'Envoi de la confirmation'],
        'identity' => false, 'documents' => false,
        'fields' => [
            ['destination', 'Pays', 'country', true, null],
            ['city', 'Ville', 'text', true, null],
            ['hotel', 'Hôtel souhaité', 'text', false, 'Si vous en avez déjà un en vue'],
            ['check_in', 'Début du séjour', 'date', true, null],
            ['check_out', 'Fin du séjour', 'date', true, null],
            ['guests', 'Nombre de personnes', 'number', true, null],
        ],
    ],
];
