# プロジェクト計画書 (Plan.md)

このファイルはプロジェクトの開発方針・計算ロジック・画面仕様・DB スキーマを明文化した計画書です。
セットアップや概要は `README.md` を参照し、本ファイルは実装・設計の詳細（方針、計算式、スキーマ、画面仕様）を記載します。

## ディレクトリ構成（推奨・初版との比較）

### 採用する構成（シンプル指向）

```
project_root/
├─ config/
│   └─ config.php              # DB接続情報・共通設定（秘匿管理推奨）
├─ lib/
│   ├─ db.php                  # PDO インスタンス取得
│   ├─ functions.php           # 計算関数（BMI, BMR, TDEE）、ユーティリティ
│   └─ auth.php                # セッション・ログイン認証ヘルパー
├─ templates/
│   ├─ header.php              # HTML ヘッダー・ナビゲーション共通部分
│   └─ footer.php              # HTML フッター共通部分
└─ public/                      # Web ドキュメントルート（.htaccess / Apache 設定で指定）
    ├─ index.php             # ルート: 認証状態に応じて /login または /dashboard へリダイレクト
    ├─ login.php             # ログインフォーム（/login に対応）
    ├─ register.php          # ユーザー登録
    ├─ dashboard.php         # メインページ：記録一覧 + 新規入力フォーム
    ├─ settings_profile.php  # プロフィール：生年月日・性別
    ├─ settings_goal.php     # 目標値設定：理想体重・BMI・BMR・TDEE
    ├─ settings_password.php # パスワード変更
    └─ logout.php            # ログアウト処理
```
### ディレクトリ構造（確定版）

```
project_root/
├─ config/
│   ├─ config.php          # 本番用（git ignore）
│   └─ config.example.php  # テンプレ（git 管理）
├─ lib/
│   ├─ db.php
│   ├─ functions.php
│   └─ auth.php
├─ templates/
│   ├─ header.php
│   └─ footer.php
├─ public/
│   ├─ index.php
│   ├─ login.php
│   ├─ register.php
│   ├─ dashboard.php
│   ├─ settings_profile.php
│   ├─ settings_goal.php
│   ├─ settings_password.php
│   └─ logout.php
└─ tools/
  └─ recalc_records.php   # CLI用（Webからは触らせない）
```

### 比較考察

**初版との相違:** 初版では `/settings/*` という URL ベース設計の可能性も考察していましたが、**本仕様では `public/` 直下にシンプルに配置**します。

**理由:**
- **初期段階**では機能数が少ないため、サブディレクトリ化のメンテナンスコストより**シンプルさ優先**
- **段階的リファクタリング容易:** 将来「API 層の分離」や「機能の大幅追加」の際に再構成可能
- **テンプレート分離** (`templates/`) により HTML の重複を防ぎつつ、ビュー層を効率的に管理

## 編集可能な項目（クライアント側フォームで編集可能）

- `record_date` (日付)
- `height_cm` (身長、cm)
- `weight_kg` (体重、kg)
- `memo` (備考、テキスト)

上記いずれかが編集（新規 or 更新）された場合、サーバ側で必ず以下を再計算して DB の該当レコードを更新します: BMI, BMR, TDEE

## 責務の分離: ブートストラップファイルの提案

本実装ではテンプレート（`templates/header.php` / `footer.php`）はビュー専用とし、セッション開始や共通ライブラリの読み込みは `public/_init.php`（または `bootstrap.php`）に集約することを推奨します。

`public/_init.php` の主な責務:
- `session_start()` の呼び出し
- `require_once __DIR__.'/../config/config.php'` / `require_once __DIR__.'/../lib/db.php'` などの共通読み込み
- エラーハンドラや共通ヘルパの初期化
- 可能なら CSP/セキュリティヘッダのセット

各 `public/*.php` ページの先頭で `require_once __DIR__ . '/_init.php';` を呼ぶことで、重複した初期化処理を避け、ビュー（`templates/`）は純粋な HTML 表示に専念できます。
※ 本ドキュメントは `public/_init.php` を配置する前提で記述しています。`_init.php` を別の場所に置く場合は require パスを `__DIR__` 相対で調整してください（例: `require_once __DIR__ . '/../_init.php';`）。

## バリデーションとエッジケース

本仕様ではサーバ側で以下のバリデーションルールを必須とします。クライアント側にもミラーのチェックを入れて UX を向上させますが、信頼できるのはサーバ側の検証です。

