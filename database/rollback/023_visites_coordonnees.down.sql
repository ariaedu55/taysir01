-- Retour arrière de 023_visites_coordonnees.sql. Les coordonnées notées sur les visites sont perdues (celles reprises
-- sur les fiches restent) : sauvegarder avant.
ALTER TABLE reception_visits DROP COLUMN email, DROP COLUMN phone;
DELETE FROM migrations WHERE name = '023_visites_coordonnees.sql';
