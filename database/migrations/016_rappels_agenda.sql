ALTER TABLE mail_outbox
    ADD COLUMN appointment_start_utc DATETIME NULL,
    ADD COLUMN expires_at_utc DATETIME NULL;

ALTER TABLE appointment_cancellation_tokens
    ADD KEY appointment_cancellation_appointment_index (appointment_id),
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (token_hash),
    DROP KEY token_hash;
