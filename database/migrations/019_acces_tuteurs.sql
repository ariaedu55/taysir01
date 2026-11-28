-- Une autorisation de lecture par dossier et compte parent. Le dépôt ciblé est facultatif.
-- Une révocation conserve la décision initiale. Un nouvel octroi reçoit un nouvel identifiant.
CREATE TABLE request_tutor_access (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id     BIGINT UNSIGNED NOT NULL,
    parent_user_id BIGINT UNSIGNED NOT NULL,
    can_deposit    TINYINT(1) NOT NULL DEFAULT 0,
    granted_by     BIGINT UNSIGNED NOT NULL,
    granted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verification_reference VARCHAR(100) NOT NULL,
    revoked_by     BIGINT UNSIGNED NULL,
    revoked_at     DATETIME NULL,
    active_parent_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN parent_user_id END) STORED,
    UNIQUE KEY request_tutor_one_active (request_id, active_parent_id),
    KEY request_tutor_parent_active (active_parent_id, request_id),
    CONSTRAINT request_tutor_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT,
    CONSTRAINT request_tutor_parent_fk FOREIGN KEY (parent_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT request_tutor_granted_by_fk FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT request_tutor_revoked_by_fk FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT request_tutor_revocation_state CHECK ((revoked_by IS NULL) = (revoked_at IS NULL)),
    CONSTRAINT request_tutor_deposit_flag CHECK (can_deposit IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
