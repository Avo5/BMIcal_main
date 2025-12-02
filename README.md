# Health & Fitness Tracker - PHPプロジェクト

このリポジトリは、ユーザーが日々の体重・身長などを記録し、BMI・BMR・TDEE などを自動計算・表示するPHP Webアプリケーションです。

「ガチMVC」までは行かず、「include でそこそこ整理」したレベルの設計となっており、学習・プロトタイピングに最適です。

## プロジェクト概要

### 機能

- **ユーザー認証** — 登録・ログイン・ログアウト
- **プロフィール管理** — 生年月日、性別の設定
- **目標値管理** — 理想体重・BMI・BMR・TDEE の設定
- **記録管理（CRUD）** — 日々の身長・体重・備考を記録
- **自動計算** — 記録時に BMI・BMR・TDEE を自動計算して保存
- **ダッシュボード** — 記録一覧表示、目標達成度を可視化
- **パスワード変更** — セキュリティ管理

### 技術スタック

- **言語:** PHP 7.4 以上
- **DB:** MySQL 5.7 以上（または MariaDB）
- **フロント:** HTML5 + CSS（Bootstrap など）
- **認証:** セッション + `password_hash()` / `password_verify()`
- **サーバ:** Apache（.htaccess）または Nginx（リライトルール設定）

## ディレクトリ構成

```
project_root/
├─ .env.example              # 環境変数テンプレート（Git に入れる）
├─ .gitignore               # Git で無視するファイル
├─ README.md                # このファイル
├─ Plan.md                  # 仕様書・設計ドキュメント
├─ config/
│   └─ config.example.php   # DB 接続テンプレート（Git に入れる）
├─ lib/
│   ├─ db.php               # PDO インスタンス取得関数
│   ├─ functions.php        # 計算ロジック（BMI, BMR, TDEE）、ユーティリティ
│   └─ auth.php             # セッション・ログイン認証ヘルパー
├─ templates/
│   ├─ header.php           # HTML ヘッダー・ナビゲーション
│   └─ footer.php           # HTML フッター
├─ tools/                   # CLIユーティリティ（Web公開しない）
│   └─ recalc_records.php   # 例: 過去レコード再計算スクリプト
└─ public/                   # Web ドキュメントルート
    ├─ index.php            # ログイン（未認証）/ ダッシュボード（認証済み）
    ├─ register.php         # ユーザー登録
    ├─ dashboard.php        # メイン：記録一覧 + 新規入力
  ├─ settings_profile.php # プロフィール編集
  ├─ settings_goal.php    # 目標値設定（実装予定）
  ├─ settings_password.php # パスワード変更（実装予定）
    └─ logout.php           # ログアウト
```

## セットアップ手順

### 1. リポジトリのクローン・配置

```bash
# MAMP を使用する場合
cd /Applications/MAMP/htdocs/PHP_TechBase/mission6/project

# または git clone した場合
git clone <repository> project
cd project
```

### 2. 環境設定

#### `.env` ファイルの作成

`.env.example` をコピーして `.env` を作成します：

```bash
cp .env.example .env
```

`.env` の内容例（実際の DB 情報に置き換える）:

```ini
DB_HOST=127.0.0.1
DB_NAME=your_database_name
DB_USER=your_db_user
DB_PASS=your_db_password
DB_CHARSET=utf8mb4
```

#### `config/config.php` の作成

`config/config.example.php` をコピーして `config/config.php` を作成します：

```bash
cp config/config.example.php config/config.php
```

`config/config.php` の内容（実際の DB 情報に編集）:

```php
<?php
return [
  'db' => [
    'dsn' => 'mysql:host=127.0.0.1;dbname=your_db;charset=utf8mb4',
    'user' => 'root',
    'pass' => 'root',
  ],
];
```

セキュリティ注意: `tools/` のような CLI スクリプトや診断用スクリプトは必ず `public/`（ドキュメントルート）の外に置いてください。本リポジトリでは `tools/` をプロジェクトルートに置いています。Web サーバの document root は必ず `public/` を指すよう設定してください。

### 3. DB テーブル作成

データベースの詳細なスキーマ（CREATE TABLE 等）は本リポジトリの `Plan.md` に記載しています。運用環境に合わせて `config/config.php` を作成し、Plan.md の DDL を実行してください。

（要点）
- テーブルは `users`, `goals`, `body_records` の 3 つ。詳細は `Plan.md` を参照。
- `body_records` は同日複数レコードを許可する設計です（`UNIQUE(user_id, record_date)` は付けない）。


### 4. Web サーバ設定

#### MAMP の場合

1. MAMP Apache 設定で document root を `.../project/public` に変更
2. または Virtual Host を設定

#### Apache（.htaccess）の場合

`public/.htaccess` を作成：

