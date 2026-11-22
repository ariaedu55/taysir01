-- Demandes de service (admission, logement, visa, prise en charge, billet, hôtel), leurs documents et leur historique,
-- et rendez-vous. Voir docs/demandes-rendez-vous.md.

CREATE TABLE service_requests (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference           VARCHAR(20)  NOT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    service             ENUM('admission', 'logement', 'visa', 'prise-en-charge', 'billet', 'hotel') NOT NULL,
    status              ENUM('nouvelle', 'en-cours', 'attente-client', 'terminee', 'annulee') NOT NULL DEFAULT 'nouvelle',
    civility            VARCHAR(10)  NULL,
    first_name          VARCHAR(80)  NOT NULL,
    last_name           VARCHAR(80)  NOT NULL,
    email               VARCHAR(190) NOT NULL,
    phone               VARCHAR(30)  NOT NULL,
    birth_date          DATE         NULL,
    destination         VARCHAR(120) NULL,
    details             JSON         NULL COMMENT 'Champs propres au service (config/services.php)',
    message             TEXT         NULL,
    assigned_to         BIGINT UNSIGNED NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY service_requests_reference_unique (reference),
    KEY service_requests_user_index (user_id),
    KEY service_requests_status_index (status, created_at),
    CONSTRAINT service_requests_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT service_requests_assigned_fk FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_documents (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id    BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name   CHAR(68)     NOT NULL COMMENT 'Nom aléatoire dans storage/uploads/requests/',
    mime          VARCHAR(60)  NOT NULL,
    size          INT UNSIGNED NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY request_documents_request_index (request_id),
    CONSTRAINT request_documents_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique : création, changements de statut, notes. visible = 1 : affiché au client.
CREATE TABLE request_events (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    type        ENUM('creation', 'statut', 'note') NOT NULL,
    status      VARCHAR(20)  NULL,
    message     TEXT         NULL,
    visible     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY request_events_request_index (request_id, created_at),
    CONSTRAINT request_events_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT request_events_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference      VARCHAR(20)  NOT NULL,
    user_id        BIGINT UNSIGNED NULL COMMENT 'Null pour un visiteur sans compte',
    motive         ENUM('orientation-filieres', 'orientation-metiers', 'renseignement', 'admission', 'logement', 'prise-en-charge', 'visa', 'voyage') NOT NULL,
    mode           ENUM('agence-dakar', 'agence-ziguinchor', 'telephone', 'visio', 'whatsapp') NOT NULL,
    preferred_date DATE         NOT NULL,
    preferred_slot ENUM('matin', 'apres-midi') NOT NULL,
    first_name     VARCHAR(80)  NOT NULL,
    last_name      VARCHAR(80)  NOT NULL,
    email          VARCHAR(190) NOT NULL,
    phone          VARCHAR(30)  NOT NULL,
    message        TEXT         NULL,
    status         ENUM('demande', 'confirme', 'termine', 'annule') NOT NULL DEFAULT 'demande',
    scheduled_at   DATETIME     NULL COMMENT 'Date et heure confirmées par l''agence',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY appointments_reference_unique (reference),
    KEY appointments_user_index (user_id),
    KEY appointments_status_index (status, preferred_date),
    CONSTRAINT appointments_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
