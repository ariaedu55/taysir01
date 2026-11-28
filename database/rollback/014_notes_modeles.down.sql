-- Retour arrière de 014_notes_modeles.sql (docs/gestion-phase-2.md, 2C). Arrêter les écritures et sauvegarder avant.
-- Garde-fous portables (MySQL et MariaDB) : chaque sous-requête renvoie deux lignes, donc échoue (erreur 1242), dès
-- qu'existe une donnée créée par la 2C, qui n'a pas d'équivalent avant 014. Le retour arrière s'arrête alors AVANT
-- toute modification ; ces données sont à traiter à la main avant de relancer.
SELECT (SELECT 1 FROM notes UNION ALL SELECT 1 FROM notes) AS garde_fou_notes;
SELECT (SELECT 1 FROM request_steps UNION ALL SELECT 1 FROM request_steps) AS garde_fou_etapes;
SELECT (SELECT 1 FROM service_requests WHERE email IS NULL OR phone IS NULL OR template_id IS NOT NULL OR opened_by IS NOT NULL
        UNION ALL SELECT 1 FROM service_requests WHERE email IS NULL OR phone IS NULL OR template_id IS NOT NULL OR opened_by IS NOT NULL) AS garde_fou_dossiers_equipe;
ALTER TABLE appointments DROP KEY appointments_email_normalized_index, DROP COLUMN email_normalized;
ALTER TABLE service_requests DROP FOREIGN KEY service_requests_template_fk;
ALTER TABLE service_requests DROP FOREIGN KEY service_requests_opened_by_fk;
ALTER TABLE service_requests
    DROP KEY service_requests_open_token_unique,
    DROP KEY service_requests_template_fk,
    DROP KEY service_requests_opened_by_fk,
    DROP COLUMN template_id,
    DROP COLUMN intake,
    DROP COLUMN opened_by,
    DROP COLUMN open_token,
    MODIFY email VARCHAR(190) NOT NULL,
    MODIFY phone VARCHAR(30) NOT NULL;
DROP TABLE IF EXISTS request_steps;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS procedure_templates;
DELETE FROM migrations WHERE name = '014_notes_modeles.sql';
