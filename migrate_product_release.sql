USE meshtrifty;

ALTER TABLE products
  ADD COLUMN release_at DATETIME NULL DEFAULT NULL AFTER active;
