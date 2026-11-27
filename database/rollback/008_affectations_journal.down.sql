-- Retour arrière de 008_affectations_journal.sql. service_requests.assigned_to garde le conseiller :
-- l'état d'avant (attribution à un conseiller) est conservé. Le journal d'audit est supprimé :
-- l'exporter d'abord s'il doit être gardé (SELECT * FROM audit_log).
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS request_assignments;
DELETE FROM migrations WHERE name = '008_affectations_journal.sql';
