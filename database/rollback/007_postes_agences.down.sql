-- Retour arrière de 007_postes_agences.sql. Les comptes créés sur un des nouveaux postes (secrétaire, comptable…)
-- n'ont pas d'équivalent avant 007 : ils deviennent « conseiller », le seul rôle d'équipe non administrateur d'alors.
DROP TABLE IF EXISTS user_missions;
ALTER TABLE users DROP FOREIGN KEY users_agency_fk;
ALTER TABLE users DROP COLUMN agency_id;
UPDATE users SET role = 'conseiller' WHERE role NOT IN ('client', 'conseiller', 'admin');
ALTER TABLE users MODIFY role ENUM('client', 'conseiller', 'admin') NOT NULL DEFAULT 'client';
DROP TABLE IF EXISTS agencies;
DELETE FROM migrations WHERE name = '007_postes_agences.sql';
