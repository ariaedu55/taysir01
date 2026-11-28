<?php

declare(strict_types=1);

/*
 * Données FICTIVES de la maquette 2.0 (docs/gestion-phase-2.md) : agenda de la semaine, fiche personne,
 * créneaux publics. Noms, horaires et contenus inventés. Rien n'est lu ni écrit en base.
 */

$tz = new DateTimeZone('Africa/Dakar');
$lundi = (new DateTimeImmutable('monday this week', $tz))->setTime(0, 0);
$jour = static fn (int $i): DateTimeImmutable => $lundi->modify('+' . $i . ' days');

// Motifs : libellé, durée (min), famille de couleur (surfaces teintées, jamais de bordure)
$motifs = [
    'orientation-filieres' => ['Orientation : filières', 45, 'ori'],
    'orientation-metiers' => ['Orientation : métiers', 45, 'ori'],
    'renseignement' => ['Renseignement', 30, 'rdv'],
    'admission' => ['Admission', 60, 'ok'],
    'visa' => ['Visa', 45, 'pro'],
    'logement' => ['Logement', 30, 'fin'],
];

// Membres qui ont un agenda (D2) : semaine type Dakar, lun-ven 9-13 / 14-18, sam 9-14 (horaires provisoires, D4)
$membres = [
    'conseiller' => ['Moussa Diop', 'Conseiller'],
    'orientation' => ['Fatou Ba', 'Agente d’orientation'],
    'conseiller2' => ['Awa Ndiaye', 'Conseillère'],
];
$horaires = [
    0 => [['09:00', '13:00'], ['14:00', '18:00']], 1 => [['09:00', '13:00'], ['14:00', '18:00']],
    2 => [['09:00', '13:00'], ['14:00', '18:00']], 3 => [['09:00', '13:00'], ['14:00', '18:00']],
    4 => [['09:00', '13:00'], ['14:00', '18:00']], 5 => [['09:00', '14:00']],
];

// Rendez-vous : [id, jour (0 = lundi), début, membre, personne, motif, mode, statut, fuseau de la personne]
$rendezVous = [
    ['RDV-2041', 0, '09:00', 'conseiller', 'Lamine Sy', 'orientation-filieres', 'agence-dakar', 'honore', null],
    ['RDV-2042', 0, '10:30', 'orientation', 'Mamadou Kane', 'orientation-metiers', 'agence-dakar', 'confirme', null],
    ['RDV-2043', 0, '11:00', 'conseiller', 'Awa Sène', 'admission', 'agence-dakar', 'confirme', null],
    ['RDV-2044', 1, '09:30', 'conseiller2', 'Rokhaya Thiam', 'renseignement', 'telephone', 'confirme', null],
    ['RDV-2045', 1, '14:30', 'conseiller', 'Seynabou Mbaye', 'visa', 'visio', 'confirme', 'Europe/Paris'],
    ['RDV-2046', 2, '10:00', 'orientation', 'Ousmane Fall', 'orientation-filieres', 'visio', 'confirme', 'America/Toronto'],
    ['RDV-2047', 2, '15:00', 'conseiller2', 'Pape Samba Ndour', 'logement', 'agence-dakar', 'confirme', null],
    ['RDV-2048', 3, '09:00', 'conseiller', 'Astou Gaye', 'admission', 'agence-dakar', 'demande', null],
    ['RDV-2049', 3, '11:30', 'orientation', 'Dior Faye', 'orientation-metiers', 'agence-dakar', 'confirme', null],
    ['RDV-2050', 4, '16:00', 'conseiller', 'Coumba Diallo', 'renseignement', 'whatsapp', 'confirme', null],
    ['RDV-2051', 5, '10:00', 'conseiller2', 'El Hadji Ndiaye', 'visa', 'agence-dakar', 'confirme', null],
    // Tâche 2 de la maquette : « le conseiller retrouve son entretien de 10 h » (aujourd'hui, ou samedi le dimanche)
    ['RDV-2054', min(5, (int) (new DateTimeImmutable('now', $tz))->format('N') - 1), '10:00', 'conseiller', 'Awa Sène', 'admission', 'agence-dakar', 'confirme', null],
];
// Exceptions (D2) : [jour, membre, début, fin, motif]
$exceptions = [
    [2, 'conseiller', '09:00', '13:00', 'Formation Campus France'],
    [4, 'orientation', '14:00', '18:00', 'Congé'],
];
// Demandes à placer (sans créneau, ou souhaitées) : [id, personne, motif, souhait, origine]
$demandes = [
    ['RDV-2052', 'Haby Sow', 'orientation-filieres', 'jeudi matin', 'Site web'],
    ['RDV-2053', 'Gora Wade', 'admission', 'vendredi après-midi', 'Khady Diouf (commerciale)'],
];
// Créneaux libres proposés pour placer une demande : [date, début, membre, pris] ; toujours À VENIR (délai de 24 h,
// jamais le dimanche, C6) ; le 1er « vient d'être pris » (démonstration C2)
$prochains = [];
for ($k = 1; count($prochains) < 3; $k++) {
    $date = (new DateTimeImmutable('today', $tz))->modify('+' . $k . ' days');
    if ((int) $date->format('N') !== 7) {
        $prochains[] = $date;
    }
}
$creneauxLibres = [
    [$prochains[0], '10:00', 'orientation', true],
    [$prochains[0], '10:45', 'orientation', false],
    [$prochains[1], '14:00', 'conseiller', false],
    [$prochains[2], '09:00', 'conseiller2', false],
];

