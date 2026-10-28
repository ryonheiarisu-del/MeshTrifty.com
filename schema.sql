-- Import with: mysql -u root < schema.sql
-- Existing installations created before username login also need migrate_admin_username.sql once.
-- Existing products tables also need migrate_product_release.sql once.
-- Existing accounts also need migrate_account_features.sql once.
-- Then create the first administrator from a local terminal with setup_admin.php.

CREATE DATABASE IF NOT EXISTS meshtrifty
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE meshtrifty;

CREATE TABLE IF NOT EXISTS accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  profile_photo VARCHAR(80) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY accounts_email_unique (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admins_username_unique (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  price DECIMAL(10,2) NOT NULL,
  category VARCHAR(32) NOT NULL,
  tag VARCHAR(40) NOT NULL DEFAULT '',
  image VARCHAR(2048) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  release_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY products_active_category (active, category)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(32) NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  customer_name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(120) NOT NULL,
  postal VARCHAR(20) NOT NULL,
  payment_method VARCHAR(32) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY orders_number_unique (order_number),
  KEY orders_created_at (created_at),
  KEY orders_account_id (account_id),
  CONSTRAINT orders_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(120) NOT NULL,
  size VARCHAR(32) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY order_items_order_id (order_id),
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB;

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

INSERT INTO products (id, name, note, price, category, tag, image, stock, active) VALUES
  (1, 'UND Devin Booker Edition', 'Better with age', 899.00, 'Clothing', 'Brand New', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTiqLxvTHKD7Kvj_xoqVRLXnRZVb3JVwZMgaGi2mFp-EZ4hAxq238ayCo4&s=10', 10, 1),
  (2, 'A Bathing Ape X Chrome Hearts', 'Soft cotton · easy fit', 1450.00, 'Clothing', 'Brand New', 'https://cdn-images.farfetch-contents.com/36/66/92/27/36669227_69424036_600.jpg', 10, 1),
  (3, 'UND XMAS Edition', 'A little worn in, a lot of life', 899.00, 'Clothing', 'Brand New', 'https://img.lazcdn.com/g/p/b74929f9f9d8c1a16c842943bdce97ae.jpg_720x720q80.jpg', 10, 1),
  (4, 'Nike Elite Bag', 'Room for all your things', 1100.00, 'Accessories', 'Brand New', 'https://static.nike.com/a/images/t_web_pdp_535_v2/f_auto,u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/9c5b965c-c947-4e31-9cbd-19ce88efc317/NK+VARSITY+ELITE+BKPK.png', 10, 1),
  (5, 'New Balance 530', 'The pair you keep forever', 6795.00, 'Shoes', 'Second Hand', 'https://i.pinimg.com/736x/16/67/81/16678150e3a456c1b5a1efccc1f33787.jpg', 10, 1),
  (6, 'Nike Jordan Sando', 'Fresh and Clean', 199.00, 'Clothing', 'Brand New', 'https://static.nike.com/a/images/t_web_pdp_535_v2/f_auto,u_126ab356-44d8-4a06-89b4-fcdcc8df0245,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/084dafbb-4e41-4525-8e8a-fea99b355df2/M+J+SPT+RVRSBL+GFX+JRSY.png', 10, 1),
  (7, 'Nike Brasilia', 'Made in Recycle but Nike Make it Reusable', 2699.00, 'Accessories', 'Brand New', 'https://static.nike.com/a/images/t_web_pdp_535_v2/f_auto,u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/bbfab902-f382-48f5-944c-e9f4b6878e25/NK+BRSLA+S+DUFF+-+X.png', 10, 1),
  (8, 'New Balance U9060', 'A statement from every angle', 9888.80, 'Shoes', 'Second Hand', 'https://i.pinimg.com/1200x/d5/3c/99/d53c99f62d91bc9615ca6812a6fbd6fc.jpg', 10, 1)
ON DUPLICATE KEY UPDATE id = id;
