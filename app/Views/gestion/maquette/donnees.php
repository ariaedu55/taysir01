<?php

declare(strict_types=1);

/*
 * Données FICTIVES de la maquette de l'espace équipe (docs/gestion-phase-1.md, étape 1A).
 * Noms, références, montants et dates sont inventés. Rien n'est lu ni écrit en base.
 */

$jour = new DateTimeImmutable('now', new DateTimeZone('Africa/Dakar'));
$d = static fn (string $modif): string => $jour->modify($modif)->format('Y-m-d');

// Équipe fictive : un membre par poste
$equipe = [
    'admin' => ['Mariama', 'Sall', 'Administratrice'],
    'secretaire' => ['Aïssatou', 'Ndiaye', 'Secrétaire'],
    'prospection' => ['Ibrahima', 'Faye', 'Agent de prospection'],
    'commerciale' => ['Khady', 'Diouf', 'Commerciale'],
    'conseiller' => ['Moussa', 'Diop', 'Conseiller'],
    'orientation' => ['Fatou', 'Ba', 'Agente d’orientation'],
    'traitement' => ['Cheikh', 'Gueye', 'Agent de traitement'],
    'comptable' => ['Ndèye', 'Sarr', 'Comptable'],
];

// Navigation : groupes et entrées par poste. [libellé, icône, page de la maquette (ou null), compteur]
$nav = [
    'admin' => [
        ['', [['Ma journée', 'sun', 'journee', null], ['Tableau de bord', 'chart', null, null]]],
        ['Relation client', [['Personnes', 'users', null, null], ['Agenda', 'calendar', null, 6], ['Dossiers', 'folder', 'dossiers', 42]]],
        ['Prospection', [['Prospects', 'target', 'prospects', 18], ['Campagnes', 'flag', null, null]]],
        ['Finance', [['À encaisser', 'wallet', null, 5], ['Dépenses', 'receipt', null, null]]],
        ['Paramètres', [['Équipe', 'team', null, null], ['Agences', 'building', null, null], ['Services et tarifs', 'tag', null, null], ['Modèles de procédure', 'route', null, 2]]],
    ],
    'secretaire' => [
        ['', [['Ma journée', 'sun', 'journee', null]]],
        ['Accueil', [['File d’attente', 'queue', null, 3], ['Agenda', 'calendar', null, 9], ['Demandes de rendez-vous', 'inbox', 'journee', 4]]],
        ['Relation client', [['Personnes', 'users', null, null], ['Dossiers', 'folder', 'dossiers', null]]],
    ],
    'prospection' => [
        ['', [['Ma journée', 'sun', 'journee', null]]],
        ['Prospection', [['Prospects', 'target', 'prospects', 18], ['Établissements', 'building', null, null], ['Campagnes', 'flag', null, null], ['Imports', 'upload', null, null]]],
    ],
    'commerciale' => [
        ['', [['Ma journée', 'sun', 'journee', null]]],
        ['Prospection', [['Mes prospects', 'target', 'prospects', 12], ['Rappels', 'phone', null, 7]]],
        ['Relation client', [['Agenda', 'calendar', null, 3]]],
    ],
    'conseiller' => [
        ['', [['Ma journée', 'sun', 'journee', null]]],
        ['Mes clients', [['Personnes', 'users', null, null], ['Dossiers', 'folder', 'dossiers', 14], ['Agenda', 'calendar', null, 4], ['Entretiens', 'chat', null, null]]],
    ],
    'orientation' => [
        ['', [['Ma journée', 'sun', 'journee', null]]],
        ['Orientation', [['À orienter', 'compass', 'dossiers', 5], ['Orientés', 'check', 'dossiers', null], ['Agenda', 'calendar', null, 2]]],
    ],
    'traitement' => [
        ['', [['Ma journée', 'sun', 'journee', null]]],
        ['Dossiers', [['Mes dossiers', 'folder', 'dossiers', 23], ['Pièces à valider', 'file', 'dossiers', 3], ['Échéances', 'clock', null, 4]]],
    ],
    'comptable' => [
        ['', [['Ma journée', 'sun', 'journee', null]]],
        ['Finance', [['À encaisser', 'wallet', null, 5], ['Règlements', 'receipt', null, null], ['Devis', 'doc', null, 2], ['Dépenses', 'cart', null, null], ['Rapports', 'chart', null, null]]],
        ['Relation client', [['Dossiers', 'folder', 'dossiers', null]]],
    ],
];

