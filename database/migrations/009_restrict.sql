-- Phase 1B (docs/gestion-phase-1.md, docs/gestion-plan.md §4.4) : on désactive, on ne supprime pas.
-- Les cascades qui effaceraient des dossiers, leurs pièces et leur historique sont remplacées par RESTRICT :
-- la base refuse désormais toute suppression accidentelle d'un compte qui a des dossiers.

ALTER TABLE service_requests DROP FOREIGN KEY service_requests_user_fk;
ALTER TABLE service_requests ADD CONSTRAINT service_requests_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT;

ALTER TABLE request_documents DROP FOREIGN KEY request_documents_request_fk;
ALTER TABLE request_documents ADD CONSTRAINT request_documents_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT;

ALTER TABLE request_events DROP FOREIGN KEY request_events_request_fk;
ALTER TABLE request_events ADD CONSTRAINT request_events_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT;
