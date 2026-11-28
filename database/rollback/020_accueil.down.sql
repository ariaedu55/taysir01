SELECT (SELECT 1 FROM reception_visits UNION ALL SELECT 1 FROM reception_visits) AS garde_fou_visites;
SELECT (SELECT 1 FROM people WHERE merged_into IS NOT NULL UNION ALL SELECT 1 FROM people WHERE merged_into IS NOT NULL) AS garde_fou_fusions;
DROP TABLE reception_visits;
ALTER TABLE people DROP FOREIGN KEY people_merged_fk, DROP KEY people_merged_idx, DROP COLUMN merged_into;
DELETE FROM migrations WHERE name = '020_accueil.sql';
