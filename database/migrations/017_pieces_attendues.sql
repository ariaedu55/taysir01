-- Les pièces attendues sont initialisées à partir des étapes figées à la première consultation.
CREATE TABLE request_requirements (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id     BIGINT UNSIGNED NOT NULL,
    title          VARCHAR(190) NOT NULL,
    source         ENUM('procedure', 'manual') NOT NULL DEFAULT 'manual',
    status         ENUM('manquante', 'deposee', 'validee', 'refusee') NOT NULL DEFAULT 'manquante',
    document_id    BIGINT UNSIGNED NULL,
    refusal_reason TEXT NULL,
    expires_on     DATE NULL,
    minimum_valid_until DATE NULL,
    UNIQUE KEY request_requirements_title_unique (request_id, title),
    UNIQUE KEY request_requirements_identity_unique (request_id, id),
    CONSTRAINT request_requirements_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT,
    CONSTRAINT request_requirements_document_state CHECK ((status = 'manquante') = (document_id IS NULL)),
    CONSTRAINT request_requirements_refusal_state CHECK (
        (status = 'refusee' AND refusal_reason IS NOT NULL AND CHAR_LENGTH(TRIM(refusal_reason)) > 0)
        OR (status <> 'refusee' AND refusal_reason IS NULL)
    ),
    CONSTRAINT request_requirements_expiry_state CHECK (expires_on IS NULL OR status = 'validee')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chaque dépôt garde son lien vers la pièce attendue. Le pointeur courant ne peut viser
-- qu'un fichier du même dossier et de la même pièce.
ALTER TABLE request_documents
    ADD COLUMN requirement_id BIGINT UNSIGNED NULL,
    ADD UNIQUE KEY request_documents_submission_unique (request_id, requirement_id, id),
    ADD CONSTRAINT request_documents_requirement_fk FOREIGN KEY (request_id, requirement_id)
        REFERENCES request_requirements (request_id, id) ON DELETE RESTRICT;

ALTER TABLE request_requirements
    ADD CONSTRAINT request_requirements_document_fk FOREIGN KEY (request_id, id, document_id)
        REFERENCES request_documents (request_id, requirement_id, id) ON DELETE RESTRICT;
