-- Retour arrière de 012_agenda.sql. Arrêter les écritures et sauvegarder avant exécution.
-- Les disponibilités, l'historique et les champs propres à 2B sont supprimés. Les données historiques
-- de rendez-vous, dont scheduled_at, sont conservées. Les statuts propres à 2B perdent leur distinction.

DROP TABLE IF EXISTS appointment_events;
DROP TABLE IF EXISTS availability_exceptions;
DROP TABLE IF EXISTS availability_rules;

-- Réintroduire l'ancien statut avant conversion.
ALTER TABLE appointments
    MODIFY status ENUM('demande', 'confirme', 'honore', 'absent', 'reporte', 'annule', 'termine') NOT NULL DEFAULT 'demande',
    DROP KEY appointments_active_start_unique,
    DROP COLUMN active_starts_at_utc;

UPDATE appointments SET status = 'termine', updated_at = updated_at WHERE status IN ('honore', 'absent');
UPDATE appointments SET status = 'annule', updated_at = updated_at WHERE status = 'reporte';

ALTER TABLE appointments
    DROP FOREIGN KEY appointments_assigned_fk,
    DROP FOREIGN KEY appointments_agency_fk,
    DROP FOREIGN KEY appointments_rescheduled_to_fk;

ALTER TABLE appointments
    MODIFY status ENUM('demande', 'confirme', 'termine', 'annule') NOT NULL DEFAULT 'demande',
    DROP KEY appointments_assigned_start_index,
    DROP KEY appointments_agency_start_index,
    DROP KEY appointments_rescheduled_to_index,
    DROP COLUMN assigned_to,
    DROP COLUMN agency_id,
    DROP COLUMN starts_at_utc,
    DROP COLUMN ends_at_utc,
    DROP COLUMN rescheduled_to_id,
    DROP COLUMN reminded_at;

ALTER TABLE agencies DROP COLUMN timezone;

DELETE FROM migrations WHERE name = '012_agenda.sql';
