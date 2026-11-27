-- Phase 1B bis (docs/gestion-phase-1.md, revue docs/gestion-revue-1b.md point 2) : file d'attente par poste.
-- request_assignments.user_id NULL = le dossier est DANS LA FILE de ce poste (transmis, pas encore pris).
-- Prise atomique : UPDATE … WHERE user_id IS NULL (une seule prise réussit), ou INSERT contre la clé (dossier, poste).
-- Reprise après interruption : voir docs/gestion-phase-1.md, « Migrations interrompues ».

ALTER TABLE request_assignments DROP FOREIGN KEY request_assignments_user_fk;
ALTER TABLE request_assignments
    MODIFY user_id BIGINT UNSIGNED NULL COMMENT 'NULL : dans la file du poste, à prendre',
    ADD COLUMN queued_at DATETIME NULL COMMENT 'Mis dans la file le' AFTER assigned_at,
    ADD COLUMN queued_by BIGINT UNSIGNED NULL COMMENT 'Mis dans la file par' AFTER queued_at;
ALTER TABLE request_assignments
    ADD CONSTRAINT request_assignments_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    ADD CONSTRAINT request_assignments_queued_fk FOREIGN KEY (queued_by) REFERENCES users (id) ON DELETE RESTRICT;
