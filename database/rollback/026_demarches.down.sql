-- Retour arrière de 026_demarches.sql.
-- Garde-fou : refusé (erreur 1242) si un dossier porte un motif ou si une pièce est « non demandée » : ces choix
-- seraient perdus. Les démarches de départ sont retirées seulement si aucun dossier ne les utilise.
SELECT (SELECT 1 FROM service_requests WHERE purpose IS NOT NULL
        UNION ALL SELECT 1 FROM service_requests WHERE purpose IS NOT NULL
        UNION ALL SELECT 1 FROM request_requirements WHERE status = 'non-demandee'
        UNION ALL SELECT 1 FROM request_requirements WHERE status = 'non-demandee' LIMIT 2) AS garde_fou;
DELETE FROM procedure_templates
 WHERE template_key IN ('demarche-admission', 'demarche-visa', 'demarche-visa-france-etudes', 'demarche-logement',
                        'demarche-prise-en-charge', 'demarche-billet', 'demarche-hotel')
   AND id NOT IN (SELECT template_id FROM service_requests WHERE template_id IS NOT NULL);
ALTER TABLE request_requirements
    DROP CONSTRAINT request_requirements_document_state,
    ADD CONSTRAINT request_requirements_document_state CHECK ((status = 'manquante') = (document_id IS NULL));
ALTER TABLE request_requirements
    MODIFY status ENUM('manquante', 'deposee', 'validee', 'refusee') NOT NULL DEFAULT 'manquante',
    DROP COLUMN waived_reason, DROP COLUMN provider, DROP COLUMN optional, DROP COLUMN hint;
ALTER TABLE service_requests DROP COLUMN purpose;
ALTER TABLE procedure_templates
    DROP KEY procedure_templates_match_index, DROP KEY procedure_templates_combo_unique,
    DROP COLUMN published_combo, DROP COLUMN documents, DROP COLUMN purpose;
DROP TABLE IF EXISTS covered_countries;
DELETE FROM migrations WHERE name = '026_demarches.sql';
