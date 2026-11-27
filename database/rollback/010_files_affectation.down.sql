-- Retour arrière de 010_files_affectation.sql (docs/gestion-phase-1.md, 1B bis).
-- Les dossiers « dans la file » (user_id NULL) n'ont pas d'équivalent avant 010 : ces lignes sont retirées
-- (le dossier redevient « à prendre » selon l'ancienne règle).
DELETE FROM request_assignments WHERE user_id IS NULL;
ALTER TABLE request_assignments DROP FOREIGN KEY request_assignments_queued_fk;
ALTER TABLE request_assignments DROP FOREIGN KEY request_assignments_user_fk;
ALTER TABLE request_assignments DROP COLUMN queued_by, DROP COLUMN queued_at, MODIFY user_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE request_assignments ADD CONSTRAINT request_assignments_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT;
DELETE FROM migrations WHERE name = '010_files_affectation.sql';
