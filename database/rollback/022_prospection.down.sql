SELECT (SELECT 1 FROM prospect_schools UNION ALL SELECT 1 FROM prospect_schools) AS garde_fou_etablissements;
SELECT (SELECT 1 FROM prospect_campaigns UNION ALL SELECT 1 FROM prospect_campaigns) AS garde_fou_campagnes;
DROP TABLE prospect_appointments, prospect_events, prospects, prospect_campaigns, prospect_schools;
DELETE FROM migrations WHERE name = '022_prospection.sql';