// Dossiers fictifs : [référence, prénom nom, parcours, service, destination, rentrée, étape (0-5), statut, échéance (jours), responsable (poste), agence]
$dossiers = [
    ['TAY-2027-0142', 'Awa Sène', 'etudes', 'Admission et visa études', 'France', 'Sept. 2027', 3, 'piece', 2, 'traitement'],
    ['TAY-2027-0139', 'Mamadou Kane', 'etudes', 'Admission', 'Canada', 'Janv. 2028', 2, 'orientation', 9, 'orientation'],
    ['TAY-2027-0137', 'Seynabou Mbaye', 'etudes', 'Visa études', 'Maroc', 'Sept. 2027', 4, 'attente', 15, 'traitement'],
    ['TAY-2027-0131', 'Ousmane Fall', 'voyage', 'Visa tourisme', 'Turquie', '—', 2, 'cours', 6, 'traitement'],
    ['TAY-2027-0128', 'Rokhaya Thiam', 'etudes', 'Admission', 'Belgique', 'Sept. 2027', 1, 'nouveau', 21, 'conseiller'],
    ['TAY-2027-0125', 'Abdou Karim Cissé', 'etudes', 'Admission et visa études', 'Canada', 'Sept. 2027', 3, 'retard', -3, 'traitement'],
    ['TAY-2027-0121', 'Coumba Diallo', 'voyage', 'Billet et hôtel', 'Arabie saoudite', '—', 3, 'paiement', 4, 'comptable'],
    ['TAY-2027-0118', 'Pape Samba Ndour', 'etudes', 'Logement', 'France', 'Sept. 2027', 4, 'cours', 30, 'traitement'],
    ['TAY-2027-0112', 'Astou Gaye', 'etudes', 'Admission', 'Tunisie', 'Oct. 2027', 5, 'admis', null, 'traitement'],
    ['TAY-2027-0107', 'Lamine Sy', 'etudes', 'Orientation', 'À définir', 'Sept. 2027', 1, 'orientation', 12, 'orientation'],
];
$statuts = [
    'nouveau' => ['Nouveau', 'neutre'],
    'orientation' => ['À orienter', 'info'],
    'cours' => ['En cours', 'info'],
    'piece' => ['Pièce à valider', 'alerte'],
    'attente' => ['Attente établissement', 'neutre'],
    'retard' => ['En retard', 'urgent'],
    'paiement' => ['Paiement dû', 'alerte'],
    'admis' => ['Admis', 'succes'],
];
$etapes = [
    'etudes' => ['Contact', 'Entretien', 'Orientation', 'Dossier', 'Visa', 'Départ'],
    'voyage' => ['Contact', 'Devis', 'Visa', 'Billet et hôtel', 'Assurance', 'Départ'],
];

// Prospects fictifs : [nom, établissement, niveau, téléphone, campagne, commerciale attribuée]
$prospects = [
    ['Adja Mbengue', 'Lycée Lamine Guèye', 'Terminale S2', '77 410 22 31', 'Rentrée 2027', null],
    ['Babacar Diagne', 'Lycée Lamine Guèye', 'Terminale L', '76 300 18 44', 'Rentrée 2027', null],
    ['Dior Faye', 'Lycée Seydina Limamou Laye', 'Terminale S1', '78 112 09 65', 'Rentrée 2027', null],
    ['El Hadji Ndiaye', 'Lycée Seydina Limamou Laye', 'Terminale S2', '77 908 41 10', 'Rentrée 2027', null],
    ['Fama Seck', 'Lycée Blaise Diagne', 'Terminale G', '70 655 32 87', 'Salon de l’étudiant', 'Khady Diouf'],
    ['Gora Wade', 'Lycée Blaise Diagne', 'Terminale S2', '77 221 76 03', 'Salon de l’étudiant', null],
    ['Haby Sow', 'Lycée Kennedy', 'Terminale L', '76 487 50 19', 'Rentrée 2027', 'Awa Niang'],
    ['Ismaïla Touré', 'Lycée Kennedy', 'Terminale S1', '78 640 13 72', 'Rentrée 2027', null],
];

// Recherche (Ctrl+K)
$recherche = [];
foreach ($dossiers as $dossier) {
    $recherche[] = ['type' => 'Dossier', 'titre' => $dossier[1] . ' · ' . $dossier[3], 'detail' => $dossier[0] . ' · ' . $dossier[4], 'page' => 'dossier', 'ref' => $dossier[0]];
}
foreach ($prospects as $p) {
    $recherche[] = ['type' => 'Prospect', 'titre' => $p[0], 'detail' => $p[1] . ' · ' . $p[3], 'page' => 'prospects', 'ref' => null];
}

