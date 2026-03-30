-- Run after schema.sql
-- Adds: dumpsters enhancements, bookings table, inventory_blocks table, default Stripe settings

-- Enhance dumpsters table
ALTER TABLE `dumpsters`
  ADD COLUMN IF NOT EXISTS `type`        ENUM('dumpster','trailer') NOT NULL DEFAULT 'dumpster' AFTER `unit_code`,
  ADD COLUMN IF NOT EXISTS `daily_rate`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `size`,
  ADD COLUMN IF NOT EXISTS `active`      TINYINT(1) NOT NULL DEFAULT 1 AFTER `daily_rate`,
  ADD COLUMN IF NOT EXISTS `image`       VARCHAR(255) DEFAULT NULL AFTER `active`;

-- bookings
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`                 INT(11)       NOT NULL AUTO_INCREMENT,
  `booking_number`     VARCHAR(20)   NOT NULL,
  `customer_name`      VARCHAR(100)  NOT NULL,
  `customer_phone`     VARCHAR(25)            DEFAULT NULL,
  `customer_email`     VARCHAR(150)           DEFAULT NULL,
  `customer_address`   VARCHAR(200)           DEFAULT NULL,
  `customer_city`      VARCHAR(100)           DEFAULT NULL,
  `dumpster_id`        INT(11)                DEFAULT NULL,
  `unit_code`          VARCHAR(50)            DEFAULT NULL,
  `unit_type`          VARCHAR(50)            DEFAULT NULL,
  `unit_size`          VARCHAR(50)            DEFAULT NULL,
  `rental_start`       DATE          NOT NULL,
  `rental_end`         DATE          NOT NULL,
  `rental_days`        INT(11)       NOT NULL DEFAULT 1,
  `daily_rate`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method`     ENUM('stripe','cash','check') NOT NULL DEFAULT 'stripe',
  `payment_status`     ENUM('unpaid','pending','paid','refunded','pending_cash','paid_cash','pending_check','paid_check') NOT NULL DEFAULT 'unpaid',
  `stripe_session_id`  VARCHAR(255)           DEFAULT NULL,
  `stripe_payment_id`  VARCHAR(255)           DEFAULT NULL,
  `booking_status`     ENUM('pending','confirmed','paid','canceled','completed') NOT NULL DEFAULT 'pending',
  `notes`              TEXT                   DEFAULT NULL,
  `created_by`         INT(11)                DEFAULT NULL,
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookings_number` (`booking_number`),
  CONSTRAINT `fk_bookings_dumpster_id` FOREIGN KEY (`dumpster_id`) REFERENCES `dumpsters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bookings_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- inventory_blocks (admin-managed unavailability periods)
CREATE TABLE IF NOT EXISTS `inventory_blocks` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `dumpster_id`  INT(11)      NOT NULL,
  `block_start`  DATE         NOT NULL,
  `block_end`    DATE         NOT NULL,
  `reason`       VARCHAR(200)          DEFAULT NULL,
  `created_by`   INT(11)               DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ib_dumpster_id`  FOREIGN KEY (`dumpster_id`) REFERENCES `dumpsters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ib_created_by`   FOREIGN KEY (`created_by`)  REFERENCES `users`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Stripe / booking settings
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('stripe_publishable_key', ''),
  ('stripe_secret_key',      ''),
  ('stripe_webhook_secret',  ''),
  ('stripe_mode',            'test'),
  ('booking_terms',          'By completing this booking, you agree to our rental terms and conditions.'),
  ('currency',               'usd');
