-- Comptes désactivés (membres de l'équipe partis) : connexion refusée, historique conservé.
-- Voir docs/comptes-equipe.md.

ALTER TABLE users
    ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN invited_by BIGINT UNSIGNED NULL AFTER active,
    ADD CONSTRAINT users_invited_by_fk FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL;