// « Ma journée » par poste : indicateurs, à faire, rendez-vous, nouveautés
$journee = [
    'admin' => [
        'kpis' => [['Contacts cette semaine', '24', 'pro', 'target'], ['RDV honorés sur 30 j', '87 %', 'rdv', 'calendar'], ['Dossiers ouverts', '42', 'ori', 'folder'], ['FCFA encaissés ce mois', '6,4 M', 'fin', 'wallet']],
        'taches' => [
            ['urgent', 'Transfert à valider', 'Dossier TAY-2027-0125 : Cheikh Gueye demande un transfert vers un collègue.', 'Examiner', 'TAY-2027-0125'],
            ['alerte', 'Modèle à revérifier', 'Procédure « Canada · Permis d’études » vérifiée il y a 7 mois.', 'Ouvrir', null],
            ['info', 'Correction de reçu', 'Ndèye Sarr demande l’annulation du reçu DKR-2027-000214 (doublon).', 'Examiner', null],
        ],
    ],
    'secretaire' => [
        'kpis' => [['En salle d’attente', '3', 'ech', 'queue'], ['RDV du jour', '9', 'rdv', 'calendar'], ['RDV à confirmer', '4', 'ori', 'inbox']],
        'confirmer' => [
            ['Rokhaya Thiam', 'Entretien d’orientation', 'demain 10:00', 'Moussa Diop', 'Site web'],
            ['Gora Wade', 'Premier rendez-vous', 'jeudi 15:30', 'Moussa Diop', 'Khady Diouf (commerciale)'],
            ['Haby Sow', 'Premier rendez-vous', 'vendredi 09:00', 'Moussa Diop', 'Awa Niang (commerciale)'],
            ['Ousmane Fall', 'Point visa', 'vendredi 11:30', 'Cheikh Gueye', 'Site web'],
        ],
        'file' => [['Dior Faye', 'Information Canada', '12 min'], ['Coumba Diallo', 'Paiement', '6 min'], ['Lamine Sy', 'Rendez-vous 11:00', 'en avance']],
    ],
    'prospection' => [
        'kpis' => [['Prospects · Rentrée 2027', '318', 'pro', 'target'], ['Prospects non attribués', '6', 'ech', 'users'], ['Établissements visités', '12 / 20', 'ok', 'building']],
        'taches' => [
            ['alerte', '6 prospects non attribués', 'Lycées Lamine Guèye, Seydina Limamou Laye, Blaise Diagne et Kennedy.', 'Attribuer', 'prospects'],
            ['info', 'Import du 21/11 : 4 doublons écartés', '146 lignes importées depuis « Lycée Kennedy TS ». Rapport disponible.', 'Voir le rapport', null],
        ],
    ],
    'commerciale' => [
        'kpis' => [['Appels du jour', '7', 'pro', 'phone'], ['RDV à confirmer', '3', 'rdv', 'calendar'], ['Conversion sur 30 j', '18 %', 'ok', 'chart']],
        'appels' => [['Fama Seck', 'Lycée Blaise Diagne · Terminale G', 'Rappeler après 17 h'], ['Haby Sow', 'Lycée Kennedy · Terminale L', 'Intéressée par le Canada'], ['Adja Mbengue', 'Lycée Lamine Guèye · Terminale S2', 'Premier appel']],
    ],
    'conseiller' => [
        'kpis' => [['Entretiens du jour', '4', 'ori', 'chat'], ['Dossiers suivis', '14', 'ok', 'folder'], ['Dossiers en retard', '1', 'urg', 'alert']],
        'taches' => [
            ['urgent', 'Dossier en retard', 'Abdou Karim Cissé : dossier en retard, le client attend une réponse depuis 3 jours.', 'Reprendre', 'TAY-2027-0125'],
            ['info', 'Nouveau client affecté', 'Rokhaya Thiam (Belgique) vous a été affectée par l’accueil.', 'Ouvrir', 'TAY-2027-0128'],
        ],
    ],
    'orientation' => [
        'kpis' => [['Étudiants à orienter', '5', 'ori', 'compass'], ['RDV du jour', '2', 'rdv', 'calendar'], ['Dossiers transmis ce mois', '11', 'ok', 'send']],
        'taches' => [
            ['alerte', 'À orienter : Mamadou Kane', 'Compte rendu du conseiller disponible. Projet : informatique au Canada, rentrée janvier 2028.', 'Orienter', 'TAY-2027-0139'],
            ['info', 'À orienter : Lamine Sy', 'Hésite entre le droit et la gestion ; budget modéré.', 'Orienter', 'TAY-2027-0107'],
        ],
    ],
    'traitement' => [
        'kpis' => [['Pièces à valider', '3', 'ok', 'file'], ['Échéances sous 7 jours', '4', 'ech', 'clock'], ['Dossiers bloqués', '2', 'urg', 'lock']],
        'taches' => [
            // Pièces et échéances ont leurs propres cartes : ici, le reste du travail
            ['alerte', 'Dossier bloqué : Seynabou Mbaye', 'Attente de l’établissement depuis 12 jours : relancer l’université ou prévenir la cliente.', 'Ouvrir', 'TAY-2027-0137'],
            ['info', 'Réponse à saisir : Astou Gaye', 'Université de Tunis : réponse reçue par e-mail.', 'Saisir', 'TAY-2027-0112'],
        ],
        // Pièces déposées par les clients, à vérifier : [personne, pièce, déposée, référence]
        'verifier' => [
            ['Awa Sène', 'Relevé de notes de terminale', 'il y a 25 min', 'TAY-2027-0142'],
            ['Seynabou Mbaye', 'Attestation de bourse', 'il y a 2 h', 'TAY-2027-0137'],
            ['Ousmane Fall', 'Relevés bancaires (3 mois)', 'hier', 'TAY-2027-0131'],
        ],
        // Échéances des 7 jours : [quand, personne, quoi, ton, référence]
        'echeances' => [
            ['hier', 'Abdou Karim Cissé', 'Lettre d’acceptation sur le portail CAQ', 'urgent', 'TAY-2027-0125'],
            ['dans 2 j', 'Awa Sène', 'Frais Campus France à régler', 'alerte', 'TAY-2027-0142'],
            ['dans 6 j', 'Ousmane Fall', 'Dépôt de la demande de visa', 'info', 'TAY-2027-0131'],
        ],
    ],
    'comptable' => [
        'kpis' => [['Règlements attendus', '5', 'ech', 'wallet'], ['FCFA encaissés aujourd’hui', '850 000', 'fin', 'check'], ['Corrections en attente', '1', 'urg', 'receipt']],
        'taches' => [
            // Les échéances ont leur propre carte : ici, seulement ce qui n'en est pas une
            ['alerte', 'Correction de reçu à préparer', 'Reçu DKR-2027-000214 en double : demande d’annulation à soumettre à l’administratrice.', 'Préparer', null],
            ['info', 'Devis à envoyer : Rokhaya Thiam', 'Admission Belgique : le conseiller a validé le service, le devis attend votre envoi.', 'Ouvrir', 'TAY-2027-0128'],
        ],
        // Échéances financières : [quand, personne, objet, montant, ton, référence]
        'echeances' => [
            ['depuis 2 j', 'Coumba Diallo', 'Billet et hôtel Djeddah, 2ᵉ versement', '450 000 FCFA', 'urgent', 'TAY-2027-0121'],
            ['aujourd’hui', 'Awa Sène', 'Frais Campus France', '150 000 FCFA', 'alerte', 'TAY-2027-0142'],
            ['vendredi', 'Seynabou Mbaye', 'Frais de service (2/2)', '200 000 FCFA', 'info', 'TAY-2027-0137'],
        ],
        // Règlements enregistrés aujourd’hui : [heure, personne, mode, montant, reçu]
        'reglements' => [
            ['09:20', 'Ousmane Fall', 'Wave', '350 000 FCFA', 'DKR-2027-000228'],
            ['10:05', 'Pape Samba Ndour', 'Espèces', '300 000 FCFA', 'DKR-2027-000229'],
            ['11:40', 'Astou Gaye', 'Orange Money', '200 000 FCFA', 'DKR-2027-000230'],
        ],
    ],
];

