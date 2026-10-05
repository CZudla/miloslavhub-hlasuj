-- Pouzijte pouze pri prechodu z 0.8.2 na 0.8.3.
ALTER TABLE mhl_sessions ADD COLUMN joining_started_at DATETIME NULL AFTER status, ADD COLUMN last_join_at DATETIME NULL AFTER joining_started_at;
CREATE TABLE IF NOT EXISTS mhl_session_joins (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,session_id BIGINT UNSIGNED NOT NULL,participant_key CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY session_participant(session_id,participant_key),KEY session_id(session_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
