-- Phase 2, étape 2A (docs/gestion-phase-2.md, « 2A Plan détaillé », conditions C5 et C7) : fiche personne.
-- Une personne existe avec ou sans compte. Les demandes (= dossiers, décision D1) et les rendez-vous lui sont rattachés.
-- Clés étrangères en RESTRICT, sans cascade. Reprise relançable : INSERT … WHERE NOT EXISTS, updated_at conservé.
-- Aucune fusion automatique : les doublons sont traités en phase 4. Retour arrière : database/rollback/011_personnes.down.sql.

CREATE TABLE IF NOT EXISTS people (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name    VARCHAR(80) NOT NULL,
    last_name     VARCHAR(80) NOT NULL,
    email         VARCHAR(190) NULL,
    phone         VARCHAR(30) NULL,
    profile       ENUM('eleve', 'etudiant', 'parent', 'particulier') NULL,
    birth_date    DATE NULL,
    timezone      VARCHAR(40) NOT NULL DEFAULT 'Africa/Dakar' COMMENT 'Fuseau de la personne (visio)',
    agency_id     TINYINT UNSIGNED NULL,
    origin        ENUM('site', 'visite', 'prospect', 'import', 'reprise') NOT NULL DEFAULT 'site',
    to_reconcile  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 : à rapprocher (e-mail déjà connu, rattachement non automatique)',
    import_ref    VARCHAR(40) NULL COMMENT 'user:<id> ou rdv:<id> (reprise 011), puis imports',
    created_by    BIGINT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY people_import_ref_unique (import_ref),
    KEY people_email_index (email),
    KEY people_name_index (last_name, first_name),
    KEY people_agency_fk (agency_id),
    KEY people_created_by_fk (created_by),
    CONSTRAINT people_agency_fk FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT,
    CONSTRAINT people_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN person_id BIGINT UNSIGNED NULL COMMENT 'Fiche personne du compte (au plus un compte par personne)' AFTER id,
    ADD UNIQUE KEY users_person_unique (person_id),
    ADD CONSTRAINT users_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT;

ALTER TABLE service_requests DROP FOREIGN KEY service_requests_user_fk;

ALTER TABLE service_requests
    MODIFY user_id BIGINT UNSIGNED NULL COMMENT 'Compte qui a envoyé la demande ; NULL pour un dossier ouvert par l''équipe',
    ADD COLUMN person_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD KEY service_requests_person_index (person_id);

ALTER TABLE service_requests
    ADD CONSTRAINT service_requests_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    ADD CONSTRAINT service_requests_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT;

ALTER TABLE appointments
    ADD COLUMN person_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD KEY appointments_person_index (person_id),
    ADD CONSTRAINT appointments_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT;

-- C5 : ce que l'équipe écrit est interne par défaut ; le code écrit visible = 1 explicitement quand le client doit le lire
ALTER TABLE request_events MODIFY visible TINYINT(1) NOT NULL DEFAULT 0;

-- Reprise 1 : une personne par compte client
INSERT INTO people (first_name, last_name, email, phone, profile, agency_id, origin, import_ref, created_at)
SELECT u.first_name, u.last_name, u.email, u.phone, u.profile, u.agency_id, 'reprise', CONCAT('user:', u.id), u.created_at
FROM users u
WHERE u.role = 'client' AND u.person_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM people p WHERE p.import_ref = CONCAT('user:', u.id));

UPDATE users u
SET u.person_id = (SELECT p.id FROM people p WHERE p.import_ref = CONCAT('user:', u.id)), u.updated_at = u.updated_at
WHERE u.role = 'client' AND u.person_id IS NULL;

-- Reprise 2 : demandes et rendez-vous d'un compte
UPDATE service_requests r
SET r.person_id = (SELECT u.person_id FROM users u WHERE u.id = r.user_id), r.updated_at = r.updated_at
WHERE r.person_id IS NULL AND r.user_id IS NOT NULL;

UPDATE appointments a
SET a.person_id = (SELECT u.person_id FROM users u WHERE u.id = a.user_id), a.updated_at = a.updated_at
WHERE a.person_id IS NULL AND a.user_id IS NOT NULL;

-- Reprise 3 : rendez-vous sans compte. Une personne par e-mail et nom normalisés ; jamais vers une personne qui a un
-- compte (C5). Si l'e-mail est déjà connu d'une autre personne, la nouvelle est marquée « à rapprocher ».
INSERT INTO people (first_name, last_name, email, phone, origin, to_reconcile, import_ref, created_at)
SELECT g.first_name, g.last_name, g.email, g.phone, 'reprise',
       CASE WHEN EXISTS (SELECT 1 FROM people p WHERE LOWER(TRIM(p.email)) = g.email_norm) THEN 1 ELSE 0 END,
       CONCAT('rdv:', g.first_id), g.created_at
FROM (
    SELECT MIN(a.id) AS first_id, MIN(a.first_name) AS first_name, MIN(a.last_name) AS last_name, MIN(a.email) AS email,
           MIN(a.phone) AS phone, MIN(a.created_at) AS created_at, LOWER(TRIM(a.email)) AS email_norm
    FROM appointments a
    WHERE a.user_id IS NULL AND a.person_id IS NULL
    GROUP BY LOWER(TRIM(a.email)), LOWER(TRIM(a.first_name)), LOWER(TRIM(a.last_name))
) g
WHERE NOT EXISTS (
    SELECT 1 FROM people p
    WHERE p.import_ref LIKE 'rdv:%' AND LOWER(TRIM(p.email)) = g.email_norm
      AND LOWER(TRIM(p.first_name)) = LOWER(TRIM(g.first_name)) AND LOWER(TRIM(p.last_name)) = LOWER(TRIM(g.last_name))
);

-- Fiches anonymes de la reprise dont l'e-mail est partagé avec une autre fiche (créées dans la même instruction,
-- elles ne se voyaient pas encore) : « à rapprocher ». Table dérivée : portable MySQL et MariaDB.
UPDATE people p
SET p.to_reconcile = 1
WHERE p.import_ref LIKE 'rdv:%' AND p.to_reconcile = 0
  AND LOWER(TRIM(p.email)) IN (
      SELECT d.email_norm FROM (
          SELECT LOWER(TRIM(q.email)) AS email_norm FROM people q WHERE q.email IS NOT NULL
          GROUP BY LOWER(TRIM(q.email)) HAVING COUNT(*) > 1
      ) d
  );

UPDATE appointments a
SET a.person_id = (
        SELECT MIN(p.id) FROM people p
        WHERE p.import_ref LIKE 'rdv:%' AND LOWER(TRIM(p.email)) = LOWER(TRIM(a.email))
          AND LOWER(TRIM(p.first_name)) = LOWER(TRIM(a.first_name)) AND LOWER(TRIM(p.last_name)) = LOWER(TRIM(a.last_name))
    ),
    a.updated_at = a.updated_at
WHERE a.user_id IS NULL AND a.person_id IS NULL;
