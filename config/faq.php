<?php

declare(strict_types=1);

/*
 * Questions fréquentes (page /faq/). La FAQ WordPress était le contenu de démonstration du thème :
 * ces réponses sont écrites à partir du fonctionnement réel du site et des informations publiées par l'agence.
 * Pas de tarif ni de garantie inventés. À faire valider par le client (docs/pages-agence.md).
 *
 * [thème, [[question, réponse (texte simple), [libellé du lien, adresse] ou null], …]]
 */

return [
    ['L’accompagnement', [
        [
            'Quels services propose Taysir Orientation Voyage ?',
            'L’orientation (choix de la filière, du métier et du pays), la demande d’admission, la prise en charge financière, la demande de visa, la recherche de logement, la vente de billets d’avion et la réservation d’hôtel. Vous pouvez faire appel à un seul service ou être accompagné du premier rendez-vous jusqu’au départ.',
            ['Voir les destinations', '/destination/'],
        ],
        [
            'Dans quels pays pouvez-vous m’accompagner ?',
            'Taysir accompagne vers 12 destinations : Canada, France, Angleterre, Belgique, Portugal, Russie, Turquie, Maroc, Tunisie, Arabie saoudite, Chine et Japon, ainsi que vers les universités et écoles du Sénégal.',
            ['Comparer les destinations', '/destination/'],
        ],
        [
            'Combien coûte l’accompagnement ?',
            'Les tarifs dépendent du service et de la destination. Un conseiller vous les présente lors du premier échange, avant tout engagement, avec l’ensemble des frais à prévoir.',
            ['Prendre rendez-vous', '/rendez_vous_orientation/'],
        ],
        [
            'Taysir garantit-il l’obtention du visa ?',
            'Non : la décision appartient au consulat du pays de destination. Taysir vous aide à constituer un dossier complet et conforme aux exigences, et vous prépare à l’entretien, pour mettre toutes les chances de votre côté.',
            ['Demande de visa', '/demande-de-visa/'],
        ],
    ]],
    ['Votre espace en ligne', [
        [
            'Faut-il créer un compte ?',
            'Pour faire une demande (admission, visa, logement, prise en charge, billet, hôtel), oui : votre compte garde vos informations et vos documents en sécurité et vous permet de suivre votre dossier. Pour prendre rendez-vous, un compte n’est pas nécessaire.',
            ['Créer mon compte', '/inscription/'],
        ],
        [
            'Comment suivre l’avancement de ma demande ?',
            'Dans votre espace, rubrique « Mes demandes » : chaque demande a une référence, un statut (reçue, en cours, en attente de vos éléments, terminée) et l’historique des messages de votre conseiller. Vous pouvez lui répondre et ajouter des documents.',
            ['Mon espace', '/mon-espace/'],
        ],
        [
            'Quels documents puis-je envoyer, et sont-ils protégés ?',
            'Des fichiers PDF, JPG ou PNG de 5 Mo au maximum : 3 par envoi et 10 par demande. Ils sont conservés hors de la partie publique du site, et seuls vous et l’équipe Taysir pouvez les ouvrir.',
            ['Notre politique de confidentialité', '/terms-conditions/'],
        ],
        [
            'J’ai oublié mon mot de passe : que faire ?',
            'Sur la page de connexion, choisissez « Mot de passe oublié ? » et indiquez votre adresse e-mail : vous recevez un lien, valable une heure, pour en choisir un nouveau. Si vous n’avez plus accès à cette adresse, écrivez-nous sur WhatsApp.',
            ['Réinitialiser mon mot de passe', '/mot-de-passe-oublie/'],
        ],
    ]],
    ['Nous rencontrer', [
        [
            'Où se trouvent vos agences et quand sont-elles ouvertes ?',
            'Le siège est à Dakar, au rond-point JVC, et une agence vous accueille à Ziguinchor. Les agences sont ouvertes du lundi au vendredi de 9 h à 18 h et le samedi de 9 h à 14 h.',
            ['Nous contacter', '/contact/'],
        ],
        [
            'Peut-on échanger à distance ?',
            'Oui : par téléphone, sur WhatsApp ou en visio. Choisissez le mode qui vous convient au moment de prendre rendez-vous.',
            ['Prendre rendez-vous', '/rendez_vous_orientation/'],
        ],
    ]],
];
