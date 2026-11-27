-- Phase 1B (docs/gestion-phase-1.md) : affectation des dossiers par poste et journal d'audit.
-- Voir docs/gestion-plan.md §3.2 (chaîne de travail), §3.4 (périmètre) et §8 (journal).

-- Un responsable par poste et par dossier, comme les colonnes *_id de GAOV (conseiller_id, agent_orientation_id, agent_id).
-- C'est la source du filtre de périmètre. service_requests.assigned_to reste synchronisé avec le poste « conseiller ».
CREATE TABLE request_assignments (
    request_id  BIGINT UNSIGNED NOT NULL,
    poste       ENUM('conseiller', 'orientation', 'traitement') NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, poste),
    KEY request_assignments_user_index (user_id, poste),
    CONSTRAINT request_assignments_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT,
    CONSTRAINT request_assignments_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT request_assignments_author_fk FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reprise : les attributions actuelles deviennent l'affectation « conseiller ».
INSERT INTO request_assignments (request_id, poste, user_id)
    SELECT id, 'conseiller', assigned_to FROM service_requests WHERE assigned_to IS NOT NULL;

-- Journal d'audit : accès et modifications sensibles. On n'y écrit jamais de contenu de pièce ni de mot de passe.
CREATE TABLE audit_log (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NULL COMMENT 'Auteur ; null pour un visiteur non connecté',
    action       VARCHAR(40)  NOT NULL COMMENT 'connexion, acces_refuse, piece_telechargee, affectation…',
    subject_type VARCHAR(30)  NULL,
    subject_ref  VARCHAR(40)  NULL,
    detail       JSON         NULL,
    ip           VARCHAR(45)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY audit_log_action_index (action, created_at),
    KEY audit_log_subject_index (subject_type, subject_ref),
    KEY audit_log_user_index (user_id, created_at),
    CONSTRAINT audit_log_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
