CREATE TABLE IF NOT EXISTS finance_quotes (
    request_id BIGINT UNSIGNED PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    amount INT UNSIGNED NOT NULL,
    CONSTRAINT finance_quote_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE RESTRICT,
    CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_installments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount INT UNSIGNED NOT NULL,
    UNIQUE KEY finance_installment_date (request_id, due_date),
    CONSTRAINT finance_installment_quote_fk FOREIGN KEY (request_id) REFERENCES finance_quotes (request_id) ON DELETE RESTRICT,
    CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_sequences (
    agency_id TINYINT UNSIGNED NOT NULL,
    receipt_year SMALLINT UNSIGNED NOT NULL,
    last_number INT UNSIGNED NOT NULL,
    PRIMARY KEY (agency_id, receipt_year),
    CONSTRAINT finance_sequence_agency_fk FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    receipt VARCHAR(40) NOT NULL UNIQUE,
    form_token CHAR(64) NOT NULL UNIQUE,
    amount INT UNSIGNED NOT NULL,
    method VARCHAR(20) NOT NULL,
    received_on DATE NOT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(161) NOT NULL,
    case_reference VARCHAR(40) NOT NULL,
    CONSTRAINT finance_payment_quote_fk FOREIGN KEY (request_id) REFERENCES finance_quotes (request_id) ON DELETE RESTRICT,
    CONSTRAINT finance_payment_user_fk FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE RESTRICT,
    CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_cancellations (
    payment_id BIGINT UNSIGNED PRIMARY KEY,
    reason VARCHAR(500) NOT NULL,
    cancelled_by BIGINT UNSIGNED NOT NULL,
    cancelled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT finance_cancellation_payment_fk FOREIGN KEY (payment_id) REFERENCES finance_payments (id) ON DELETE RESTRICT,
    CONSTRAINT finance_cancellation_user_fk FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
