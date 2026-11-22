-- Articles : fiches métiers, écoles de formation professionnelle, offres, bourses, salons.
-- Importés depuis WordPress par database/import/wordpress-articles.php, puis gérables depuis le back-office.
-- Voir docs/metiers-orientation.md.

CREATE TABLE articles (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(190) NOT NULL COMMENT 'Adresse : /<slug>/ (identique à WordPress)',
    title         VARCHAR(255) NOT NULL,
    type          ENUM('metier', 'ecole', 'offre-emploi', 'offre-stage', 'bourse', 'formation', 'salon', 'actualite') NOT NULL,
    sector        VARCHAR(40)  NULL COMMENT 'Clé de config/sectors.php pour les fiches métiers',
    excerpt       TEXT         NULL,
    intro         MEDIUMTEXT   NULL COMMENT 'HTML nettoyé',
    sections      JSON         NULL COMMENT 'Liste de {title, html}',
    gallery       JSON         NULL COMMENT 'Liste de chemins de médias (wp-content/uploads)',
    image         VARCHAR(255) NULL COMMENT 'Image de couverture (chemin sous wp-content/uploads)',
    status        ENUM('publie', 'archive', 'brouillon') NOT NULL DEFAULT 'publie',
    published_at  DATETIME     NOT NULL,
    wordpress_id  BIGINT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY articles_slug_unique (slug),
    KEY articles_type_index (type, status),
    KEY articles_sector_index (sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
