-- Phase 1B (docs/gestion-phase-1.md) : les 8 postes de l'équipe, les agences et les missions nominatives.
-- Voir docs/gestion-plan.md §3.1 (postes), §3.3 et §3.6 (agences, cumuls).

-- Agences : Dakar (siège) active ; Ziguinchor et Paris créées inactives, activables depuis les paramètres.
CREATE TABLE agencies (
    id         TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20)  NOT NULL,
    name       VARCHAR(80)  NOT NULL,
    city       VARCHAR(80)  NOT NULL,
    is_head    TINYINT(1)   NOT NULL DEFAULT 0,
    active     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY agencies_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO agencies (code, name, city, is_head, active) VALUES
    ('dakar', 'Siège de Dakar', 'Dakar', 1, 1),
    ('ziguinchor', 'Agence de Ziguinchor', 'Ziguinchor', 0, 0),
    ('paris', 'Relais de Paris', 'Paris', 0, 0);

-- Un poste par personne : les 8 postes réels de GAOV, plus le client.
-- Les valeurs existantes (client, conseiller, admin) restent valides.
ALTER TABLE users
    MODIFY role ENUM('client', 'admin', 'secretaire', 'prospection', 'commerciale', 'conseiller', 'orientation', 'traitement', 'comptable') NOT NULL DEFAULT 'client',
    ADD COLUMN agency_id TINYINT UNSIGNED NULL AFTER role,
    ADD CONSTRAINT users_agency_fk FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT;

-- Les membres de l'équipe existants sont rattachés au siège, sans toucher à leur date de modification
-- (updated_at est en ON UPDATE CURRENT_TIMESTAMP : un retour arrière ne pourrait pas la restaurer).
UPDATE users SET agency_id = (SELECT id FROM agencies WHERE code = 'dakar'), updated_at = updated_at WHERE role <> 'client';

-- Missions additionnelles nominatives (§3.6) : un poste par personne, plus des missions tracées,
-- attribuées et retirées par un administrateur (jamais supprimées : l'historique reste).
CREATE TABLE user_missions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    mission     ENUM('encaissement', 'orientation', 'prospection', 'traitement') NOT NULL,
    granted_by  BIGINT UNSIGNED NOT NULL,
    granted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by  BIGINT UNSIGNED NULL,
    revoked_at  DATETIME NULL,
    KEY user_missions_user_index (user_id, revoked_at),
    CONSTRAINT user_missions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT user_missions_granted_fk FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT user_missions_revoked_fk FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
