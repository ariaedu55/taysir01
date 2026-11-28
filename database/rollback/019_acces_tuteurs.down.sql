-- Exécuter sans --force. Le retour arrière s'arrête si une décision doit être conservée.
SELECT (SELECT 1 FROM request_tutor_access UNION ALL SELECT 1 FROM request_tutor_access) AS garde_fou_acces_tuteurs;

DROP TABLE request_tutor_access;
DELETE FROM migrations WHERE name = '019_acces_tuteurs.sql';
