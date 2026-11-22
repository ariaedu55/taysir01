-- Comptes de la plateforme : clients (élèves, étudiants, parents, particuliers), conseillers et administrateurs.
-- Voir docs/vision-plateforme.md et docs/authentification.md.

CREATE TABLE users (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name        VARCHAR(80)  NOT NULL,
    last_name         VARCHAR(80)  NOT NULL,
    email             VARCHAR(190) NOT NULL,
    phone             VARCHAR(30)  NULL,
    profile           ENUM('eleve', 'etudiant', 'parent', 'particulier') NOT NULL DEFAULT 'etudiant',
    role              ENUM('client', 'conseiller', 'admin') NOT NULL DEFAULT 'client',
    password_hash     VARCHAR(255) NOT NULL,
    consent_at        DATETIME     NOT NULL COMMENT 'Acceptation des conditions et du traitement des données',
    email_verified_at DATETIME     NULL,
    last_login_at     DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email),
    KEY users_role_index (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons de réinitialisation du mot de passe (seule l'empreinte SHA-256 du jeton est stockée).
CREATE TABLE password_resets (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64)  NOT NULL,
    expires_at DATETIME  NOT NULL,
    used_at    DATETIME  NULL,
    created_at DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY password_resets_token_unique (token_hash),
    CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