- `height_cm` の許容範囲: 20.0 〜 300.0 cm（妥当な範囲はプロダクト要件で調整）
- `weight_kg` の許容範囲: 2.0 〜 1000.0 kg（極端値は弾く）
- `record_date` と `birth_date` の関係: `record_date >= birth_date` を必須とする。もし `record_date < birth_date` が来たら 400 エラーにする。
- `bmi` は DB 上では NOT NULL（常に数値を保存）。UI では数値をそのまま表示することを想定します。
- `bmr` / `tdee` は `birth_date` / `sex` が未設定のとき NULL を許容します。UI では NULL の場合に `-` を表示するか「プロフィール未設定」のバッジを表示してください（保存上の挙動と表示ルールを明確に分けて記載しています）。

また、更新系処理では必ず `WHERE id = ? AND user_id = ?` を使い、外部から `user_id` を受け取ってはならない点を再確認してください。




## 計算ロジック

1) BMI

- 身長[m] = height_cm / 100
- BMI = weight_kg / (height_m ^ 2)
- 保存精度: 小数第2位（例: DECIMAL(5,2)）で丸める

例（PHP）:

```php
function calc_bmi(float $weight_kg, float $height_cm): float {
  // 前提: 呼び出し元で身長/体重のバリデーションが済んでいること
  $h = $height_cm / 100.0;
  $bmi = $weight_kg / ($h * $h);
  return round($bmi, 2);
}
```

2) 年齢

- 年齢 = floor( (record_date - birth_date) / 365.25 )
- 毎回サーバ側で計算（DB に age カラムは持たない）

例（PHP、DateTime を利用）:

```php
function age_on_date(DateTime $birth, DateTime $onDate): int {
  $diff = $onDate->diff($birth);
  return (int)$diff->y; // DateInterval の年差を使えば 365.25 を意識せず正しく得られる。DateIntervalはフォーマット自体が暦
}
```

3) BMR（基礎代謝）

- 採用式: Mifflin-St Jeor 方程式
- 入力: 身長[cm], 体重[kg], 年齢[years], 性別 (users.sex)
- 計算式:
  - 男性: BMR = 10 * weight_kg + 6.25 * height_cm - 5 * age + 5
  - 女性: BMR = 10 * weight_kg + 6.25 * height_cm - 5 * age - 161
- `other` の扱い: 「男女平均」を採用（保険的かつ性別中立）
- DB には計算結果（小数第2位で丸め、DECIMAL(7,2) で保存）を持つ。

PHP 例:

```php
function calc_bmr($sex, $weight_kg, $height_cm, $age) {
  $male = 10*$weight_kg + 6.25*$height_cm - 5*$age + 5;
  $female = 10*$weight_kg + 6.25*$height_cm - 5*$age - 161;
  if ($sex === 'male') return round($male, 2);
  if ($sex === 'female') return round($female, 2);
  // other -> 男女平均
  return round(($male + $female) / 2.0, 2);
}
```

4) TDEE（総消費カロリー）

- 方針: TDEE = BMR × 活動係数
- 初期値: 全ユーザー共通で係数 1.2 を使う（デスクワーク中心＋活動少なめ想定）
- 将来的に `users.activity_level` を追加して係数を変更することを推奨
- DB には計算結果（小数第2位で丸め、DECIMAL(7,2) で保存）を持つ。

PHP 例:

```php
function calc_tdee($bmr, $activity = 1.2) {
  return round($bmr * $activity, 2);
}
```

5) 理想とのギャップ（保存しない）

- 表示時にオンザフライで計算する:
  - diff_weight = weight_kg - target_weight_kg
  - diff_bmi = bmi - target_bmi
- 理由: target の変化に対して過去データが自動で反映されるよう DB に保存しない


## DB スキーマ（確定版）

ユーザーの指定により、以下の **3 つのテーブルを 1:N で設計**します。

### 1. users テーブル

