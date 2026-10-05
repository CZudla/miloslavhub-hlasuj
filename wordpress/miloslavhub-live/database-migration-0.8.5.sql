-- MiloslavHub Live 0.8.5 - upgrade z 0.8.4
ALTER TABLE mhl_participants
  ADD COLUMN IF NOT EXISTS hall_visibility VARCHAR(12) NOT NULL DEFAULT 'unset' AFTER hall_opted_at;
