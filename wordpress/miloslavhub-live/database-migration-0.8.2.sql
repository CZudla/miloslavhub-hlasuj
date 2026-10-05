-- MiloslavHub Live 0.8.2
-- Rezervace prezdivky je jedinecna pouze v ramci predmetu a rezimu (LIVE/TEST).
-- Po uplynuti nastavene doby necinnosti (vychozi 365 dni) ji muze pouzit jiny ucastnik.
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
  PRIMARY KEY (id),
  UNIQUE KEY participant_subject (subject_id, mode, participant_key),
  UNIQUE KEY nickname_subject (subject_id, mode, nickname_key),
  KEY expires_at (expires_at),
  KEY subject_mode (subject_id, mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
