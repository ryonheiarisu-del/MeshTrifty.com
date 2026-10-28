USE meshtrifty;

ALTER TABLE accounts
  ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(80) NULL AFTER password_hash;

CREATE TABLE IF NOT EXISTS password_reset_otps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id BIGINT UNSIGNED NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  request_ip_hash CHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  requested_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY reset_account_latest (account_id, requested_at),
  KEY reset_ip_latest (request_ip_hash, requested_at),
  CONSTRAINT reset_otp_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB;
