CREATE TABLE appointment_durations (
    motive VARCHAR(30) PRIMARY KEY,
    minutes SMALLINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO appointment_durations (motive, minutes) VALUES
    ('orientation-filieres', 45), ('orientation-metiers', 45), ('renseignement', 30),
    ('admission', 60), ('visa', 30), ('logement', 30), ('prise-en-charge', 30), ('voyage', 30);

CREATE TABLE appointment_cancellation_tokens (
    appointment_id BIGINT UNSIGNED PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    CONSTRAINT appointment_cancellation_fk FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
