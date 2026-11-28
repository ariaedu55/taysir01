-- Phase 2B : agenda (docs/gestion-phase-2.md, §2B, C6 et C7).
-- Les rendez-vous restent compatibles en lecture avec scheduled_at. Les nouveaux créneaux utilisent les dates UTC.

ALTER TABLE agencies
    ADD COLUMN timezone VARCHAR(40) NOT NULL DEFAULT 'Africa/Dakar' AFTER city;

UPDATE agencies SET timezone = 'Europe/Paris' WHERE code = 'paris' AND timezone = 'Africa/Dakar';

-- Étape 1 : accepter les anciens et les nouveaux statuts pendant la reprise.
ALTER TABLE appointments
    MODIFY status ENUM('demande', 'confirme', 'termine', 'annule', 'honore', 'absent', 'reporte') NOT NULL DEFAULT 'demande',
    ADD COLUMN assigned_to BIGINT UNSIGNED NULL,
    ADD COLUMN agency_id TINYINT UNSIGNED NULL,
    ADD COLUMN starts_at_utc DATETIME NULL,
    ADD COLUMN ends_at_utc DATETIME NULL,
    ADD COLUMN rescheduled_to_id BIGINT UNSIGNED NULL COMMENT 'Rendez-vous qui remplace celui-ci après un report',
    ADD COLUMN reminded_at DATETIME NULL COMMENT 'Date UTC de prise en charge du rappel',
    ADD KEY appointments_assigned_start_index (assigned_to, starts_at_utc),
    ADD KEY appointments_agency_start_index (agency_id, starts_at_utc),
    ADD KEY appointments_rescheduled_to_index (rescheduled_to_id),
    ADD CONSTRAINT appointments_assigned_fk FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE RESTRICT,
    ADD CONSTRAINT appointments_agency_fk FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT,
    ADD CONSTRAINT appointments_rescheduled_to_fk FOREIGN KEY (rescheduled_to_id) REFERENCES appointments (id) ON DELETE RESTRICT;

-- Étape 2 : les dates historiques étaient saisies dans le fuseau fixe de l'application (Dakar, UTC+0).
-- Ziguinchor est explicite dans le mode. Dakar était l'agence de référence des autres modes.
-- L'ancien modèle ne donnait ni durée confirmée ni responsable.
UPDATE appointments a
JOIN agencies g ON g.code = CASE WHEN a.mode = 'agence-ziguinchor' THEN 'ziguinchor' ELSE 'dakar' END
SET a.agency_id = g.id, a.updated_at = a.updated_at
WHERE a.agency_id IS NULL;

UPDATE appointments
SET starts_at_utc = scheduled_at, updated_at = updated_at
WHERE starts_at_utc IS NULL AND scheduled_at IS NOT NULL;

UPDATE appointments SET status = 'honore', updated_at = updated_at WHERE status = 'termine';

-- Étape 3 : retirer l'ancien statut et empêcher deux créneaux actifs au même début pour un responsable.
-- Les lignes sans responsable ou sans début, et les statuts inactifs, restent hors de cette unicité.
-- Les chevauchements avec des débuts différents sont contrôlés par l'application sous verrou du responsable.
ALTER TABLE appointments
    MODIFY status ENUM('demande', 'confirme', 'honore', 'absent', 'reporte', 'annule') NOT NULL DEFAULT 'demande',
    ADD COLUMN active_starts_at_utc DATETIME GENERATED ALWAYS AS (
        CASE WHEN assigned_to IS NOT NULL AND status IN ('demande', 'confirme') THEN starts_at_utc ELSE NULL END
    ) STORED,
    ADD UNIQUE KEY appointments_active_start_unique (assigned_to, active_starts_at_utc);

-- Semaine type en heure locale de l'agence. Jour ISO : lundi = 1, dimanche = 7.
-- Une règle décrit une plage sur une seule journée, en agence ou en visio.
CREATE TABLE IF NOT EXISTS availability_rules (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    agency_id   TINYINT UNSIGNED NOT NULL,
    weekday     TINYINT UNSIGNED NOT NULL,
    starts_at   TIME NOT NULL,
    ends_at     TIME NOT NULL,
    mode        ENUM('agence', 'visio') NOT NULL,
    UNIQUE KEY availability_rules_slot_unique (user_id, agency_id, weekday, starts_at, ends_at, mode),
    KEY availability_rules_agency_index (agency_id),
    CONSTRAINT availability_rules_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT availability_rules_agency_fk FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT,
    CONSTRAINT availability_rules_weekday_check CHECK (weekday BETWEEN 1 AND 7),
    CONSTRAINT availability_rules_hours_check CHECK (starts_at >= '00:00:00' AND starts_at < ends_at AND ends_at <= '24:00:00')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Absences ponctuelles en UTC. Une exception bloque le membre dans toutes les agences, y compris en visio.
CREATE TABLE IF NOT EXISTS availability_exceptions (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    starts_at_utc DATETIME NOT NULL,
    ends_at_utc   DATETIME NOT NULL,
    reason        VARCHAR(255) NULL,
    UNIQUE KEY availability_exceptions_interval_unique (user_id, starts_at_utc, ends_at_utc),
    CONSTRAINT availability_exceptions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT availability_exceptions_interval_check CHECK (starts_at_utc < ends_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique interne, alimenté par l'application après 012. Un acteur NULL désigne un événement sans compte.
CREATE TABLE IF NOT EXISTS appointment_events (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    user_id        BIGINT UNSIGNED NULL,
    type           ENUM('creation', 'statut', 'affectation', 'deplacement', 'note') NOT NULL,
    status         VARCHAR(20) NULL,
    message        TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY appointment_events_appointment_index (appointment_id, created_at),
    KEY appointment_events_user_index (user_id),
    CONSTRAINT appointment_events_appointment_fk FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE RESTRICT,
    CONSTRAINT appointment_events_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
