-- Audit trail (ADR 0003). Idempotent; no data in this file.
CREATE TABLE IF NOT EXISTS magic_audit (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at     DATETIME NOT NULL,              -- UTC, set with UTC_TIMESTAMP()
  functionality  VARCHAR(16) NOT NULL,           -- 'self' | 'love'
  on_date        DATE NOT NULL,                  -- reading date ("today" or on=)
  format_version TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- version of the YAML layout
  response_yaml  MEDIUMTEXT NOT NULL,            -- UTF-8 YAML, capped at 64 KiB by the app
  PRIMARY KEY (id),
  KEY idx_magic_audit_created_at (created_at),
  KEY idx_magic_audit_functionality (functionality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS magic_audit_person (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  audit_id    BIGINT UNSIGNED NOT NULL,
  role        VARCHAR(8) NOT NULL,               -- 'self' | 'user' | 'loved'
  name        VARCHAR(40) NOT NULL,              -- characters, same limit as Request
  birth_date  DATE NULL,                         -- NULL when not given (loved one)
  birth_time  TIME NULL,                         -- NULL when not given/used
  place_label VARCHAR(255) NULL,                 -- Geocoder::label() text as resolved
  lat         DECIMAL(8,5) NULL,
  lon         DECIMAL(8,5) NULL,
  tz          VARCHAR(64) NULL,                  -- IANA zone
  PRIMARY KEY (id),
  UNIQUE KEY uq_magic_audit_person_role (audit_id, role),
  KEY idx_magic_audit_person_name (name),
  CONSTRAINT fk_magic_audit_person_audit FOREIGN KEY (audit_id)
    REFERENCES magic_audit (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
