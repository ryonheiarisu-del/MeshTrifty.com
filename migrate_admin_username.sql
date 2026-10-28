USE meshtrifty;

ALTER TABLE admins
  MODIFY email VARCHAR(254) NULL,
  ADD COLUMN username VARCHAR(64) NULL AFTER name;

UPDATE admins
SET username = LOWER(SUBSTRING_INDEX(email, '@', 1))
WHERE username IS NULL AND email IS NOT NULL;

ALTER TABLE admins
  MODIFY username VARCHAR(64) NOT NULL,
  ADD UNIQUE KEY admins_username_unique (username);
