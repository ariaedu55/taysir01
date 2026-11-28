-- Phase 2, étape 2D (docs/gestion-phase-2.md, « 2D Plan détaillé », F4) : tâches minimales de l'équipe.
-- Une tâche a un responsable (obligatoire), un auteur, une échéance facultative, et peut viser une personne ou un dossier.
-- Jamais visible du client. Pas de suppression : elle se coche ou se rouvre. Clés étrangères en RESTRICT.
-- Retour arrière : database/rollback/015_taches.down.sql (garde-fou : refus s'il existe une tâche).

CREATE TABLE IF NOT EXISTS tasks (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(190) NOT NULL,
    due_date    DATE NULL,
    person_id   BIGINT UNSIGNED NULL,
    request_id  BIGINT UNSIGNED NULL,
    assigned_to BIGINT UNSIGNED NOT NULL,
    created_by  BIGINT UNSIGNED NOT NULL,
    done_at     DATETIME NULL,
    done_by     BIGINT UNSIGNED NULL,
    form_token  CHAR(64) NULL COMMENT 'Jeton du formulaire de création (double soumission, G9)',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY tasks_form_token_unique (form_token),
    KEY tasks_assigned_index (assigned_to, done_at, due_date),
    KEY tasks_created_by_index (created_by),
    KEY tasks_person_fk (person_id),
    KEY tasks_request_fk (request_id),
    KEY tasks_done_by_fk (done_by),
    CONSTRAINT tasks_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
    CONSTRAINT tasks_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT,
    CONSTRAINT tasks_assigned_fk FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT tasks_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT tasks_done_by_fk FOREIGN KEY (done_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
