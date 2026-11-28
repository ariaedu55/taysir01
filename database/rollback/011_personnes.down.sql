-- Retour arrière de 011_personnes.sql (docs/gestion-phase-2.md, 2A).
-- Garde-fou : si un dossier a été ouvert sans compte (service_requests.user_id NULL), l'instruction qui remet
-- user_id en NOT NULL échoue et le retour arrière s'arrête là, sans rien perdre. Ces dossiers n'ont pas
-- d'équivalent avant 011 : les traiter à la main avant de relancer.
-- Les événements gardent la valeur de visible qu'ils ont reçue ; seule la valeur par défaut revient à 1.
-- Garde-fou portable (MySQL et MariaDB) : la sous-requête renvoie deux lignes, donc échoue (erreur 1242), dès qu'un
-- dossier n'a pas de compte. Sans lui, MODIFY … NOT NULL remplacerait ces NULL par 0 au lieu d'échouer.
SELECT (SELECT 1 FROM service_requests WHERE user_id IS NULL UNION ALL SELECT 1 FROM service_requests WHERE user_id IS NULL) AS garde_fou_dossiers_sans_compte;
-- Les fiches créées après la reprise doivent être préservées avant de supprimer la table.
SELECT (SELECT 1 FROM people WHERE origin <> 'reprise' UNION ALL SELECT 1 FROM people WHERE origin <> 'reprise') AS garde_fou_fiches_nouvelles;
ALTER TABLE service_requests DROP FOREIGN KEY service_requests_user_fk;
ALTER TABLE service_requests MODIFY user_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE service_requests ADD CONSTRAINT service_requests_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT;
ALTER TABLE service_requests DROP FOREIGN KEY service_requests_person_fk;
ALTER TABLE service_requests DROP KEY service_requests_person_index, DROP COLUMN person_id;
ALTER TABLE appointments DROP FOREIGN KEY appointments_person_fk;
ALTER TABLE appointments DROP KEY appointments_person_index, DROP COLUMN person_id;
ALTER TABLE users DROP FOREIGN KEY users_person_fk;
ALTER TABLE users DROP KEY users_person_unique, DROP COLUMN person_id;
ALTER TABLE request_events MODIFY visible TINYINT(1) NOT NULL DEFAULT 1;
DROP TABLE IF EXISTS people;
DELETE FROM migrations WHERE name = '011_personnes.sql';
