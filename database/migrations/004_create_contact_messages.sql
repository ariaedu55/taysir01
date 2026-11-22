-- Messages envoyés depuis la page Contact, traités dans le back-office (/admin/messages/).
-- Voir docs/pages-agence.md.

CREATE TABLE contact_messages (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NULL COMMENT 'Compte connecté au moment de l''envoi, sinon null',
    name        VARCHAR(160) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(30)  NOT NULL,
    message     TEXT         NOT NULL,
    status      ENUM('nouveau', 'traite') NOT NULL DEFAULT 'nouveau',
    handled_by  BIGINT UNSIGNED NULL,
    handled_at  DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY contact_messages_status_index (status, created_at),
    CONSTRAINT contact_messages_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT contact_messages_handler_fk FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
