ALTER TABLE people ADD COLUMN merged_into BIGINT UNSIGNED NULL,
    ADD KEY people_merged_idx (merged_into),
    ADD CONSTRAINT people_merged_fk FOREIGN KEY (merged_into) REFERENCES people (id) ON DELETE RESTRICT;

CREATE TABLE reception_visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    purpose VARCHAR(190) NOT NULL,
    state ENUM('attente','recu','termine') NOT NULL DEFAULT 'attente',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    KEY reception_day_idx (created_at, state),
    CONSTRAINT reception_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
    CONSTRAINT reception_creator_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
