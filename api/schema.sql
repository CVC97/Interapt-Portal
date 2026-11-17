CREATE DATABASE IF NOT EXISTS interapt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE interapt;

-- Replacing the per-module schema of 2026-11 (users per module, no codename): drop the old tables first.
-- Only test data was stored there (decided 2026-11-17). In phpMyAdmin:
--   DROP TABLE IF EXISTS question_attempts, page_visits, users, modules;

-- One row per learning module ('pohl', 'precession'). A module is added on its first save,
-- but only if its name is listed under 'modules' in api/config.php.
CREATE TABLE IF NOT EXISTS modules (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- One account for all modules. The course key a student registers with decides the cohort (semester), so a
-- student repeating the course next year starts a new account. The username is unique per cohort and case-
-- insensitive (collation). codename is the survey code, built in the browser from the student's answers
-- (login/codename.js); only the code itself is stored. It also confirms a login on a new device.
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  cohort        VARCHAR(20) NOT NULL,
  username      VARCHAR(100) NOT NULL,
  codename      VARCHAR(32) NOT NULL,
  consent_at    DATETIME(3) NOT NULL,
  failed_logins INT NOT NULL DEFAULT 0,
  created_at    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  last_active   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY unique_user_per_cohort (cohort, username),
  KEY by_codename (codename)
);

-- One row per page visit. The unique key makes saving idempotent: a visit sent twice is stored once.
-- started_at comes from the browser, in UTC with milliseconds; received_at is the server's clock.
CREATE TABLE IF NOT EXISTS page_visits (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  module_id   INT NOT NULL,
  route       VARCHAR(100) NOT NULL,
  subpage     VARCHAR(20),
  query       VARCHAR(255) NOT NULL DEFAULT '',
  duration_s  DECIMAL(10, 2),
  started_at  DATETIME(3) NOT NULL,
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_visit (user_id, module_id, route, started_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (module_id) REFERENCES modules(id)
);

-- One row per submitted answer, for learning questions and tests alike.
-- unit_id is the learning section or the test (e.g. 'e2-damped-oscillation-module').
-- The unique key makes saving idempotent, and as tests always use attempt_no 1
-- it also enforces their single submission.
CREATE TABLE IF NOT EXISTS question_attempts (
  id             BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  module_id      INT NOT NULL,
  kind           ENUM('learning', 'test') NOT NULL,
  unit_id        VARCHAR(100) NOT NULL,
  question_id    VARCHAR(100) NOT NULL,
  attempt_no     INT NOT NULL,
  is_correct     BOOLEAN NOT NULL,
  points_awarded INT,
  max_points     INT,
  answer         JSON,
  correct_answer JSON,
  texts          JSON,
  answered_at    DATETIME(3) NOT NULL,
  received_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_attempt (user_id, module_id, unit_id, question_id, attempt_no),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (module_id) REFERENCES modules(id)
);
