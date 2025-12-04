## 実装済み機能 ✅ vs 未実装機能 ⬜

### 認証周り
- [x] ユーザー登録（register.php）— CSRF 保護、password_hash 使用
- [x] ログイン（login.php）— password_verify、session_regenerate_id
- [x] ログアウト（logout.php）— session_destroy
- [x] セッション管理（auth.php）— require_login(), current_user_id(), get_current_user()
- [x] CSRF トークン（functions.php）— csrf_token(), verify_csrf_token()
- [ ] パスワード変更（settings_password.php）— 未実装
- [ ] パスワードリセット — 未実装

### ユーザープロフィール
- [x] プロフィール表示（dashboard.php）— ユーザ名を表示
- [x] プロフィール編集（settings_profile.php）— birth_date, sex を設定
- [x] プロフィール更新時の過去レコード再計算（settings_profile.php）— birth_date/sex 変更時に body_records の BMR/TDEE を再計算
- [ ] 目標値設定（settings_goal.php）— 未実装
- [ ] アバター画像アップロード — 未実装

### 体重・身長記録管理（body_records）
- [x] 新規記録追加（dashboard.php）— record_date, height_cm, weight_kg, memo を入力
- [x] BMI 自動計算（dashboard.php）— サーバ側で calc_bmi() を実行して保存
- [x] BMR/TDEE 自動計算（dashboard.php）— birth_date/sex 有無に応じて calc_bmr(), calc_tdee() を実行
- [x] 記録一覧表示（dashboard.php）— 新しい順で最新 30 件を表示
- [ ] 記録編集（update）— 未実装
- [ ] 記録削除（delete）— 未実装
- [ ] 記録検索・フィルタ — 未実装
- [ ] 記録データのエクスポート — 未実装

### 目標値管理（goals テーブル）
- [ ] 目標値入力（settings_goal.php）— 理想体重、目標 BMI、目標 BMR などを設定
- [ ] 目標値表示 — 未実装
- [ ] 目標達成度の計算・表示 — 未実装

### 計算関数
- [x] BMI 計算（calc_bmi）— 2 桁丸め
- [x] BMR 計算（calc_bmr）— Mifflin-St Jeor、2 桁丸め、sex='male'|'female'|'other'
- [x] TDEE 計算（calc_tdee）— BMR × 活動係数（デフォルト 1.2）、2 桁丸め
- [x] 年齢計算（age_on_date）— birth_date から record_date 時点の年齢を計算
- [x] バリデーション（validate_height_weight）— 身長・体重の範囲チェック

### UI/UX
- [x] シンプルな HTML5 テンプレート（header.php, footer.php）
- [x] 基本的な CSS スタイル（styles.css）— エラー・成功メッセージの色分け、フォームスタイル
- [ ] Bootstrap または Tailwind による高度な UI — 未実装
- [ ] グラフ表示（Chart.js）— 記録推移、BMI/TDEE トレンド
- [ ] レスポンシブデザイン — 最小限のみ実装

### データベース
- [x] DB 接続（db.php）— PDO ドライバ、config.php から接続情報取得
- [x] テーブル設計（Plan.md で記載）— users, goals, body_records の DDL
- [ ] マイグレーション機能 — 未実装
- [ ] バックアップ・復元スクリプト — 未実装

### ユーティリティ
- [x] CLI スクリプト（tools/recalc_records.php）— 指定ユーザの BMR/TDEE を手動再計算
- [ ] バッチ処理スクリプト — 全ユーザの body_records 定期更新
- [ ] ログ機能 — 未実装
- [ ] アラート機能（目標超過時など） — 未実装

### セキュリティ
- [x] password_hash / password_verify
- [x] CSRF トークン検証
- [x] SQL インジェクション対策（PDO prepared statements）
- [x] htmlspecialchars() による XSS 対策
- [x] .gitignore で config.php, .env を除外
- [ ] HTTPS 強制設定 — 未実装（サーバ設定側）
- [ ] レート制限（ブルートフォース対策） — 未実装
- [ ] ログイン試行回数制限 — 未実装

---

## 🧪 現時点で確認すべき内容と方法

### 1. **DB 接続と基本的な動作確認**

#### 確認内容
- `config/config.php` が正しく設定されているか
- DB テーブル（users, goals, body_records）が作成されているか
- PDO 接続が成功しているか

