CREATE TABLE IF NOT EXISTS request_deadlines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    due_date DATE NOT NULL,
    completed_at DATETIME NULL,
    queued_at DATETIME NULL,
    UNIQUE KEY request_deadlines_unique (request_id, title, due_date),
    KEY request_deadlines_due (due_date, completed_at, queued_at),
    CONSTRAINT request_deadlines_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_admission_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    institution VARCHAR(190) NOT NULL,
    status ENUM('attente','admis','liste-attente','refuse') NOT NULL DEFAULT 'attente',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY request_admission_institution_unique (request_id, institution),
    CONSTRAINT request_admission_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mail_outbox MODIFY related VARCHAR(48) NULL COMMENT 'Référence liée ou paire échéance et destinataire';