```apacheconf
Options -Indexes
RewriteEngine On
```

#### Nginx の場合

```nginx
server {
    listen 80;
    server_name localhost;
    root /Applications/MAMP/htdocs/PHP_TechBase/mission6/project/public;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. 簡易実行（テスト用）

PHP 組み込みサーバで動かす場合（本番向けではありません）:

```bash
cd /Applications/MAMP/htdocs/PHP_TechBase/mission6/project/public
php -S 127.0.0.1:8000
```

ブラウザで `http://127.0.0.1:8000` にアクセス

## 使用方法

### 1. ユーザー登録

`/register.php` で新規ユーザーを作成。username と password を入力。

### 2. ログイン

`/index.php` で username と password でログイン。

### 3. ダッシュボード

ログイン後、`/dashboard.php` で：
- 現在の目標値を表示
- 新規記録フォーム（日付、身長、体重、備考）
- 記録一覧（BMI・BMR・TDEE が自動計算された状態で表示）

### 4. プロフィール設定

`/settings_profile.php` で生年月日・性別を設定。

### 5. 目標値設定

`/settings_goal.php` で理想体重・BMI・BMR・TDEE を設定。

### 6. パスワード変更

`/settings_password.php` で新しいパスワードに変更。

### 7. ログアウト

`/logout.php` でセッションを破棄してログアウト。

## セキュリティ上の注意

- **DB 接続情報** — `.gitignore` で `config/config.php` を除外。本番環境でのみ配置
- **パスワード** — `password_hash()` / `password_verify()` で安全に保存
- **XSS 対策** — すべての出力を `htmlspecialchars()` でエスケープ
- **CSRF 対策** — フォーム送信時にトークンを検証
- **HTTPS** — 本番環境では常時有効化
- **環境変数** — `.env` を `.gitignore` に入れる

## .gitignore 設定

リポジトリに含めてはいけないファイル:

```
# 秘密設定
config/config.php
.env
.env.local

# キャッシュ
.DS_Store
*.swp
*.swo
*~
.idea/
.vscode/

# ログ
*.log
```

詳細は `.gitignore` ファイルを確認。

## サーバーの起動・実行

### 方法 1: PHP 組み込みサーバを使用（開発時・簡単）

```bash
cd /Applications/MAMP/htdocs/PHP_TechBase/mission6/project
php -S localhost:8000 -t public
```

ブラウザで `http://localhost:8000` を開くと、ログインページが表示されます。

### 方法 2: MAMP を使用（macOS・推奨）

1. MAMP を起動（/Applications/MAMP/MAMP.app）
2. MAMP ウィンドウの「Preferences」をクリック
3. 「Web Server」タブで「Document Root」を以下に設定：
   ```
   /Applications/MAMP/htdocs/PHP_TechBase/mission6/project/public
   ```
4. 「Start Servers」をクリック
5. ブラウザで `http://localhost:8888` を開く

### 方法 3: Apache + Nginx の場合

`public/` をドキュメントルートとし、`.htaccess` または Nginx の rewrite ルールを設定してください。

## 開発ロードマップ

1. ✅ DB テーブル作成
2. ✅ `lib/` の共通関数実装
3. ✅ 認証フロー（register / login）
4. ✅ ダッシュボード（CRUD）
5. ✅ 設定画面（プロフィール編集・過去レコード再計算）
6. ⬜ 目標値設定・パスワード変更ページ
7. ⬜ UI 強化（Bootstrap など）
8. ⬜ グラフ表示（Chart.js など）

## トラブルシューティング

### DB 接続エラー

- `config/config.php` が存在するか確認
- DB ホスト、ユーザー、パスワードが正確か確認
- MySQL サーバが起動しているか確認

### ログイン後にダッシュボードが表示されない

- セッションが有効か確認（session_start() が呼ばれているか）
- ブラウザの Cookie が有効か確認

### BMI / BMR / TDEE が計算されない

- `birth_date`, `sex` が users テーブルに設定されているか確認
- `lib/functions.php` の計算関数が正しいか確認

## 参考資料

- **BMI 計算式:** `BMI = weight_kg / (height_m ^ 2)`
- **BMR（Mifflin-St Jeor）:**
  - 男性: `BMR = 10*weight + 6.25*height - 5*age + 5`
  - 女性: `BMR = 10*weight + 6.25*height - 5*age - 161`
- **TDEE:** `TDEE = BMR × 活動係数（初期値: 1.2）`

## ライセンス

本プロジェクトはプライベート学習用です。商用利用は禁止します。

## 作成者・最終更新

- 作成者: (あなたの名前)
- 最終更新: 2025-12-01
- バージョン: 1.0-alpha

---

詳細な仕様書は `Plan.md` を参照してください。