#### 具体的な方法

**Step 1: config.php を作成**
```bash
cp /Applications/MAMP/htdocs/PHP_TechBase/mission6/project/config/config.example.php \
   /Applications/MAMP/htdocs/PHP_TechBase/mission6/project/config/config.php
```

`config/config.php` の内容を確認・編集：
```php
<?php
return [
    'db_dsn' => 'mysql:host=127.0.0.1;dbname=your_database_name;charset=utf8mb4',
    'db_user' => 'root',  // MAMP のデフォルトユーザ
    'db_pass' => 'root',  // MAMP のデフォルトパスワード
    'db_options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
];
```

**Step 2: DB テーブル作成**

MySQL クライアント（MySQL Workbench, Sequel Pro, コマンドライン等）で以下の DDL を実行：

```sql
CREATE DATABASE IF NOT EXISTS your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE your_database_name;

-- users テーブル
CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    birth_date DATE NULL,
    sex ENUM('male', 'female', 'other') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- goals テーブル
CREATE TABLE IF NOT EXISTS goals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNIQUE NOT NULL,
    target_weight_kg DECIMAL(5, 2) NULL,
    target_bmi DECIMAL(5, 2) NULL,
    target_bmr DECIMAL(7, 2) NULL,
    target_tdee DECIMAL(7, 2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- body_records テーブル
CREATE TABLE IF NOT EXISTS body_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    record_date DATE NOT NULL,
    height_cm DECIMAL(5, 2) NOT NULL,
    weight_kg DECIMAL(5, 2) NOT NULL,
    bmi DECIMAL(5, 2) NOT NULL,
    bmr DECIMAL(7, 2) NULL,
    tdee DECIMAL(7, 2) NULL,
    memo TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, record_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Step 3: PHP スクリプトで接続テスト（オプション）**

`public/test_db.php` を作成して実行：
```php
<?php
require_once __DIR__ . '/../lib/db.php';

try {
    $pdo = get_db();
    echo "✅ DB 接続成功\n";
    
    // テーブル存在確認
    $tables = ['users', 'goals', 'body_records'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
        if ($stmt->rowCount() > 0) {
            echo "✅ $t テーブル存在\n";
        } else {
            echo "❌ $t テーブル未作成\n";
        }
    }
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
}
```

実行：
```bash
php -f /Applications/MAMP/htdocs/PHP_TechBase/mission6/project/public/test_db.php
```

---

### 2. **認証フロー（register → login → dashboard）**

#### 確認内容
- 会員登録が正常に動作
- ログインが正常に動作
- セッション管理が正常に動作
- CSRF トークンが機能しているか

#### 具体的な方法

**方法 A: PHP 組み込みサーバを起動（簡単）：**
```bash
cd /Applications/MAMP/htdocs/PHP_TechBase/mission6/project
php -S localhost:8000 -t public
```

**方法 B: MAMP を使用（推奨）：**
1. MAMP.app を起動（/Applications/MAMP/MAMP.app）
2. MAMP ウィンドウの「Preferences」をクリック
3. 「Web Server」タブで「Document Root」を以下に設定：
   ```
   /Applications/MAMP/htdocs/PHP_TechBase/mission6/project/public
   ```
4. 「Start Servers」をクリック
5. ブラウザで `http://localhost:8888` を開く

**ブラウザで以下を順に実行（方法 A の場合は localhost:8000、方法 B の場合は localhost:8888）：**

1. `http://localhost:8888` を開く（または `http://localhost:8000`）
   - 期待：ログインページに自動リダイレクト
   
2. `http://localhost:8888/register.php` を開く
   - 「会員登録」フォームが表示される
   - ユーザ名と パスワード を入力
   - 登録ボタンをクリック
   - 期待：DB の users テーブルに レコードが追加される、ログインページにリダイレクト
   
3. `http://localhost:8888/login.php` で登録したユーザで ログイン
   - ユーザ名・パスワードを入力
   - 期待：ダッシュボードにリダイレクト、セッションが設定される
   
4. ダッシュボード（`http://localhost:8888/dashboard.php`）が表示される
   - ユーザ名が表示されている
   - 「新しい記録を追加」フォームが存在
   - 「記録一覧」が表示（初回は空）

---

### 3. **BMI/BMR/TDEE 計算と保存**

