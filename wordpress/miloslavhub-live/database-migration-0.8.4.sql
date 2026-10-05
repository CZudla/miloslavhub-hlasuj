-- MiloslavHub Live 0.8.4
-- Pouzijte pri prechodu z 0.8.3 na 0.8.4.
ALTER TABLE mhl_participants
  ADD COLUMN IF NOT EXISTS hall_of_fame_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER expires_at,
  ADD COLUMN IF NOT EXISTS hall_opted_at DATETIME NULL AFTER hall_of_fame_opt_in;
