CREATE TABLE IF NOT EXISTS prospect_schools (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    city VARCHAR(100) NOT NULL,
    UNIQUE KEY prospect_school_name (agency_id, name, city),
    FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospect_campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    UNIQUE KEY prospect_campaign_name (agency_id, name, starts_on),
    FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id BIGINT UNSIGNED NOT NULL UNIQUE,
    agency_id TINYINT UNSIGNED NOT NULL,
    school_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(80) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    assigned_to BIGINT UNSIGNED NULL,
    state ENUM('nouveau','contacte','interesse','a-rappeler','non-interesse','transmis') NOT NULL DEFAULT 'nouveau',
    follow_up_on DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY prospect_assignee_followup (assigned_to, follow_up_on),
    KEY prospect_creator (created_by, agency_id),
    FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
    FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT,
    FOREIGN KEY (school_id) REFERENCES prospect_schools (id) ON DELETE RESTRICT,
    FOREIGN KEY (campaign_id) REFERENCES prospect_campaigns (id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospect_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prospect_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('attribution','appel','rendez-vous','transmission') NOT NULL,
    detail VARCHAR(1600) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE RESTRICT,
    FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospect_appointments (
    appointment_id BIGINT UNSIGNED PRIMARY KEY,
    prospect_id BIGINT UNSIGNED NOT NULL,
    proposed_by BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE RESTRICT,
    FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE RESTRICT,
    FOREIGN KEY (proposed_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
