SELECT (SELECT 1 FROM finance_quotes UNION ALL SELECT 1 FROM finance_quotes) AS garde_fou_finance;
SELECT (SELECT 1 FROM finance_sequences UNION ALL SELECT 1 FROM finance_sequences) AS garde_fou_sequences;
DROP TABLE finance_cancellations, finance_payments, finance_installments, finance_sequences, finance_quotes;
DELETE FROM migrations WHERE name = '021_finance.sql';
