-- =============================================================================
-- Trash Panda Roll-Offs – Database Schema
-- Run via install/install.php or manually with: mysql -u user -p dbname < schema.sql
-- Payments are handled outside the system by the business.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`             int(11)      NOT NULL AUTO_INCREMENT,
  `name`           varchar(100) NOT NULL,
  `email`          varchar(150) NOT NULL,
  `password`       varchar(255) NOT NULL,
  `role`           ENUM('admin','office','dispatcher','readonly') NOT NULL DEFAULT 'office',
  `active`         tinyint(1)   NOT NULL DEFAULT 1,
  `must_change_pw` tinyint(1)   NOT NULL DEFAULT 0,
  `last_login`     datetime              DEFAULT NULL,
  `created_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- customers  (must come before leads so the FK in leads can reference it)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `name`            varchar(100) NOT NULL,
  `company`         varchar(150)          DEFAULT NULL,
  `email`           varchar(150)          DEFAULT NULL,
  `phone`           varchar(20)           DEFAULT NULL,
  `address`         varchar(200)          DEFAULT NULL,
  `city`            varchar(100)          DEFAULT NULL,
  `state`           varchar(50)           DEFAULT NULL,
  `zip`             varchar(20)           DEFAULT NULL,
  `billing_address` varchar(200)          DEFAULT NULL,
  `billing_city`    varchar(100)          DEFAULT NULL,
  `billing_state`   varchar(50)           DEFAULT NULL,
  `billing_zip`     varchar(20)           DEFAULT NULL,
  `type`            ENUM('residential','commercial','contractor') NOT NULL DEFAULT 'residential',
  `notes`           text                  DEFAULT NULL,
  `lead_id`         int(11)               DEFAULT NULL,
  `created_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- leads  (references users and customers)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `name`         varchar(100) NOT NULL,
  `email`        varchar(150)          DEFAULT NULL,
  `phone`        varchar(20)           DEFAULT NULL,
  `address`      varchar(200)          DEFAULT NULL,
  `city`         varchar(100)          DEFAULT NULL,
  `state`        varchar(50)           DEFAULT NULL,
  `zip`          varchar(20)           DEFAULT NULL,
  `size_needed`  varchar(50)           DEFAULT NULL,
  `project_type` varchar(100)          DEFAULT NULL,
  `source`       varchar(100)          DEFAULT NULL,
  `message`      text                  DEFAULT NULL,
  `status`       ENUM('new','contacted','quoted','won','lost') NOT NULL DEFAULT 'new',
  `assigned_to`  int(11)               DEFAULT NULL,
  `converted_to` int(11)               DEFAULT NULL,
  `archived`     tinyint(1)   NOT NULL DEFAULT 0,
  `created_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_leads_assigned_to`  FOREIGN KEY (`assigned_to`)  REFERENCES `users`     (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leads_converted_to` FOREIGN KEY (`converted_to`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Now that leads exists, add the FK from customers.lead_id → leads.id
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_lead_id`
    FOREIGN KEY IF NOT EXISTS (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- quotes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotes` (
  `id`             int(11)        NOT NULL AUTO_INCREMENT,
  `quote_number`   varchar(20)    NOT NULL,
  `customer_id`    int(11)                 DEFAULT NULL,
  `lead_id`        int(11)                 DEFAULT NULL,
  `cust_name`      varchar(100)   NOT NULL,
  `cust_email`     varchar(150)            DEFAULT NULL,
  `cust_phone`     varchar(20)             DEFAULT NULL,
  `cust_address`   varchar(200)            DEFAULT NULL,
  `size`           varchar(50)             DEFAULT NULL,
  `project_type`   varchar(100)            DEFAULT NULL,
  `service_address` varchar(200)           DEFAULT NULL,
  `service_city`   varchar(100)            DEFAULT NULL,
  `rental_days`    int(11)                 DEFAULT NULL,
  `rental_price`   decimal(10,2)           DEFAULT NULL,
  `delivery_fee`   decimal(10,2)           DEFAULT 0.00,
  `pickup_fee`     decimal(10,2)           DEFAULT 0.00,
  `extra_fees`     decimal(10,2)           DEFAULT 0.00,
  `extra_fee_desc` varchar(200)            DEFAULT NULL,
  `tax_rate`       decimal(5,2)            DEFAULT 0.00,
  `tax_amount`     decimal(10,2)           DEFAULT 0.00,
  `total`          decimal(10,2)           DEFAULT 0.00,
  `notes`          text                    DEFAULT NULL,
  `terms`          text                    DEFAULT NULL,
  `status`         ENUM('draft','sent','approved','rejected') NOT NULL DEFAULT 'draft',
  `valid_until`    date                    DEFAULT NULL,
  `created_by`     int(11)                 DEFAULT NULL,
  `converted_to`   int(11)                 DEFAULT NULL,
  `created_at`     datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotes_number` (`quote_number`),
  CONSTRAINT `fk_quotes_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quotes_lead_id`     FOREIGN KEY (`lead_id`)     REFERENCES `leads`     (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quotes_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `users`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- dumpsters
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dumpsters` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `unit_code`  varchar(50)  NOT NULL,
  `size`       varchar(50)  NOT NULL,
  `status`     ENUM('available','reserved','in_use','maintenance') NOT NULL DEFAULT 'available',
  `condition`  ENUM('excellent','good','fair','poor')              NOT NULL DEFAULT 'good',
  `notes`      text                  DEFAULT NULL,
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dumpsters_unit_code` (`unit_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- work_orders
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `work_orders` (
  `id`              int(11)       NOT NULL AUTO_INCREMENT,
  `wo_number`       varchar(20)   NOT NULL,
  `customer_id`     int(11)                DEFAULT NULL,
  `quote_id`        int(11)                DEFAULT NULL,
  `cust_name`       varchar(100)  NOT NULL,
  `cust_email`      varchar(150)           DEFAULT NULL,
  `cust_phone`      varchar(20)            DEFAULT NULL,
  `service_address` varchar(200)  NOT NULL,
  `service_city`    varchar(100)           DEFAULT NULL,
  `service_state`   varchar(50)            DEFAULT NULL,
  `service_zip`     varchar(20)            DEFAULT NULL,
  `size`            varchar(50)            DEFAULT NULL,
  `dumpster_id`     int(11)                DEFAULT NULL,
  `project_type`    varchar(100)           DEFAULT NULL,
  `delivery_date`   date                   DEFAULT NULL,
  `pickup_date`     date                   DEFAULT NULL,
  `actual_pickup`   date                   DEFAULT NULL,
  `assigned_driver` int(11)                DEFAULT NULL,
  `amount`          decimal(10,2)          DEFAULT 0.00,
  `status`          ENUM('scheduled','delivered','active','pickup_requested','picked_up','completed','canceled') NOT NULL DEFAULT 'scheduled',
  `priority`        ENUM('normal','high','urgent') NOT NULL DEFAULT 'normal',
  `internal_notes`  text                   DEFAULT NULL,
  `footer_notes`    text                   DEFAULT NULL,
  `created_by`      int(11)                DEFAULT NULL,
  `created_at`      datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_work_orders_number` (`wo_number`),
  CONSTRAINT `fk_wo_customer_id`    FOREIGN KEY (`customer_id`)    REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wo_quote_id`       FOREIGN KEY (`quote_id`)       REFERENCES `quotes`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wo_dumpster_id`    FOREIGN KEY (`dumpster_id`)    REFERENCES `dumpsters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wo_assigned_driver` FOREIGN KEY (`assigned_driver`) REFERENCES `users`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wo_created_by`     FOREIGN KEY (`created_by`)     REFERENCES `users`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- work_order_notes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `work_order_notes` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `wo_id`      int(11)   NOT NULL,
  `user_id`    int(11)            DEFAULT NULL,
  `note`       text      NOT NULL,
  `note_type`  ENUM('note','status_change','system') NOT NULL DEFAULT 'note',
  `created_at` datetime  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_won_wo_id`   FOREIGN KEY (`wo_id`)   REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_won_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- lead_notes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lead_notes` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `lead_id`    int(11)   NOT NULL,
  `user_id`    int(11)            DEFAULT NULL,
  `note`       text      NOT NULL,
  `created_at` datetime  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ln_lead_id` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ln_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- activity_log
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)               DEFAULT NULL,
  `action`      varchar(100) NOT NULL,
  `description` text                  DEFAULT NULL,
  `entity_type` varchar(50)           DEFAULT NULL,
  `entity_id`   int(11)               DEFAULT NULL,
  `ip_address`  varchar(45)           DEFAULT NULL,
  `created_at`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_al_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- settings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        varchar(100) NOT NULL,
  `value`      text                  DEFAULT NULL,
  `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- notifications
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `type`         ENUM('email','sms') NOT NULL DEFAULT 'email',
  `recipient`    varchar(180)          DEFAULT NULL,
  `subject`      varchar(255)          DEFAULT NULL,
  `body`         text                  DEFAULT NULL,
  `status`       ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `related_type` varchar(50)           DEFAULT NULL,
  `related_id`   int(11)               DEFAULT NULL,
  `sent_at`      datetime              DEFAULT NULL,
  `created_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- two_factor_secrets
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `two_factor_secrets` (
  `user_id`      int(11)     NOT NULL,
  `secret`       varchar(64) NOT NULL,
  `enabled`      tinyint(1)  NOT NULL DEFAULT 0,
  `backup_codes` text                  DEFAULT NULL,
  `created_at`   datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_tfs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- login_attempts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `ip_address`   varchar(45)  NOT NULL,
  `email`        varchar(180)          DEFAULT NULL,
  `attempted_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip`   (`ip_address`),
  KEY `idx_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- rate_limit_locks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limit_locks` (
  `ip_address`   varchar(45) NOT NULL,
  `attempts`     int(11)     NOT NULL DEFAULT 0,
  `locked_until` datetime             DEFAULT NULL,
  `updated_at`   datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