// Rendez-vous du jour (heure, personne, objet, poste concerné)
$rdvJour = [
    ['09:00', 'Lamine Sy', 'Entretien d’orientation', 'conseiller'],
    ['10:30', 'Mamadou Kane', 'Orientation : Canada', 'orientation'],
    ['11:00', 'Awa Sène', 'Point dossier Campus France', 'traitement'],
    ['14:30', 'Rokhaya Thiam', 'Premier entretien (visio)', 'conseiller'],
    ['16:00', 'Seynabou Mbaye', 'Préparation entretien consulaire', 'traitement'],
];

// Dossier détaillé fictif (TAY-2027-0142)
$fiche = [
    'personne' => ['Awa Sène', '19 ans · Étudiante en L1 à l’UCAD', '77 123 45 67', 'awa.sene@exemple.sn', 'Parent : Mme Ndèye Sène (vérifiée)'],
    'responsables' => ['conseiller' => 'Moussa Diop', 'orientation' => 'Fatou Ba', 'traitement' => 'Cheikh Gueye', 'comptable' => 'Ndèye Sarr'],
    'pieces' => [
        ['Passeport', 'valide', 'Valide jusqu’au 03/2031'],
        ['Acte de naissance', 'valide', 'Copie intégrale'],
        ['Relevé de notes de terminale', 'recue', 'Déposée il y a 25 min par la cliente'],
        ['Diplôme du baccalauréat', 'valide', 'Copie certifiée'],
        ['Lettre de motivation', 'refusee', 'Refusée : trop générale, citer la formation visée'],
        ['Attestation de logement', 'manquante', 'À fournir avant la demande de visa'],
    ],
    'procedure' => [
        ['Création du compte Études en France', true, 'Fait le 02/11'],
        ['Saisie du parcours et des notes', true, 'Fait le 08/11'],
        ['Choix des formations (7 maximum)', true, 'Fait le 15/11'],
        ['Paiement des frais Campus France', false, 'Échéance dans 2 jours'],
        ['Entretien pédagogique Campus France', false, 'À planifier'],
        ['Réponses des établissements', false, 'À partir de mars'],
        ['Demande de visa (France-Visas)', false, 'Après acceptation'],
    ],
    'source' => ['Campus France Sénégal · procédure Études en France', 'https://www.senegal.campusfrance.org/', 'Vérifié le 04/11/2026 par Mariama Sall · version 3'],
    'paiements' => [['Frais de service Taysir (1/2)', '250 000 FCFA', 'Payé le 01/11', 'paye'], ['Frais Campus France', '150 000 FCFA', 'Dû le ' . $jour->modify('+2 days')->format('d/m'), 'du'], ['Frais de service Taysir (2/2)', '250 000 FCFA', 'Dû le 15/01', 'avenir']],
    'notes' => [
        ['Moussa Diop', 'Conseiller', 'interne', 'Profil solide en mathématiques. Prévoir un plan B en Belgique si refus.', 'il y a 3 semaines'],
        ['Fatou Ba', 'Agente d’orientation', 'partagee', 'Formations recommandées : licence MIASHS (Lyon 2, Grenoble, Toulouse), BUT Informatique.', 'il y a 2 semaines'],
    ],
    'historique' => [
        ['il y a 25 min', 'Awa Sène a déposé « Relevé de notes de terminale »'],
        ['hier', 'Rappel automatique envoyé : frais Campus France dans 3 jours'],
        ['15/11', 'Cheikh Gueye a validé l’étape « Choix des formations »'],
        ['08/11', 'Lettre de motivation refusée par Cheikh Gueye (motif partagé avec la cliente)'],
        ['02/11', 'Dossier ouvert par Moussa Diop depuis le compte rendu d’entretien'],
    ],
];

