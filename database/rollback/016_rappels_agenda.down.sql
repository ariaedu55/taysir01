-- Refuser la perte d'un rappel ou d'un lien supplémentaire avant le premier ALTER.
SELECT (SELECT 1 FROM mail_outbox WHERE appointment_start_utc IS NOT NULL
    UNION ALL SELECT 1 FROM mail_outbox WHERE appointment_start_utc IS NOT NULL) AS garde_fou_rappels;
SELECT (SELECT 1 FROM appointment_cancellation_tokens GROUP BY appointment_id HAVING COUNT(*) > 1
    UNION ALL SELECT 1 FROM appointment_cancellation_tokens GROUP BY appointment_id HAVING COUNT(*) > 1) AS garde_fou_liens;
ALTER TABLE appointment_cancellation_tokens
    ADD UNIQUE KEY token_hash (token_hash),
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (appointment_id),
    DROP KEY appointment_cancellation_appointment_index;
ALTER TABLE mail_outbox DROP COLUMN appointment_start_utc, DROP COLUMN expires_at_utc;
DELETE FROM migrations WHERE name = '016_rappels_agenda.sql';
