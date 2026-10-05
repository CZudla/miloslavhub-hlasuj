-- MiloslavHub Live 0.8.4
-- PRIME PRECHOD Z 0.8.1. Soubor je zamerne tolerantni, pokud by nektera meziverze byla omylem castecne aplikovana.
CREATE TABLE IF NOT EXISTS mhl_participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id BIGINT UNSIGNED NOT NULL,
  mode VARCHAR(10) NOT NULL DEFAULT 'live',
  participant_key CHAR(64) NOT NULL,
  nickname VARCHAR(80) NOT NULL,
  nickname_key VARCHAR(80) NOT NULL,
  claimed_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  hall_of_fame_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  hall_opted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY participant_subject (subject_id,mode,participant_key),
  UNIQUE KEY nickname_subject (subject_id,mode,nickname_key),
  KEY expires_at (expires_at),
  KEY subject_mode (subject_id,mode),
  KEY hall_lookup (subject_id,mode,hall_of_fame_opt_in)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mhl_participants
  ADD COLUMN IF NOT EXISTS hall_of_fame_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER expires_at,
  ADD COLUMN IF NOT EXISTS hall_opted_at DATETIME NULL AFTER hall_of_fame_opt_in;

ALTER TABLE mhl_sessions
  ADD COLUMN IF NOT EXISTS joining_started_at DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS last_join_at DATETIME NULL AFTER joining_started_at;

CREATE TABLE IF NOT EXISTS mhl_session_joins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  participant_key CHAR(64) NOT NULL,
  first_seen_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY session_participant (session_id,participant_key),
  KEY session_id (session_id),
  KEY first_seen_at (first_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
