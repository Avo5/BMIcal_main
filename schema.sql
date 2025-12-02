-- Database Schema Initialization Script
-- project-root/schema.sql
-- 
-- このスクリプトはプロジェクトのデータベーススキーマを初期化するためのものです。
-- 以下のコマンドで実行します:
--   mysql -u root tech_base_php < schema.sql
-- または、別環境でのセットアップ:
--   mysql -h your.db.host -u db_user -p tech_base_php < schema.sql

-- ========================
-- 1. users テーブル
-- ========================
CREATE TABLE IF NOT EXISTS users (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  birth_date    DATE NULL,
  sex           ENUM('male', 'female', 'other') NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================
-- 2. goals テーブル（1ユーザー：1レコード）
-- ========================
CREATE TABLE IF NOT EXISTS goals (
  id               BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id          BIGINT NOT NULL,
  target_weight_kg DECIMAL(5,2) NULL,
  target_bmi       DECIMAL(5,2) NULL,
  target_bmr       DECIMAL(7,2) NULL,
  target_tdee      DECIMAL(7,2) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_goals_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT uq_goals_user UNIQUE (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================
-- 3. body_records テーブル（1ユーザー：N レコード）
-- ========================
CREATE TABLE IF NOT EXISTS body_records (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT NOT NULL,
  record_date     DATE NOT NULL,
  height_cm       DECIMAL(5,2) NOT NULL,
  weight_kg       DECIMAL(5,2) NOT NULL,
  bmi             DECIMAL(5,2) NOT NULL,
  bmr             DECIMAL(7,2) NULL,
  tdee            DECIMAL(7,2) NULL,
  memo            TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_body_records_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================
-- インデックス（パフォーマンス最適化）
-- ========================
CREATE INDEX IF NOT EXISTS idx_body_records_user_date 
  ON body_records(user_id, record_date DESC);

CREATE INDEX IF NOT EXISTS idx_goals_user 
  ON goals(user_id);

-- ========================
-- スキーマ初期化完了
-- ========================
-- 次のステップ:
-- 1. レコード挿入テストを実行
-- 2. CRUD 操作の動作確認
-- 3. 本番環境へのデプロイ
