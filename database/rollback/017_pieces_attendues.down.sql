-- Les pièces issues de la procédure et encore manquantes seront recréées à la prochaine activation.
-- Arrêter avant tout ALTER si une demande manuelle, une décision ou un dépôt doit être conservé.
SELECT (SELECT 1 FROM request_requirements WHERE source = 'manual' OR status <> 'manquante'
    UNION ALL SELECT 1 FROM request_requirements WHERE source = 'manual' OR status <> 'manquante') AS garde_fou_pieces_attendues;
SELECT (SELECT 1 FROM request_documents WHERE requirement_id IS NOT NULL
    UNION ALL SELECT 1 FROM request_documents WHERE requirement_id IS NOT NULL) AS garde_fou_depots;

ALTER TABLE request_requirements DROP FOREIGN KEY request_requirements_document_fk;
ALTER TABLE request_documents
    DROP FOREIGN KEY request_documents_requirement_fk,
    DROP KEY request_documents_submission_unique,
    DROP COLUMN requirement_id;
DROP TABLE request_requirements;
DELETE FROM migrations WHERE name = '017_pieces_attendues.sql';
