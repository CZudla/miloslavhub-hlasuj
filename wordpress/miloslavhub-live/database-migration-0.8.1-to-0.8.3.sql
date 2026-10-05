-- MiloslavHub Live 0.8.3
-- PRI PRECHODU PRIMO Z 0.8.1: obsahuje i zmeny z 0.8.2.
CREATE TABLE IF NOT EXISTS mhl_participants (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, subject_id BIGINT UNSIGNED NOT NULL, mode VARCHAR(10) NOT NULL DEFAULT 'live', participant_key CHAR(64) NOT NULL, nickname VARCHAR(80) NOT NULL, nickname_key VARCHAR(80) NOT NULL, claimed_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY participant_subject (subject_id,mode,participant_key), UNIQUE KEY nickname_subject (subject_id,mode,nickname_key), KEY expires_at (expires_at), KEY subject_mode (subject_id,mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE mhl_sessions ADD COLUMN joining_started_at DATETIME NULL AFTER status, ADD COLUMN last_join_at DATETIME NULL AFTER joining_started_at;
CREATE TABLE IF NOT EXISTS mhl_session_joins (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id BIGINT UNSIGNED NOT NULL, participant_key CHAR(64) NOT NULL, first_seen_at DATETIME NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY session_participant (session_id,participant_key), KEY session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
