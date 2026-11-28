-- Retour arrière de 024_messages_conformite.sql. Arrêter les écritures et sauvegarder avant.
-- Garde-fous portables (erreur 1242 dès qu'une ligne existe) : le journal des messages et l'effacement d'une personne
-- n'ont pas d'équivalent avant 024 ; le retour arrière s'arrête alors avant toute modification.
SELECT (SELECT 1 FROM message_logs UNION ALL SELECT 1 FROM message_logs) AS garde_fou_journal_messages;
SELECT (SELECT 1 FROM people WHERE erased_at IS NOT NULL UNION ALL SELECT 1 FROM people WHERE erased_at IS NOT NULL) AS garde_fou_personnes_effacees;
ALTER TABLE people DROP COLUMN erased_at;
DROP TABLE IF EXISTS message_logs;
DROP TABLE IF EXISTS message_templates;
DELETE FROM migrations WHERE name = '024_messages_conformite.sql';
