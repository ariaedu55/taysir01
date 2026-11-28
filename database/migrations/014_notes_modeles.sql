-- Phase 2, étape 2C (docs/gestion-phase-2.md, « 2C Plan détaillé » et amendements N1 à N12) :
-- notes et comptes rendus, modèles de procédure versionnés, étapes de dossier, ouverture d'un dossier par l'équipe.
-- Clés étrangères en RESTRICT, sans cascade. Les notes ne se modifient pas (pas d'updated_at).
-- Retour arrière : database/rollback/014_notes_modeles.down.sql (garde-fous : notes, étapes, dossiers ouverts par l'équipe).

-- Notes sur une PERSONNE (N2 : jamais sur un dossier, request_events reste le fil du dossier).
-- Compte rendu d'entretien = note liée à un rendez-vous ; au plus un par rendez-vous (index unique, NULL multiples permis).
-- Visibilité SANS valeur par défaut (E1) : l'application impose un choix explicite.
CREATE TABLE IF NOT EXISTS notes (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id      BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NULL COMMENT 'Compte rendu de cet entretien',
    visibility     ENUM('interne', 'partagee') NOT NULL,
    body           TEXT NOT NULL,
    author_id      BIGINT UNSIGNED NOT NULL,
    author_poste   VARCHAR(20) NOT NULL COMMENT 'Poste sous lequel la note est écrite (N4)',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY notes_appointment_unique (appointment_id),
    KEY notes_person_index (person_id, created_at),
    KEY notes_author_fk (author_id),
    CONSTRAINT notes_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
    CONSTRAINT notes_appointment_fk FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE RESTRICT,
    CONSTRAINT notes_author_fk FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modèles de procédure : une VERSION par ligne (E8). Une seule version publiée par clé, garantie par la base (N5) :
-- published_key vaut la clé si le statut est « publie », NULL sinon, et porte un index unique.
CREATE TABLE IF NOT EXISTS procedure_templates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key  VARCHAR(60) NOT NULL,
    version       SMALLINT UNSIGNED NOT NULL,
    title         VARCHAR(190) NOT NULL,
    service       VARCHAR(30) NOT NULL,
    destination   VARCHAR(120) NULL,
    status        ENUM('brouillon', 'publie', 'retire') NOT NULL DEFAULT 'brouillon',
    steps         JSON NOT NULL COMMENT 'Étapes : titre, pièces, délai indicatif en jours',
    sources       JSON NULL COMMENT 'Sources officielles (URL)',
    verified_at   DATETIME NULL,
    verified_by   BIGINT UNSIGNED NULL,
    created_by    BIGINT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_key VARCHAR(60) GENERATED ALWAYS AS (CASE WHEN status = 'publie' THEN template_key ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY procedure_templates_version_unique (template_key, version),
    UNIQUE KEY procedure_templates_published_unique (published_key),
    KEY procedure_templates_verified_fk (verified_by),
    KEY procedure_templates_created_fk (created_by),
    CONSTRAINT procedure_templates_verified_fk FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT procedure_templates_created_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Étapes d'un dossier, copiées du modèle à l'ouverture, ensuite indépendantes du modèle.
CREATE TABLE IF NOT EXISTS request_steps (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    position   SMALLINT UNSIGNED NOT NULL,
    title      VARCHAR(190) NOT NULL,
    documents  JSON NULL,
    due_days   SMALLINT UNSIGNED NULL,
    done_at    DATETIME NULL,
    done_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY request_steps_position_unique (request_id, position),
    KEY request_steps_done_fk (done_by),
    CONSTRAINT request_steps_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT,
    CONSTRAINT request_steps_done_fk FOREIGN KEY (done_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dossier ouvert par l'équipe (E7, N6, N7) : version du modèle, rentrée visée, ouvreur, jeton d'ouverture unique
-- (une double soumission échoue sur l'index). E-mail et téléphone facultatifs (E10).
ALTER TABLE service_requests
    ADD COLUMN template_id BIGINT UNSIGNED NULL COMMENT 'Version du modèle à l''ouverture' AFTER person_id,
    ADD COLUMN intake VARCHAR(40) NULL COMMENT 'Rentrée visée' AFTER destination,
    ADD COLUMN opened_by BIGINT UNSIGNED NULL COMMENT 'Membre de l''équipe qui a ouvert le dossier' AFTER intake,
    ADD COLUMN open_token CHAR(32) NULL COMMENT 'Jeton d''ouverture (double soumission)' AFTER opened_by,
    MODIFY email VARCHAR(190) NULL,
    MODIFY phone VARCHAR(30) NULL,
    ADD UNIQUE KEY service_requests_open_token_unique (open_token),
    ADD KEY service_requests_template_fk (template_id),
    ADD KEY service_requests_opened_by_fk (opened_by),
    ADD CONSTRAINT service_requests_template_fk FOREIGN KEY (template_id) REFERENCES procedure_templates (id) ON DELETE RESTRICT,
    ADD CONSTRAINT service_requests_opened_by_fk FOREIGN KEY (opened_by) REFERENCES users (id) ON DELETE RESTRICT;

-- N8 : e-mail normalisé du plafond anti-abus, même règle qu'App\Services\BookingLimiter::email() :
-- minuscules, sans espaces autour, partie locale coupée au premier « + », domaine après le PREMIER « @ ».
-- Jamais utilisé pour rattacher une personne.
ALTER TABLE appointments
    ADD COLUMN email_normalized VARCHAR(190) GENERATED ALWAYS AS (
        CASE WHEN LOCATE('@', LOWER(TRIM(email))) = 0 THEN LOWER(TRIM(email))
             ELSE CONCAT(SUBSTRING_INDEX(SUBSTRING_INDEX(LOWER(TRIM(email)), '@', 1), '+', 1), '@',
                         SUBSTRING(LOWER(TRIM(email)), LOCATE('@', LOWER(TRIM(email))) + 1))
        END
    ) STORED,
    ADD KEY appointments_email_normalized_index (email_normalized, status);

-- Modèles de départ (D5) : en BROUILLON, contenu à faire vérifier par l'agence, sans date de vérification.
INSERT INTO procedure_templates (template_key, version, title, service, destination, status, steps, sources)
SELECT 'france-campus', 1, 'France · Admission par Campus France', 'admission', 'France', 'brouillon',
       '[{"titre":"Création du dossier Études en France","pieces":["Passeport","Relevés de notes"],"delai":7},{"titre":"Choix des formations et lettres de motivation","pieces":["Lettre de motivation par formation"],"delai":21},{"titre":"Entretien Campus France","pieces":[],"delai":30},{"titre":"Réponses des établissements","pieces":[],"delai":60},{"titre":"Demande de visa","pieces":["Attestation d’admission","Justificatif de ressources"],"delai":30}]',
       '["https://www.campusfrance.org"]'
WHERE NOT EXISTS (SELECT 1 FROM procedure_templates WHERE template_key = 'france-campus');

INSERT INTO procedure_templates (template_key, version, title, service, destination, status, steps, sources)
SELECT 'canada-permis', 1, 'Canada · Permis d’études (CAQ à confirmer)', 'visa', 'Canada', 'brouillon',
       '[{"titre":"Lettre d’acceptation de l’établissement","pieces":["Lettre d’acceptation"],"delai":30},{"titre":"Certificat d’acceptation du Québec (si applicable, à confirmer)","pieces":[],"delai":30},{"titre":"Demande de permis d’études","pieces":["Passeport","Preuve de fonds"],"delai":45},{"titre":"Données biométriques","pieces":[],"delai":14}]',
       '["https://www.canada.ca/fr/immigration-refugies-citoyennete.html"]'
WHERE NOT EXISTS (SELECT 1 FROM procedure_templates WHERE template_key = 'canada-permis');

INSERT INTO procedure_templates (template_key, version, title, service, destination, status, steps, sources)
SELECT 'belgique-equivalence', 1, 'Belgique · Équivalence et inscription', 'admission', 'Belgique', 'brouillon',
       '[{"titre":"Demande d’équivalence du diplôme","pieces":["Diplôme","Relevés de notes"],"delai":60},{"titre":"Inscription dans l’établissement","pieces":["Équivalence"],"delai":30},{"titre":"Demande de visa","pieces":["Attestation d’inscription","Preuve de fonds"],"delai":30}]',
       NULL
WHERE NOT EXISTS (SELECT 1 FROM procedure_templates WHERE template_key = 'belgique-equivalence');
