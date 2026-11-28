-- Retour arrière de 015_taches.sql (docs/gestion-phase-2.md, 2D). Arrêter les écritures et sauvegarder avant.
-- Garde-fou portable (MySQL et MariaDB) : la sous-requête renvoie deux lignes, donc échoue (erreur 1242), dès qu'une
-- tâche existe ; le retour arrière s'arrête alors avant toute modification.
SELECT (SELECT 1 FROM tasks UNION ALL SELECT 1 FROM tasks) AS garde_fou_taches;
DROP TABLE IF EXISTS tasks;
DELETE FROM migrations WHERE name = '015_taches.sql';