// Suivi côté client
$client = [
    'etudes' => [
        'prenom' => 'Awa',
        'afaire' => [
            ['piece', 'Relevé de notes de terminale', 'Bien reçu : il est en cours de vérification par votre chargé de dossier.', null],
            ['piece-refusee', 'Lettre de motivation à refaire', 'Motif : elle est trop générale. Citez la formation visée et votre projet professionnel.', 'Déposer la nouvelle version'],
            ['piece', 'Attestation de logement', 'Demandée pour le visa : elle prouve où vous serez hébergée en France.', 'Déposer la pièce'],
            ['paiement', 'Frais Campus France : 150 000 FCFA', 'À régler avant le ' . $jour->modify('+2 days')->format('d/m') . ' pour tenir le calendrier Campus France.', 'Voir comment payer'],
        ],
        'dossier' => ['Admission et visa études · France', 'TAY-2027-0142', 3, 'Cheikh Gueye, votre chargé de dossier'],
        'rdv' => ['Point dossier Campus France', 'aujourd’hui 11:00', 'Agence de Dakar · Cheikh Gueye'],
    ],
    'voyage' => [
        'prenom' => 'Ousmane',
        'afaire' => [
            ['piece', 'Relevés bancaires des 3 derniers mois', 'Le consulat vérifie que vous pouvez financer votre séjour en Turquie.', 'Déposer la pièce'],
            ['piece', 'Réservation d’hôtel', 'Déposez votre réservation. Pas encore de réservation ? Votre chargé de dossier peut la faire pour vous.', 'Déposer la pièce'],
        ],
        'dossier' => ['Visa tourisme · Turquie', 'TAY-2027-0131', 2, 'Cheikh Gueye, votre chargé de dossier'],
        'rdv' => ['Point visa', 'vendredi 11:30', 'Agence de Dakar · Cheikh Gueye'],
    ],
];

return compact('equipe', 'nav', 'dossiers', 'statuts', 'etapes', 'prospects', 'recherche', 'journee', 'rdvJour', 'fiche', 'client');