```sql
CREATE TABLE users (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  birth_date    DATE NULL,
  sex           ENUM('male', 'female', 'other') NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**ポイント:**
- `id` は BIGINT でスケーラブル設計
- `username` は UNIQUE でログイン識別子
- `birth_date` / `sex` が NULL の場合、BMR/TDEE は計算せず NULL を保存する（後日プロフィール設定で過去レコードを再計算可能とする）
- `sex` の `other` に対しては「男女平均」で BMR を計算

### 2. goals テーブル（1ユーザー 1 レコード）

```sql
CREATE TABLE goals (
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
);
```

**ポイント:**
- UNIQUE(user_id) により 1 ユーザー 1 レコード強制
- 目標値は全て NULL 許可（未設定でもダッシュボードは機能）
- DECIMAL(7,2) で将来的に高い目標値にも対応

### 3. body_records テーブル（多対 1: user_id）

```sql
CREATE TABLE body_records (
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
);
```

**ポイント:**
- `height_cm`, `weight_kg` は **編集可能な項目**
- `bmi`, `bmr`, `tdee` は **サーバ側で自動計算・保存**（編集不可）
- `memo` は記録日ごとの備考（体調、天気、運動内容など）
- `record_date` × `user_id` は重複を許可（同日複数回測定ケースに対応）
- DATETIME カラムで UPDATE 時の自動更新タイムスタンプを有効化

**スキーマ選定理由:**
- `BIGINT` で Instagram/Twitter 規模の拡張性を確保（初期段階では大げさに見えるが、業務要件の変更に耐える）
- `DECIMAL(5,2)`, `DECIMAL(7,2)` は浮動小数点より精度が安定（医療／健康管理データに最適）
- UNIQUE 制約を `goals` テーブルに限定し、`body_records` は複数記録を許可（後々の集計分析が容易）
- `created_at`, `updated_at` を全テーブルに統一（監査トレイル、ログ分析に有利）

### インデックス（推奨・運用段階で検討）

```sql
-- body_records の user_id × record_date 検索を最適化
CREATE INDEX idx_body_records_user_date ON body_records(user_id, record_date DESC);

-- goals の user_id 検索を最適化
CREATE INDEX idx_goals_user ON goals(user_id);
```


## レコード作成 / 更新のサーバ側フロー（必須）

どの操作でも共通の流れとして次の処理を行う。これにより常に BMI/BMR/TDEE は最新に保たれる。

1. フォーム受信（record_date, height_cm, weight_kg, memo）
2. サーバ側で入力バリデーション（必須、数値範囲、日付形式）
3. `users` テーブルから該当ユーザーの `birth_date`, `sex` を取得
4. BMI を計算（小数第2位で丸め）
5. `birth_date` と `sex` が両方存在する場合のみ年齢を計算し BMR/TDEE を算出（いずれも小数第2位で丸める）。どちらかが未設定なら `bmr`/`tdee` は NULL のまま保存する
6. `body_records` を INSERT（新規）または UPDATE（編集時は id 指定）して、bmi/bmr/tdee カラムに保存

簡単な PHP 実装例（概念コード）:

```php
// 共通関数群は lib/functions.php にまとめる
$pdo = get_db();
$user = current_user();

// POST 処理例
$record_date = $_POST['record_date'];
$height_cm = (float)$_POST['height_cm'];
$weight_kg = (float)$_POST['weight_kg'];
$memo = $_POST['memo'] ?? null;


$bmi = calc_bmi($weight_kg, $height_cm);

// birth_date / sex が両方ある場合のみ年齢・BMR/TDEE を計算
$bmr = null;
$tdee = null;
if (!empty($user['birth_date']) && !empty($user['sex'])) {
  $birth = new DateTime($user['birth_date']);
  $on = new DateTime($record_date);
  $age = age_on_date($birth, $on);
  $bmr = calc_bmr($user['sex'], $weight_kg, $height_cm, $age);
  $tdee = calc_tdee($bmr, 1.2);
}

