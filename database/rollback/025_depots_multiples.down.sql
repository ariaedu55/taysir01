-- Retour arrière de 025_depots_multiples.sql.
-- Garde-fou : refusé (erreur 1242) si un envoi compte plusieurs fichiers, car leur regroupement serait perdu.
SELECT (SELECT 1 FROM request_documents WHERE deposit_batch IS NOT NULL GROUP BY request_id, deposit_batch HAVING COUNT(*) > 1
        UNION ALL SELECT 1 FROM request_documents WHERE deposit_batch IS NOT NULL GROUP BY request_id, deposit_batch HAVING COUNT(*) > 1) AS garde_fou;
ALTER TABLE request_documents DROP KEY request_documents_batch_index, DROP COLUMN deposit_batch;
DELETE FROM migrations WHERE name = '025_depots_multiples.sql';