// Fiche personne (Awa Sène) : identité, rendez-vous, notes, dossiers, tâches
$personne = [
    'nom' => 'Awa Sène',
    'profil' => 'Étudiante · 19 ans',
    'telephone' => '77 123 45 67',
    'email' => 'awa.sene@exemple.sn',
    'fuseau' => 'Africa/Dakar',
    'origine' => 'Site web',
    'compte' => true,
    'agence' => 'Siège de Dakar',
    'lien' => 'Parent : Mme Ndèye Sène (lien à vérifier)',
    'rdv' => [
        ['RDV-2054', 'aujourd’hui', '10:00', 'Admission', 'Moussa Diop', 'confirme', 'agence-dakar'],
        ['RDV-2043', 'lundi', '11:00', 'Admission', 'Moussa Diop', 'honore', 'agence-dakar'],
        ['RDV-1988', '12/11', '10:00', 'Orientation : filières', 'Fatou Ba', 'honore', 'agence-dakar'],
        ['RDV-1950', '04/11', '15:30', 'Renseignement', 'Moussa Diop', 'honore', 'telephone'],
    ],
    // Notes : [auteur, poste, visibilité, texte, date, rendez-vous lié]
    'notes' => [
        ['Fatou Ba', 'Agente d’orientation', 'partagee', 'Compte rendu : licence MIASHS (Lyon 2, Grenoble, Toulouse) et BUT Informatique (Lyon 1). Préparer la lettre de motivation par formation.', '12/11', 'RDV-1988'],
        ['Moussa Diop', 'Conseiller', 'interne', 'Profil solide en mathématiques. Budget familial limité : prévoir un plan B en Belgique. Ne pas évoquer devant le parent avant l’accord de l’étudiante.', '04/11', 'RDV-1950'],
        ['Moussa Diop', 'Conseiller', 'interne', 'Relancer pour le relevé de notes de terminale avant le 20/11.', '15/11', null],
    ],
    'dossiers' => [
        ['TAY-2027-0142', 'Admission et visa études', 'France', 'Sept. 2027', 3, 6, 'Pièce à valider', 'alerte', 'France · Campus France (v3)'],
    ],
    'taches' => [
        ['Vérifier le relevé de notes déposé', 'aujourd’hui', 'Cheikh Gueye', false],
        ['Proposer le rendez-vous Campus France', 'jeudi', 'Moussa Diop', false],
        ['Envoyer la liste des pièces', '10/11', 'Moussa Diop', true],
    ],
];

// Modèles de procédure (D5) : [clé, libellé, version, statut, vérifié le, par]
$modeles = [
    ['france-campus', 'France · Admission par Campus France', 3, 'publie', '04/11/2026', 'Mariama Sall'],
    ['canada-permis', 'Canada · Permis d’études (CAQ à confirmer)', 1, 'brouillon', null, null],
    ['belgique-equivalence', 'Belgique · Équivalence et inscription', 1, 'brouillon', null, null],
];

// Réservation publique : jours ouvrables à venir (délai minimal de 24 h, D3) et heures libres
$creneauxPublics = [];
$demain = (new DateTimeImmutable('tomorrow', $tz));
for ($i = 1, $ajoutes = 0; $ajoutes < 6 && $i < 14; $i++) {
    $date = (new DateTimeImmutable('today', $tz))->modify('+' . $i . ' days');
    if ((int) $date->format('N') === 7) {
        continue;
    }
    $samedi = (int) $date->format('N') === 6;
    $heures = $samedi ? ['09:00', '09:45', '11:15', '12:00'] : ['09:00', '09:45', '10:30', '11:15', '14:00', '14:45', '15:30', '16:15', '17:00'];
    // Quelques heures déjà prises, pour un rendu réaliste
    $heures = array_values(array_filter($heures, static fn (string $h, int $k): bool => ($k + $ajoutes) % 4 !== 1, ARRAY_FILTER_USE_BOTH));
    $creneauxPublics[] = [$date, $heures];
    $ajoutes++;
}

return compact('motifs', 'membres', 'horaires', 'rendezVous', 'exceptions', 'demandes', 'creneauxLibres', 'personne', 'modeles', 'creneauxPublics', 'lundi');
