-- MiloslavHub Live 0.8.5 - complete schema for a fresh dedicated database
CREATE TABLE IF NOT EXISTS mhl_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lecture_id BIGINT UNSIGNED NULL,
  subject_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL DEFAULT '',
  mode VARCHAR(10) NOT NULL DEFAULT 'live',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id), KEY lecture_id (lecture_id), KEY subject_id (subject_id), KEY mode (mode), KEY status (status), KEY expires_at (expires_at)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mhl_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  mode VARCHAR(10) NOT NULL DEFAULT 'live',
  status VARCHAR(20) NOT NULL DEFAULT 'waiting',
  joining_started_at DATETIME NULL, last_join_at DATETIME NULL, opened_at DATETIME NULL, closed_at DATETIME NULL, reset_at DATETIME NULL, created_at DATETIME NOT NULL,
  PRIMARY KEY (id), KEY run_question (run_id,question_id), KEY question_id (question_id), KEY mode (mode), KEY status (status), KEY reset_at (reset_at)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mhl_votes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL, run_id BIGINT UNSIGNED NOT NULL, question_id BIGINT UNSIGNED NOT NULL,
  mode VARCHAR(10) NOT NULL DEFAULT 'live', participant_key CHAR(64) NOT NULL, nickname VARCHAR(80) NOT NULL DEFAULT '',
  option_index SMALLINT UNSIGNED NOT NULL, is_correct TINYINT(1) NULL, response_ms INT UNSIGNED NOT NULL DEFAULT 0, points INT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY one_vote (session_id,participant_key), KEY run_id (run_id), KEY question_id (question_id), KEY mode (mode), KEY participant_key (participant_key)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

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
  hall_visibility VARCHAR(12) NOT NULL DEFAULT 'unset',
  PRIMARY KEY (id),
  UNIQUE KEY participant_subject (subject_id, mode, participant_key),
  UNIQUE KEY nickname_subject (subject_id, mode, nickname_key),
  KEY expires_at (expires_at),
  KEY subject_mode (subject_id, mode),
  KEY hall_lookup (subject_id, mode, hall_of_fame_opt_in),
  KEY hall_visibility (subject_id, mode, hall_visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mhl_session_joins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id BIGINT UNSIGNED NOT NULL, participant_key CHAR(64) NOT NULL, first_seen_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY session_participant (session_id,participant_key), KEY session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
