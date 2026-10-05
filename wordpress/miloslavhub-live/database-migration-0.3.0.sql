-- Upgrade MiloslavHub Live database from 0.2.1 to 0.3.0.
-- Run once in the dedicated voting database via phpMyAdmin using a database account with ALTER privileges.
ALTER TABLE mhl_runs
  ADD COLUMN IF NOT EXISTS subject_id BIGINT UNSIGNED NULL AFTER lecture_id,
  ADD COLUMN IF NOT EXISTS mode VARCHAR(10) NOT NULL DEFAULT 'live' AFTER title;
ALTER TABLE mhl_sessions
  ADD COLUMN IF NOT EXISTS mode VARCHAR(10) NOT NULL DEFAULT 'live' AFTER question_id;
ALTER TABLE mhl_votes
  ADD COLUMN IF NOT EXISTS mode VARCHAR(10) NOT NULL DEFAULT 'live' AFTER question_id;
ALTER TABLE mhl_runs ADD INDEX IF NOT EXISTS subject_id (subject_id), ADD INDEX IF NOT EXISTS mode (mode);
ALTER TABLE mhl_sessions ADD INDEX IF NOT EXISTS mode (mode);
ALTER TABLE mhl_votes ADD INDEX IF NOT EXISTS mode (mode);
UPDATE mhl_runs SET mode='live' WHERE mode IS NULL OR mode='';
UPDATE mhl_sessions SET mode='live' WHERE mode IS NULL OR mode='';
UPDATE mhl_votes SET mode='live' WHERE mode IS NULL OR mode='';
