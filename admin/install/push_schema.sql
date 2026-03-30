-- =============================================================================
-- Push Notification Subscriptions Schema
-- Run after booking_schema.sql
-- =============================================================================

-- push_subscriptions: stores Web Push API subscription objects for both
-- admin users and customers (identified by email or phone).
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `subscriber_type` ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  `subscriber_id`   VARCHAR(200)  NOT NULL COMMENT 'admin user_id (int as string) or customer email/phone',
  `endpoint`        TEXT          NOT NULL,
  `p256dh`          VARCHAR(255)  NOT NULL,
  `auth`            VARCHAR(64)   NOT NULL,
  `user_agent`      VARCHAR(255)           DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_endpoint` (endpoint(200)),
  KEY `idx_push_subscriber` (`subscriber_type`, `subscriber_id`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store VAPID key pair in settings (generated on first use)
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('vapid_public_key',  ''),
  ('vapid_private_key', ''),
  ('vapid_subject',     '');
