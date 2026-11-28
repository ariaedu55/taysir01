SELECT (SELECT 1 FROM request_deadlines UNION ALL SELECT 1 FROM request_deadlines) AS garde_fou_echeances;
SELECT (SELECT 1 FROM request_admission_results UNION ALL SELECT 1 FROM request_admission_results) AS garde_fou_admissions;
SELECT (SELECT 1 FROM mail_outbox WHERE CHAR_LENGTH(related) > 30 UNION ALL SELECT 1 FROM mail_outbox WHERE CHAR_LENGTH(related) > 30) AS garde_fou_references;
ALTER TABLE mail_outbox MODIFY related VARCHAR(30) NULL COMMENT 'Référence liée (TAY-…, RDV-…) pour le suivi';
DROP TABLE request_admission_results;
DROP TABLE request_deadlines;
DELETE FROM migrations WHERE name = '018_suivi_dossiers.sql';