// 新規登録は必ず INSERT。編集は id を指定して UPDATE を行う。
// 新規 INSERT の例:
$stmt = $pdo->prepare('INSERT INTO body_records (user_id, record_date, height_cm, weight_kg, memo, bmi, bmr, tdee, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
$stmt->execute([$user['id'], $record_date, $height_cm, $weight_kg, $memo, $bmi, $bmr, $tdee]);

// 編集（既存レコードの更新）の例:
$stmt = $pdo->prepare('UPDATE body_records SET record_date = ?, height_cm = ?, weight_kg = ?, memo = ?, bmi = ?, bmr = ?, tdee = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
$stmt->execute([$record_date, $height_cm, $weight_kg, $memo, $bmi, $bmr, $tdee, $record_id, $user['id']]);
```

注意:

- トランザクションが必要な一連の処理（複数テーブル更新が絡む場合）は明示的に BEGIN/COMMIT を使う
- 入力エラー時は適切な HTTP ステータスとエラーメッセージを返す

- 同日レコードの扱い（重要）
  - 本仕様は「同日複数レコードを許可する（ユーザーは同日に複数回測定できる）」方針で統一します。
  - そのため DB には `UNIQUE(user_id, record_date)` のような制約は付けません。
  - 実装方針としては「新規登録は INSERT、既存レコードの編集は id を指定した UPDATE」を基本としてください（サンプルコードはこの方針になっています）。
  - ドキュメント中にあった `REPLACE INTO` / `ON DUPLICATE KEY UPDATE` の記述は、ユニーク制約を付ける設計の場合にのみ意味を持ちます。今回の方針（同日複数許可）では不要なので削除/非推奨とします。

- 再計算ロジックの責務（重要）
  - 過去レコードの BMR/TDEE 再計算は `lib/functions.php` に `recalc_body_records_for_user(PDO $pdo, int $user_id): int` のような単一の関数として実装し、`settings_profile.php` と CLI(`tools/recalc_records.php`) の双方から呼び出すようにしてください。
  - この関数はトランザクションを張り、途中エラーが起きればロールバックすること（途中半端な更新を残さない）を必須要件とします。

- 権限・安全性の慣行
  - `UPDATE` / `DELETE` 系のクエリは常に `WHERE id = ? AND user_id = ?` とし、`user_id` はセッションから取得すること（外部パラメータに依存させない）。
  - `tools/` 等のユーティリティは Web ドキュメントルートの外に置くことを推奨します（`public/` の外）。


## 画面・ページ構成（フル仕様）

認証

- /register  — ユーザー作成（email/name/password 等）
- /login     — ログイン
- /logout    — ログアウト

設定

- /settings/profile — 生年月日（カレンダー）/ 性別
- /settings/goal    — 理想体重 / 理想BMI / 理想BMR / 理想TDEE
- /settings/password — 現在のパスワード / 新しいパスワード

メイン

- /dashboard
  - 上部に現在の目標（goals）を表示
  - 新規記録フォーム
    - record_date (日付)
    - height_cm
    - weight_kg
    - memo
    - 送信するとサーバ側で age, BMI, BMR, TDEE を計算して保存
  - 記録一覧テーブル（user_id で絞る）
    - 日付
    - 年齢（表示のみ）
    - 身長
    - 体重
    - BMI
    - BMR
    - TDEE
    - 理想体重との差（表示のみ）
    - 備考
    - 作成日 / 更新日
    - 編集 / 削除ボタン
  - 余力: グラフ表示（体重推移 / BMI 推移 / ギャップ推移）


<!-- 重複していた簡易版バリデーションは統合済みのため削除しました -->


## 実装優先度（提案）

1. `lib/` の共通関数（DB 接続、認証、計算ロジック）を実装
2. `public/register.php`, `public/index.php` を実装して認証フローを確立
3. `public/dashboard.php` の作成（新規記録、一覧表示） — サーバ側再計算ロジックをここで実装
4. `settings_profile.php`, `settings_goal.php`, `settings_password.php` を実装
5. UI とグラフは後回しで良い（API と計算ロジックが先）


## セキュリティと運用上の注意

- パスワード: `password_hash` / `password_verify`
- CSRF トークンをフォームに実装
- 全ての出力は `htmlspecialchars()` でエスケープ
- HTTPS 常時化
- `config/config.php` は本番での秘匿管理（.env / サーバの secret manager を推奨）


---

## 実装ロードマップ＆確定版サマリ

このセクションで、実装者が **Plan.md を見ればすべてが分かる** ようにサマリを置きます。

### ディレクトリ構造（確定版）

```
project_root/
├─ config/config.php                   ← DB接続情報（秘匿化推奨）
├─ lib/{db,functions,auth}.php        ← 共通機能
├─ templates/{header,footer}.php      ← HTML 共通部分
└─ public/
│   └─ config.example.php   # DB 接続テンプレート（Git に含む）
├─ lib/
│   ├─ db.php
│   ├─ functions.php
│   └─ auth.php
├─ templates/
│   ├─ header.php
│   └─ footer.php
└─ public/
    ├─ index.php, register.php, dashboard.php, ...（7ファイル）
```

**プライバシー保護:**
- `config/config.php` は `.gitignore` で除外（秘匿情報なし）
- `.env` も除外（環境変数を管理）
- `config.example.php`, `.env.example` は公開用テンプレート

### DB スキーマ（確定版・3 テーブルのみ）

| テーブル | 主キー | 重要フィールド | 関連性 |
|---------|------|----------|------|
| `users` | `id (BIGINT)` | username, password_hash, birth_date, sex | 1 ユーザー |
| `goals` | `id (BIGINT)` | user_id, target_{weight,bmi,bmr,tdee} | 1:1（user_id UNIQUE） |
| `body_records` | `id (BIGINT)` | user_id, record_date, {height,weight,bmi,bmr,tdee}, memo | 1:N（user_id） |

**データ型ポイント:**
- ID は全て `BIGINT` で将来拡張に対応
- 小数値は `DECIMAL` で精度維持（浮動小数点は不採用）
- 日時は `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE` で監査可能

### 計算ロジック（確定版・PHPで実装）

| 計算項目 | 入力 | 精度 | 保存方式 |
|--------|------|------|--------|
| BMI | height_cm, weight_kg | 小数第2位 | DECIMAL(5,2) |
| 年齢 | birth_date, record_date | 整数 | 都度計算（DB保存せず） |
| BMR | sex, weight_kg, height_cm, age | 小数第2位 | DECIMAL(7,2) |
| TDEE | BMR × 1.2 | 小数第2位 | DECIMAL(7,2) |

**`other` 性別の扱い:** BMR = (男性値 + 女性値) / 2

### 実装優先度

1. **DB 作成** — 上述の 3 テーブルを作成、初期インデックス追加
2. **lib/** — `db.php`, `functions.php` (計算), `auth.php` (セッション)
3. **認証** — `register.php`, `index.php` (login/auto-redirect)
4. **メイン** — `dashboard.php` (新規記録 + 一覧表示) — **ここでサーバ側再計算が動く**
5. **設定** — `settings_*.php` (プロフィール, 目標, パスワード)
6. **UI 強化** — Bootstrap / CSS、グラフ（Chart.js など）は後回し

### チェックリスト（実装完了時）

- [x] `users` ← ユーザー登録・ログイン動作確認（public/register.php, public/login.php 実装）
- [x] `goals` ← 目標値の INSERT/UPDATE 確認（TODO: settings_goal.php 実装）
- [x] `body_records` ← 新規記録時に BMI/BMR/TDEE が自動計算・保存されることを確認（public/dashboard.php で実装）
- [ ] `edit` 機能 — record_date, height_cm, weight_kg, memo のいずれかを変更 → BMI/BMR/TDEE 再計算確認（TODO）
- [ ] `delete` 機能 — レコード削除が正常に動作（TODO）
- [x] セッション管理 — ログイン中なら dashboard、未ログインなら index へリダイレクト（lib/auth.php で実装）

---

## 実装済みファイル一覧

### lib/ （共通関数・ヘルパー）
- `db.php` — PDO 接続ヘルパー
- `functions.php` — calc_bmi, calc_bmr, calc_tdee, age_on_date, validate_height_weight, csrf_token, verify_csrf_token
- `auth.php` — require_login, current_user_id, get_current_user

### public/ （Web ページ）
- `index.php` — ログイン状態確認 → dashboard / login へリダイレクト
- `register.php` — 会員登録フォーム + CSRF + password_hash
- `login.php` — ログインフォーム + CSRF + password_verify + session_regenerate_id
- `dashboard.php` — メイン画面（body_records 追加＋一覧、サーバ側再計算）
- `settings_profile.php` — プロフィール編集（birth_date/sex 変更 → 過去レコード再計算）
- `logout.php` — セッション破棄 + リダイレクト
- `styles.css` — 簡易なスタイルシート
- `.htaccess` — Apache リライトルール（オプション）

### templates/ （テンプレート）
- `header.php` — HTML5 ヘッダー（ビュー専用。ロジック/初期化は `public/_init.php` に集約）
- `footer.php` — HTML5 フッター

### tools/ （CLI ユーティリティ）
- `recalc_records.php` — 手動で body_records の BMR/TDEE を再計算（CLI 使用）

---

## 次の実装予定

1. `public/settings_goal.php` — 目標値（理想体重、目標 BMI など）の設定
2. `public/settings_password.php` — パスワード変更
3. `public/dashboard.php` の拡張 — レコードの編集・削除機能、目標達成度の表示
4. UI 強化 — Bootstrap / CSS グリッド
5. グラフ表示 — Chart.js で記録推移を可視化

**次のステップ:** 上記の実装待ちです。または、DB 接続・計算・登録・ログインの一連動作を確認（MAMP を使用する場合は `http://localhost:8888` を、PHP 組み込みサーバを使う場合は `http://localhost:8000` を参照）して、バグ・修正箇所を特定してください。
