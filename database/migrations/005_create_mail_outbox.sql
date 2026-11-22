-- File d'attente des e-mails : envoyés après la réponse au navigateur, relancés par bin/send-mails.php.
-- Voir docs/notifications-email.md.

CREATE TABLE mail_outbox (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind             VARCHAR(40)  NOT NULL COMMENT 'Modèle utilisé (request-created, appointment-agency…)',
    related          VARCHAR(30)  NULL COMMENT 'Référence liée (TAY-…, RDV-…) pour le suivi',
    recipient        VARCHAR(190) NOT NULL,
    recipient_name   VARCHAR(160) NULL,
    reply_to         VARCHAR(190) NULL,
    subject          VARCHAR(200) NOT NULL,
    html             MEDIUMTEXT   NOT NULL,
    text             MEDIUMTEXT   NOT NULL,
    status           ENUM('en-attente', 'envoye', 'echec') NOT NULL DEFAULT 'en-attente',
    attempts         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error       VARCHAR(255) NULL,
    next_attempt_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at          DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY mail_outbox_queue_index (status, next_attempt_at),
    KEY mail_outbox_created_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
