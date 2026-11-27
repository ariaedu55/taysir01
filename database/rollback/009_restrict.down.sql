-- Retour arrière de 009_restrict.sql : les cascades d'origine reviennent (état de 003).
ALTER TABLE service_requests DROP FOREIGN KEY service_requests_user_fk;
ALTER TABLE service_requests ADD CONSTRAINT service_requests_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;
ALTER TABLE request_documents DROP FOREIGN KEY request_documents_request_fk;
ALTER TABLE request_documents ADD CONSTRAINT request_documents_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE;
ALTER TABLE request_events DROP FOREIGN KEY request_events_request_fk;
ALTER TABLE request_events ADD CONSTRAINT request_events_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE;
DELETE FROM migrations WHERE name = '009_restrict.sql';