#### 確認内容
- ダッシュボードで新規記録を追加時に BMI が自動計算される
- birth_date / sex が設定済みの場合、BMR/TDEE も計算される
- DB の body_records に正しく保存される

#### 具体的な方法

1. **プロフィール編集（settings_profile.php）で birth_date と sex を設定**
   - `http://localhost:8888/settings_profile.php` を開く
   - 生年月日（例：1990-01-01）と 性別（男性/女性/その他）を選択
   - 「更新」ボタンをクリック
   - 期待：users テーブルが更新される、success メッセージが表示される

2. **ダッシュボードで新規記録を追加**
   - `http://localhost:8888/dashboard.php` に戻る
   - 記録日：今日の日付
   - 身長（cm）：170
   - 体重（kg）：70
   - メモ：テスト記録（オプション）
   - 「追加」ボタンをクリック
   - 期待：success メッセージが表示される

3. **記録一覧で計算結果を確認**
   - ページを下にスクロール
   - 記録一覧テーブルが表示
   - BMI, BMR, TDEE 列に値が表示されているか確認
   
   **期待値の計算方法（170cm, 70kg, 男性, 1990-01-01 生まれ の場合）：**
   ```
   BMI = 70 / (1.70 ^ 2) = 70 / 2.89 ≈ 24.22
   年齢（2025 年 12 月時点）= 35 歳
   BMR = 10*70 + 6.25*170 - 5*35 + 5 = 700 + 1062.5 - 175 + 5 = 1592.5 ≈ 1592.50
   TDEE = 1592.5 * 1.2 = 1911 ≈ 1911.00
   ```

4. **DB で直接確認**
   ```sql
   SELECT id, record_date, height_cm, weight_kg, bmi, bmr, tdee 
   FROM body_records 
   WHERE user_id = <your_user_id> 
   ORDER BY record_date DESC;
   ```
   - BMI, BMR, TDEE の値が小数点 2 桁で保存されているか確認

---

### 4. **プロフィール更新時の再計算（settings_profile.php）**

#### 確認内容
- プロフィール編集で birth_date / sex を変更
- 既存の body_records の BMR/TDEE が自動的に再計算される

#### 具体的な方法

1. **複数の記録を先に追加**
   - ダッシュボードで 3 件以上の記録を追加（日付をずらす）
   
2. **プロフィール編集で性別を変更**
   - `http://localhost:8888/settings_profile.php` を開く
   - 現在「男性」なら「女性」に変更
   - 「更新」ボタンをクリック
   - 期待：「プロフィールを更新し、過去の記録を再計算しました。」メッセージが表示

3. **ダッシュボードで記録を確認**
   - 記録一覧の BMR/TDEE が変わっているか確認
   - 女性の BMR 計算式で再計算されているか確認：
     ```
     女性: BMR = 10*weight + 6.25*height - 5*age - 161
     ```

4. **DB で確認**
   ```sql
   SELECT id, record_date, bmi, bmr, tdee 
   FROM body_records 
   WHERE user_id = <your_user_id> 
   ORDER BY record_date ASC;
   ```

---

### 5. **セッション・CSRF 保護**

#### 確認内容
- ログアウト後にログインなしでは保護されたページにアクセスできない
- CSRF トークンが機能している

#### 具体的な方法

1. **ログアウト確認**
   - ダッシュボードから「ログアウト」をクリック
   - ログインページにリダイレクト
   - ログインなしで `http://localhost:8888/dashboard.php` を直接アクセス
   - 期待：ログインページにリダイレクト

2. **CSRF トークン確認**（開発者ツール）
   - ログイン画面のソースコードを確認（F12 → Elements）
   - CSRF トークンが input hidden に存在するか確認：
     ```html
     <input type="hidden" name="csrf" value="...">
     ```

---

## 📝 推奨される確認順序

1. ✅ **DB セットアップ** → テーブル作成、接続確認
2. ✅ **ユーザー登録・ログイン** → 認証フロー全体
3. ✅ **プロフィール編集** → birth_date / sex 設定
4. ✅ **BMI/BMR/TDEE 計算** → 新規記録追加で確認
5. ✅ **再計算機能** → プロフィール変更後に確認
6. ✅ **セッション・CSRF** → セキュリティ確認

全て確認後、次のステップ（記録編集・削除、UI 強化）に進めます。

