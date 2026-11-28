-- Dépôt de plusieurs fichiers pour une même pièce attendue (28/11/2026, demande de l'utilisateur).
-- Les fichiers d'un même envoi partagent un jeton ; request_requirements.document_id reste le premier fichier de l'envoi
-- (ancre de la version examinée par l'équipe). Les dépôts antérieurs gardent NULL : un fichier par version.
-- Additive ; retour arrière : database/rollback/025_depots_multiples.down.sql.
ALTER TABLE request_documents
    ADD COLUMN deposit_batch CHAR(32) NULL AFTER requirement_id,
    ADD KEY request_documents_batch_index (request_id, deposit_batch);
