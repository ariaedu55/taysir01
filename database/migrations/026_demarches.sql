-- Phase 12 : catalogue des démarches (docs/gestion-phase-12.md). Une démarche = service + pays + motif.
-- Additive ; retour arrière : database/rollback/026_demarches.down.sql.

-- 1. Pays couverts par l'agence, partagés par le site, les formulaires et les filtres (« Autre pays » en plus)
CREATE TABLE IF NOT EXISTS covered_countries (
    id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name     VARCHAR(120) NOT NULL,
    active   TINYINT(1) NOT NULL DEFAULT 1,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY covered_countries_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO covered_countries (name, position) VALUES
    ('France', 1), ('Canada', 2), ('Belgique', 3), ('Maroc', 4), ('Tunisie', 5), ('Turquie', 6),
    ('Angleterre', 7), ('Portugal', 8), ('Russie', 9), ('Arabie saoudite', 10), ('Chine', 11), ('Japon', 12)
ON DUPLICATE KEY UPDATE position = VALUES(position);

-- 2. Démarche : motif (NULL = tous les motifs ; destination NULL = tous les pays) et pièces structurées.
-- Une seule démarche publiée par combinaison service + pays + motif, garantie par la base.
ALTER TABLE procedure_templates
    ADD COLUMN purpose VARCHAR(40) NULL AFTER destination,
    ADD COLUMN documents JSON NULL COMMENT 'Pièces : titre, aide, obligatoire, fournisseur' AFTER steps,
    ADD COLUMN published_combo VARCHAR(200) GENERATED ALWAYS AS (CASE WHEN status = 'publie'
        THEN CONCAT(service, '|', COALESCE(destination, '*'), '|', COALESCE(purpose, '*')) ELSE NULL END) STORED,
    ADD UNIQUE KEY procedure_templates_combo_unique (published_combo),
    ADD KEY procedure_templates_match_index (service, status, destination, purpose);

-- 3. Le dossier garde son motif (le pays est déjà dans destination)
ALTER TABLE service_requests ADD COLUMN purpose VARCHAR(40) NULL AFTER destination;

-- 4. Pièce attendue : aide, facultative, fournisseur ; « non demandée » pour ce dossier (motif noté)
ALTER TABLE request_requirements
    ADD COLUMN hint VARCHAR(255) NULL AFTER title,
    ADD COLUMN optional TINYINT(1) NOT NULL DEFAULT 0 AFTER hint,
    ADD COLUMN provider VARCHAR(20) NULL AFTER optional,
    ADD COLUMN waived_reason VARCHAR(255) NULL AFTER refusal_reason,
    MODIFY status ENUM('manquante', 'deposee', 'validee', 'refusee', 'non-demandee') NOT NULL DEFAULT 'manquante';
-- Une pièce « non demandée » n'a pas de fichier, comme une pièce « à fournir » (règle de 017 élargie)
ALTER TABLE request_requirements
    DROP CONSTRAINT request_requirements_document_state,
    ADD CONSTRAINT request_requirements_document_state CHECK ((status IN ('manquante', 'non-demandee')) = (document_id IS NULL));

-- 5. Le modèle « Canada, permis d'études » (014) porte le motif « études »
UPDATE procedure_templates SET purpose = 'etudes', updated_at = updated_at WHERE template_key = 'canada-permis' AND purpose IS NULL;

-- 6. Démarches de départ, EN BROUILLON, à faire vérifier et compléter par l'agence avant publication
INSERT INTO procedure_templates (template_key, version, title, service, destination, purpose, status, steps, documents, sources) VALUES
('demarche-admission', 1, 'Admission (modèle général)', 'admission', NULL, NULL, 'brouillon',
 '[{"titre":"Étude du profil et des résultats","pieces":[],"delai":7},{"titre":"Choix des établissements et des filières","pieces":[],"delai":14},{"titre":"Constitution et dépôt des candidatures","pieces":[],"delai":30},{"titre":"Suivi jusqu’à la réponse","pieces":[],"delai":null}]',
 '[{"titre":"Copie du passeport ou de la carte d’identité","aide":"Pages d’identité, en couleur et lisibles","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Relevés de notes des deux dernières années","aide":"Tous les bulletins, y compris l’année en cours","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Diplôme ou attestation de réussite","aide":"Baccalauréat ou dernier diplôme obtenu","obligatoire":false,"fournisseur":"etudiant"},{"titre":"Lettre de motivation","aide":"Une page, adaptée à la formation visée","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Curriculum vitae","aide":null,"obligatoire":false,"fournisseur":"etudiant"},{"titre":"Photo d’identité récente","aide":"Fond clair, format JPG","obligatoire":true,"fournisseur":"etudiant"}]',
 '[]'),
('demarche-visa', 1, 'Visa (modèle général)', 'visa', NULL, NULL, 'brouillon',
 '[{"titre":"Vérification des pièces exigées par le consulat","pieces":[],"delai":7},{"titre":"Constitution du dossier et prise de rendez-vous","pieces":[],"delai":14},{"titre":"Préparation à l’entretien","pieces":[],"delai":null},{"titre":"Suivi jusqu’à la décision","pieces":[],"delai":null}]',
 '[{"titre":"Passeport en cours de validité","aide":"Valable au moins 6 mois après la date de retour prévue","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Photos d’identité aux normes","aide":"Deux photos récentes, fond clair","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Justificatif de ressources","aide":"Relevés bancaires des trois derniers mois","obligatoire":true,"fournisseur":"garant"},{"titre":"Justificatif d’hébergement","aide":"Réservation, attestation d’hébergement ou contrat de location","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Assurance voyage","aide":"Couvrant toute la durée du séjour","obligatoire":true,"fournisseur":"etudiant"}]',
 '[]'),
('demarche-visa-france-etudes', 1, 'Visa études · France', 'visa', 'France', 'etudes', 'brouillon',
 '[{"titre":"Procédure Études en France (Campus France)","pieces":[],"delai":30},{"titre":"Entretien Campus France","pieces":[],"delai":null},{"titre":"Demande de visa long séjour sur France-Visas","pieces":[],"delai":14},{"titre":"Rendez-vous au centre de visas et suivi","pieces":[],"delai":null}]',
 '[{"titre":"Passeport en cours de validité","aide":"Valable au moins 3 mois après la fin du séjour, avec deux pages vierges","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Attestation d’inscription ou de préinscription","aide":"Délivrée par l’établissement français","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Attestation Campus France","aide":"Remise après l’entretien Campus France","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Justificatif de ressources (615 € par mois)","aide":"Attestation de prise en charge et relevés bancaires du garant","obligatoire":true,"fournisseur":"garant"},{"titre":"Justificatif d’hébergement en France","aide":"Au moins pour les trois premiers mois","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Photo d’identité aux normes","aide":null,"obligatoire":true,"fournisseur":"etudiant"},{"titre":"Relevés de notes et diplômes","aide":"Avec traduction si nécessaire","obligatoire":true,"fournisseur":"etudiant"}]',
 '["https://www.campusfrance.org/fr","https://france-visas.gouv.fr/"]'),
('demarche-logement', 1, 'Logement (modèle général)', 'logement', NULL, NULL, 'brouillon',
 '[{"titre":"Analyse du budget et de la ville d’études","pieces":[],"delai":7},{"titre":"Recherche de logements adaptés","pieces":[],"delai":21},{"titre":"Montage du dossier de réservation","pieces":[],"delai":7},{"titre":"Préparation de l’arrivée","pieces":[],"delai":null}]',
 '[{"titre":"Copie du passeport","aide":"Pages d’identité","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Attestation d’admission ou d’inscription","aide":null,"obligatoire":true,"fournisseur":"etudiant"},{"titre":"Pièce d’identité du garant","aide":null,"obligatoire":true,"fournisseur":"garant"},{"titre":"Justificatif de revenus du garant","aide":"Trois derniers bulletins de salaire ou avis d’imposition","obligatoire":true,"fournisseur":"garant"}]',
 '[]'),
('demarche-prise-en-charge', 1, 'Prise en charge (modèle général)', 'prise-en-charge', NULL, NULL, 'brouillon',
 '[{"titre":"Point sur les exigences de l’établissement et du consulat","pieces":[],"delai":7},{"titre":"Préparation de l’attestation","pieces":[],"delai":14},{"titre":"Vérification du dossier complet","pieces":[],"delai":null}]',
 '[{"titre":"Pièce d’identité du garant","aide":null,"obligatoire":true,"fournisseur":"garant"},{"titre":"Relevés bancaires du garant","aide":"Trois à six derniers mois","obligatoire":true,"fournisseur":"garant"},{"titre":"Justificatif de revenus du garant","aide":"Bulletins de salaire, attestation de travail ou registre de commerce","obligatoire":true,"fournisseur":"garant"},{"titre":"Lien de parenté avec le garant","aide":"Extrait d’acte de naissance, si le garant est un parent","obligatoire":false,"fournisseur":"parent"}]',
 '[]'),
('demarche-billet', 1, 'Billet d’avion (modèle général)', 'billet', NULL, NULL, 'brouillon',
 '[{"titre":"Recherche des vols selon les dates","pieces":[],"delai":3},{"titre":"Proposition des meilleures options","pieces":[],"delai":3},{"titre":"Réservation et émission du billet","pieces":[],"delai":null}]',
 '[{"titre":"Copie du passeport","aide":"Le nom doit être identique à celui du billet","obligatoire":true,"fournisseur":"etudiant"},{"titre":"Visa, si exigé pour la destination","aide":null,"obligatoire":false,"fournisseur":"etudiant"}]',
 '[]'),
('demarche-hotel', 1, 'Réservation d’hôtel (modèle général)', 'hotel', NULL, NULL, 'brouillon',
 '[{"titre":"Recherche selon la ville et les dates","pieces":[],"delai":3},{"titre":"Proposition d’hôtels","pieces":[],"delai":3},{"titre":"Réservation et envoi de la confirmation","pieces":[],"delai":null}]',
 '[{"titre":"Copie du passeport","aide":"Pour le nom de la réservation","obligatoire":true,"fournisseur":"etudiant"}]',
 '[]')
ON DUPLICATE KEY UPDATE id = id;
