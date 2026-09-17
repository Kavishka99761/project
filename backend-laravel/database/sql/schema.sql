-- ==========================================================================
--  EDU-SMART — Complete SQL Schema (MySQL 8.0+)
--  Database layer for the four-module academic platform.
--
--  Layers:
--    • COMMON PLATFORM : users, modules, notifications
--    • BETHMI          : documents, summaries            (Learning Materials)
--    • PASINDU         : study_sessions, engagement_logs (Study & Engagement)
--    • KAVISHKA        : academic_documents, academic_chunks,
--                        chat_conversations, chat_messages, academic_dates
--    • JITHMI          : assignments, risk_assessments   (Assignment Risk)
--
--  Integration points (data exchange between modules):
--    • academic_dates -> assignments      (Kavishka extracts a deadline -> Jithmi)
--    • assignments    -> study_sessions   (Jithmi priority -> Pasindu task)
--    • study_sessions -> assignments      (Pasindu progress -> Jithmi risk recalc)
--    • documents      -> study_sessions   (Bethmi material -> Pasindu session)
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `edu_smart`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `edu_smart`;

-- ==========================================================================
--  COMMON PLATFORM LAYER
-- ==========================================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                 VARCHAR(120)    NOT NULL,
  `email`                VARCHAR(160)    NOT NULL,
  `password`             VARCHAR(255)    NOT NULL,
  `program`              VARCHAR(160)    DEFAULT NULL,
  `academic_year`        VARCHAR(80)     DEFAULT NULL,
  `dark_mode`            TINYINT(1)      NOT NULL DEFAULT 0,
  `daily_target_minutes` INT UNSIGNED    NOT NULL DEFAULT 180,
  `email_verified_at`    TIMESTAMP       NULL DEFAULT NULL,
  `remember_token`       VARCHAR(100)    DEFAULT NULL,
  `created_at`           TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`           TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `code`       VARCHAR(32)     NOT NULL,
  `name`       VARCHAR(120)    NOT NULL,
  `color`      VARCHAR(32)     NOT NULL DEFAULT 'primary',
  `icon`       VARCHAR(48)     NOT NULL DEFAULT 'book',
  `created_at` TIMESTAMP       NULL DEFAULT NULL,
  `updated_at` TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modules_user_id_foreign` (`user_id`),
  CONSTRAINT `modules_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `module_source` VARCHAR(32)    NOT NULL COMMENT 'bethmi|pasindu|kavishka|jithmi|common',
  `title`        VARCHAR(160)    NOT NULL,
  `message`      VARCHAR(500)    DEFAULT NULL,
  `is_read`      TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_foreign` (`user_id`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
--  BETHMI — SMART NOTES & LEARNING MATERIALS
-- ==========================================================================

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `module_id`      BIGINT UNSIGNED DEFAULT NULL,
  `title`          VARCHAR(200)    NOT NULL,
  `topic`          VARCHAR(120)    DEFAULT NULL,
  `type`           VARCHAR(16)     NOT NULL DEFAULT 'PDF' COMMENT 'PDF|Word|Text',
  `pages`          INT UNSIGNED    NOT NULL DEFAULT 0,
  `size_bytes`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `file_path`      VARCHAR(500)    DEFAULT NULL,
  `extracted_text` LONGTEXT        DEFAULT NULL COMMENT 'Text extracted from the uploaded file',
  `created_at`     TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_user_id_foreign` (`user_id`),
  KEY `documents_module_id_foreign` (`module_id`),
  FULLTEXT KEY `documents_text_ft` (`title`, `topic`, `extracted_text`),
  CONSTRAINT `documents_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_module_id_foreign` FOREIGN KEY (`module_id`)
    REFERENCES `modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `summaries`;
CREATE TABLE `summaries` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED DEFAULT NULL,
  `title`       VARCHAR(200)    NOT NULL,
  `length_type` VARCHAR(16)     NOT NULL DEFAULT 'Medium' COMMENT 'Short|Medium|Detailed',
  `body`        LONGTEXT        NOT NULL,
  `keywords`    JSON            DEFAULT NULL COMMENT '["inheritance","override"]',
  `created_at`  TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `summaries_user_id_foreign` (`user_id`),
  KEY `summaries_document_id_foreign` (`document_id`),
  CONSTRAINT `summaries_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `summaries_document_id_foreign` FOREIGN KEY (`document_id`)
    REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
--  PASINDU — STUDY SESSION & ENGAGEMENT
-- ==========================================================================

DROP TABLE IF EXISTS `study_sessions`;
CREATE TABLE `study_sessions` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `module_id`       BIGINT UNSIGNED DEFAULT NULL,
  `assignment_id`   BIGINT UNSIGNED DEFAULT NULL COMMENT 'Integration: task recommended by Jithmi',
  `document_id`     BIGINT UNSIGNED DEFAULT NULL COMMENT 'Integration: material provided by Bethmi',
  `activity`        VARCHAR(80)     NOT NULL DEFAULT 'Revision',
  `planned_minutes` INT UNSIGNED    NOT NULL DEFAULT 0,
  `actual_minutes`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `status`          VARCHAR(16)     NOT NULL DEFAULT 'active' COMMENT 'active|paused|completed',
  `started_at`      TIMESTAMP       NULL DEFAULT NULL,
  `ended_at`        TIMESTAMP       NULL DEFAULT NULL,
  `created_at`      TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `study_sessions_user_id_foreign` (`user_id`),
  KEY `study_sessions_module_id_foreign` (`module_id`),
  CONSTRAINT `study_sessions_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `study_sessions_module_id_foreign` FOREIGN KEY (`module_id`)
    REFERENCES `modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `engagement_logs`;
CREATE TABLE `engagement_logs` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT UNSIGNED NOT NULL,
  `study_session_id` BIGINT UNSIGNED DEFAULT NULL,
  `level`            VARCHAR(16)     NOT NULL DEFAULT 'Good' COMMENT 'Low|Moderate|Good',
  `percent`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `source`           VARCHAR(16)     NOT NULL DEFAULT 'auto' COMMENT 'auto|manual',
  `logged_at`        TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `engagement_logs_user_id_foreign` (`user_id`),
  KEY `engagement_logs_session_foreign` (`study_session_id`),
  CONSTRAINT `engagement_logs_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `engagement_logs_session_foreign` FOREIGN KEY (`study_session_id`)
    REFERENCES `study_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
--  JITHMI — ASSIGNMENT & DEADLINE RISK MANAGEMENT
-- ==========================================================================

DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `module_id`   BIGINT UNSIGNED DEFAULT NULL,
  `academic_date_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Integration: deadline extracted by Kavishka',
  `title`       VARCHAR(200)    NOT NULL,
  `deadline`    DATE            NOT NULL,
  `priority`    VARCHAR(16)     NOT NULL DEFAULT 'Medium' COMMENT 'Low|Medium|High',
  `est_hours`   DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
  `done_hours`  DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
  `progress`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `completed`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assignments_user_id_foreign` (`user_id`),
  KEY `assignments_module_id_foreign` (`module_id`),
  KEY `assignments_deadline_index` (`deadline`),
  CONSTRAINT `assignments_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_module_id_foreign` FOREIGN KEY (`module_id`)
    REFERENCES `modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `risk_assessments`;
CREATE TABLE `risk_assessments` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` BIGINT UNSIGNED NOT NULL,
  `score`         TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100',
  `level`         VARCHAR(16)     NOT NULL DEFAULT 'Low' COMMENT 'Low|Medium|High|Critical',
  `hours_per_day` DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
  `reasons`       JSON            DEFAULT NULL COMMENT '["Only 3 days left","Progress is low"]',
  `calculated_at` TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_assessments_assignment_foreign` (`assignment_id`),
  CONSTRAINT `risk_assessments_assignment_foreign` FOREIGN KEY (`assignment_id`)
    REFERENCES `assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
--  KAVISHKA — AI ACADEMIC ASSISTANT
-- ==========================================================================

DROP TABLE IF EXISTS `academic_documents`;
CREATE TABLE `academic_documents` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `category`   VARCHAR(32)     NOT NULL DEFAULT 'Handbook' COMMENT 'Handbook|Project|Regulation',
  `title`      VARCHAR(200)    NOT NULL,
  `pages`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `file_path`  VARCHAR(500)    DEFAULT NULL,
  `is_indexed` TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP       NULL DEFAULT NULL,
  `updated_at` TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_documents_user_id_foreign` (`user_id`),
  CONSTRAINT `academic_documents_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chunked content for retrieval-augmented generation (RAG)
DROP TABLE IF EXISTS `academic_chunks`;
CREATE TABLE `academic_chunks` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_document_id` BIGINT UNSIGNED NOT NULL,
  `section`              VARCHAR(200)    DEFAULT NULL,
  `page`                 INT UNSIGNED    NOT NULL DEFAULT 0,
  `content`              LONGTEXT        NOT NULL,
  `keywords`             JSON            DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_chunks_doc_foreign` (`academic_document_id`),
  FULLTEXT KEY `academic_chunks_content_ft` (`content`, `section`),
  CONSTRAINT `academic_chunks_doc_foreign` FOREIGN KEY (`academic_document_id`)
    REFERENCES `academic_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chat_conversations`;
CREATE TABLE `chat_conversations` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `title`      VARCHAR(200)    DEFAULT NULL,
  `created_at` TIMESTAMP       NULL DEFAULT NULL,
  `updated_at` TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_conversations_user_id_foreign` (`user_id`),
  CONSTRAINT `chat_conversations_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id`   BIGINT UNSIGNED NOT NULL,
  `role`              VARCHAR(16)     NOT NULL DEFAULT 'user' COMMENT 'user|bot',
  `content`           TEXT            NOT NULL,
  `source_chunk_id`   BIGINT UNSIGNED DEFAULT NULL COMMENT 'Retrieved chunk that grounded the answer',
  `created_at`        TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_conversation_foreign` (`conversation_id`),
  KEY `chat_messages_source_chunk_foreign` (`source_chunk_id`),
  CONSTRAINT `chat_messages_conversation_foreign` FOREIGN KEY (`conversation_id`)
    REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_source_chunk_foreign` FOREIGN KEY (`source_chunk_id`)
    REFERENCES `academic_chunks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `academic_dates`;
CREATE TABLE `academic_dates` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`              BIGINT UNSIGNED NOT NULL,
  `academic_document_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Source the date was extracted from',
  `title`                VARCHAR(200)    NOT NULL,
  `event_date`           DATE            NOT NULL,
  `type`                 VARCHAR(24)     NOT NULL DEFAULT 'Milestone' COMMENT 'Deadline|Milestone|Exam|Event',
  `reminder`             VARCHAR(48)     DEFAULT '1 day before',
  `created_at`           TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`           TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_dates_user_id_foreign` (`user_id`),
  KEY `academic_dates_doc_foreign` (`academic_document_id`),
  KEY `academic_dates_event_date_index` (`event_date`),
  CONSTRAINT `academic_dates_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_dates_doc_foreign` FOREIGN KEY (`academic_document_id`)
    REFERENCES `academic_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK from assignments.academic_date_id -> academic_dates (created above)
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_academic_date_foreign` FOREIGN KEY (`academic_date_id`)
  REFERENCES `academic_dates` (`id`) ON DELETE SET NULL;

-- Add FK from study_sessions.assignment_id -> assignments
ALTER TABLE `study_sessions`
  ADD CONSTRAINT `study_sessions_assignment_foreign` FOREIGN KEY (`assignment_id`)
  REFERENCES `assignments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `study_sessions_document_foreign` FOREIGN KEY (`document_id`)
  REFERENCES `documents` (`id`) ON DELETE SET NULL;

-- Laravel infrastructure tables
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255)    NOT NULL,
  `tokenable_id`   BIGINT UNSIGNED NOT NULL,
  `name`           VARCHAR(255)    NOT NULL,
  `token`          VARCHAR(64)     NOT NULL,
  `abilities`      TEXT            DEFAULT NULL,
  `last_used_at`   TIMESTAMP       NULL DEFAULT NULL,
  `expires_at`     TIMESTAMP       NULL DEFAULT NULL,
  `created_at`     TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `tokenable` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch`     INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================================
--  Handy view: current risk overview per user (used by the Overview hub)
-- ==========================================================================
CREATE OR REPLACE VIEW `v_latest_risk` AS
SELECT r.assignment_id, r.score, r.level, r.hours_per_day, r.reasons, r.calculated_at
FROM `risk_assessments` r
INNER JOIN (
  SELECT assignment_id, MAX(calculated_at) AS latest
  FROM `risk_assessments` GROUP BY assignment_id
) t ON r.assignment_id = t.assignment_id AND r.calculated_at = t.latest;
