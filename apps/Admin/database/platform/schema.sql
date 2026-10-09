-- admin_platform schema (idempotent; `./loongs admin:install` runs every statement).
-- {{prefix}} is replaced with the connection's table prefix (empty by default).

CREATE TABLE IF NOT EXISTS `{{prefix}}depts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `ancestors` VARCHAR(512) NOT NULL DEFAULT '0' COMMENT 'ancestor ids, comma separated, e.g. 0,1,2',
  `name` VARCHAR(64) NOT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `leader` VARCHAR(64) NULL,
  `phone` VARCHAR(32) NULL,
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1 enabled, 0 disabled',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='departments';

CREATE TABLE IF NOT EXISTS `{{prefix}}admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'password_hash argon2id',
  `nickname` VARCHAR(64) NOT NULL DEFAULT '',
  `mobile` VARCHAR(32) NULL,
  `email` VARCHAR(128) NULL,
  `dept_id` INT UNSIGNED NULL,
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1 enabled, 0 disabled',
  `is_super` TINYINT NOT NULL DEFAULT 0 COMMENT 'super admin: bypasses RBAC, cannot be deleted',
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `password_changed_at` DATETIME NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_dept` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='platform administrators';

CREATE TABLE IF NOT EXISTS `{{prefix}}roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `remark` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='roles';

CREATE TABLE IF NOT EXISTS `{{prefix}}admin_role` (
  `admin_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`admin_id`, `role_id`),
  KEY `idx_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='admin <-> role';

CREATE TABLE IF NOT EXISTS `{{prefix}}role_permission` (
  `role_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(128) NOT NULL COMMENT 'permission code of a menu/button in apps/*/menu.json, e.g. system:admin:create',
  PRIMARY KEY (`role_id`, `code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='role -> permission code (menus live in apps/*/menu.json)';

CREATE TABLE IF NOT EXISTS `{{prefix}}admin_wechat` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  `appid` VARCHAR(64) NOT NULL COMMENT 'mini program AppID',
  `openid` VARCHAR(128) NOT NULL,
  `unionid` VARCHAR(128) NULL,
  `created_at` DATETIME NULL COMMENT 'bound at (after a password login in the mini program)',
  `last_login_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_app_openid` (`appid`, `openid`),
  UNIQUE KEY `uk_admin_app` (`admin_id`, `appid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='admin <-> WeChat mini program openid';

CREATE TABLE IF NOT EXISTS `{{prefix}}role_data_scope` (
  `role_id` INT UNSIGNED NOT NULL,
  `scope` ENUM('all','custom','dept_and_child','dept','self') NOT NULL DEFAULT 'self',
  `dept_ids` JSON NULL COMMENT 'custom scope: department ids',
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='row-level data scope per role';

CREATE TABLE IF NOT EXISTS `{{prefix}}resource_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource` VARCHAR(64) NOT NULL,
  `field` VARCHAR(64) NOT NULL,
  `label` VARCHAR(64) NOT NULL,
  `default_perm` ENUM('hidden','masked','readonly','editable') NOT NULL DEFAULT 'editable' COMMENT 'used when a role has no rule',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_field` (`resource`, `field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='controllable fields';

CREATE TABLE IF NOT EXISTS `{{prefix}}role_field` (
  `role_id` INT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED NOT NULL,
  `perm` ENUM('hidden','masked','readonly','editable') NOT NULL,
  PRIMARY KEY (`role_id`, `field_id`),
  KEY `idx_field` (`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='role field permission';

-- P5 站内消息 (in-app messages, one row per recipient; no push yet). Sent by App\Admin\Services\MessageService.
CREATE TABLE IF NOT EXISTS `{{prefix}}admin_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL COMMENT 'recipient (admins.id)',
  `sender_id` INT UNSIGNED NULL COMMENT 'sender admin id, NULL = system',
  `title` VARCHAR(128) NOT NULL,
  `content` TEXT NULL,
  `level` VARCHAR(16) NOT NULL DEFAULT 'info' COMMENT 'info | success | warning | error',
  `link` VARCHAR(255) NULL COMMENT 'optional in-app target, e.g. /p/admin.sample.tenant-detail?id=1001',
  `read_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_read` (`admin_id`, `read_at`),
  KEY `idx_admin_created` (`admin_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='in-app messages per administrator';
