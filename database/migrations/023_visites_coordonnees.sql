-- Revue du 28/11/2026 (docs/verifications/2026-11-28-revue-2d-a-phase-6.md, écart 4) : la visite note un moyen de
-- contact, repris sur la fiche créée. Additive ; retour arrière : database/rollback/023_visites_coordonnees.down.sql.
ALTER TABLE reception_visits
    ADD COLUMN phone VARCHAR(30) NULL AFTER last_name,
    ADD COLUMN email VARCHAR(190) NULL AFTER phone;
