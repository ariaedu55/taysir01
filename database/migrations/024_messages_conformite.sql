-- Phase 7 (docs/gestion-phase-7.md) : modèles de messages de l'équipe et journal des envois (lot 7A),
-- marque d'effacement d'une personne (lot 7C). Clés étrangères en RESTRICT.
-- Retour arrière : database/rollback/024_messages_conformite.down.sql (garde-fous : journal non vide, personne effacée).

CREATE TABLE IF NOT EXISTS message_templates (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(120) NOT NULL,
    body       TEXT NOT NULL,
    channel    ENUM('whatsapp', 'email', 'les-deux') NOT NULL DEFAULT 'les-deux',
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY message_templates_created_by_fk (created_by),
    CONSTRAINT message_templates_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des messages ouverts par l'équipe (WhatsApp ou e-mail) : jamais le texte, seulement qui, quoi, quand.
CREATE TABLE IF NOT EXISTS message_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id   BIGINT UNSIGNED NOT NULL,
    request_id  BIGINT UNSIGNED NULL,
    template_id BIGINT UNSIGNED NULL,
    channel     ENUM('whatsapp', 'email') NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY message_logs_person_index (person_id, created_at),
    KEY message_logs_request_fk (request_id),
    KEY message_logs_template_fk (template_id),
    KEY message_logs_user_fk (user_id),
    CONSTRAINT message_logs_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
    CONSTRAINT message_logs_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT,
    CONSTRAINT message_logs_template_fk FOREIGN KEY (template_id) REFERENCES message_templates (id) ON DELETE RESTRICT,
    CONSTRAINT message_logs_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE people ADD COLUMN erased_at DATETIME NULL COMMENT 'Anonymisée à la demande (phase 7, lot 7C)';

-- Modèles de départ, à faire relire par l'agence.
INSERT INTO message_templates (title, body, channel)
SELECT 'Pièce manquante', 'Bonjour {prenom}, pour avancer sur votre dossier {reference}, il nous manque : {piece}. Vous pouvez la déposer dans votre espace Taysir ou nous l’envoyer ici. Merci ! L’équipe Taysir ({agence})', 'les-deux'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE title = 'Pièce manquante');

INSERT INTO message_templates (title, body, channel)
SELECT 'Relance de paiement', 'Bonjour {prenom}, un règlement est attendu pour votre dossier {reference} ; il reste {solde} à régler. N’hésitez pas à nous contacter pour en parler. L’équipe Taysir ({agence})', 'les-deux'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE title = 'Relance de paiement');

INSERT INTO message_templates (title, body, channel)
SELECT 'Rappel de rendez-vous', 'Bonjour {prenom}, nous vous rappelons votre rendez-vous Taysir le {date_rdv}. À bientôt ! L’équipe Taysir ({agence})', 'whatsapp'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE title = 'Rappel de rendez-vous');

INSERT INTO message_templates (title, body, channel)
SELECT 'Prise de contact', 'Bonjour {prenom}, ici l’équipe Taysir Orientation Voyage ({agence}). Nous revenons vers vous au sujet de votre projet d’études. Quand pouvons-nous vous appeler ?', 'les-deux'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE title = 'Prise de contact');
