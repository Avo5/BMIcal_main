<?php
// 共通関数: 計算ロジックと CSRF / バリデーションなどのユーティリティ
// 注: session_start() は public ページで呼び出すこと

// BMI: weight(kg), height(cm) -> DECIMAL(5,2) で 2 桁丸め
function calc_bmi(float $weight_kg, float $height_cm): float
{
    if ($height_cm <= 0.0) {
        throw new InvalidArgumentException('height_cm must be > 0');
    }
    $height_m = $height_cm / 100.0;
    $bmi = $weight_kg / ($height_m * $height_m);
    return round($bmi, 2);
}

// 年齢計算: 生年月日と対象日から年齢を返す
function age_on_date(DateTime $birth, DateTime $onDate): int
{
    $diff = $birth->diff($onDate);
    return (int) $diff->y;
}

// BMR: Mifflin-St Jeor を使う。sex: 'male'|'female'|'other'
// 戻り値は小数点2桁で丸める。必要要素が無ければ null を返す
function calc_bmr(?string $sex, ?float $weight_kg, ?float $height_cm, ?int $age): ?float
{
    if ($sex === null || $weight_kg === null || $height_cm === null || $age === null) {
        return null;
    }

    // Mifflin-St Jeor
    $bmr = null;
    if ($sex === 'male') {
        $bmr = 10 * $weight_kg + 6.25 * $height_cm - 5 * $age + 5;
    } elseif ($sex === 'female') {
        $bmr = 10 * $weight_kg + 6.25 * $height_cm - 5 * $age - 161;
    } else {
        // other: 平均を取る（male と female の平均）
        $male = 10 * $weight_kg + 6.25 * $height_cm - 5 * $age + 5;
        $female = 10 * $weight_kg + 6.25 * $height_cm - 5 * $age - 161;
        $bmr = ($male + $female) / 2.0;
    }

    return round($bmr, 2);
}

// TDEE: activity factor を指定。戻り値は小数点2桁で丸める。bmr が null の場合 null。
function calc_tdee(?float $bmr, float $activity_factor = 1.2): ?float
{
    if ($bmr === null) return null;
    return round($bmr * $activity_factor, 2);
}

// 活動係数マッピング: low/medium/high -> float
function activity_factor_from_level(?string $level): float
{
    // 明示的に NULL や未設定が来た場合は "low" 相当を使う（デフォルトポリシー）
    if ($level === null || $level === '') {
        return 1.2;
    }

    switch ($level) {
        case 'high':
            return 1.725; // 活動的
        case 'medium':
            return 1.55; // 中程度
        case 'low':
        default:
            return 1.2; // 座りがち（デフォルト）
    }
}

// シンプルなバリデーション
function validate_height_weight($height_cm, $weight_kg): array
{
    $errors = [];
    if (!is_numeric($height_cm) || $height_cm <= 0 || $height_cm > 300) {
        $errors[] = '身長は 0 より大きく 300 以下で指定してください。';
    }
    if (!is_numeric($weight_kg) || $weight_kg <= 0 || $weight_kg > 1000) {
        $errors[] = '体重は 0 より大きく 1000 以下で指定してください。';
    }
    return $errors;
}

// CSRF トークン
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

// body_records を一括再計算するヘルパー
// 戻り値: 更新したレコード件数
function recalc_body_records_for_user(PDO $pdo, int $user_id): int
{
    // users テーブルから birth_date, sex, activity_level を取得
    $stmt = $pdo->prepare('SELECT birth_date, sex, activity_level FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) return 0;

    $birth_date = $user['birth_date'] ?? null;
    $sex = $user['sex'] ?? null;

    // トランザクションでまとめて更新
    $pdo->beginTransaction();
    try {
        // すべてのレコードを取得
        $rec_stmt = $pdo->prepare('SELECT id, record_date, height_cm, weight_kg FROM body_records WHERE user_id = ? ORDER BY record_date ASC');
        $rec_stmt->execute([$user_id]);
        $records = $rec_stmt->fetchAll();

        $updated = 0;
        $now = (new DateTime())->format('Y-m-d H:i:s');

        if ($birth_date && $sex) {
            $birth = new DateTime($birth_date);
            $upd = $pdo->prepare('UPDATE body_records SET bmr = ?, tdee = ?, updated_at = ? WHERE id = ?');
            foreach ($records as $r) {
                $record_dt = new DateTime($r['record_date']);
                $age = age_on_date($birth, $record_dt);
                $new_bmr = calc_bmr($sex, (float)$r['weight_kg'], (float)$r['height_cm'], $age);
                $activity = activity_factor_from_level($user['activity_level'] ?? null);
                $new_tdee = calc_tdee($new_bmr, $activity);
                $upd->execute([$new_bmr, $new_tdee, $now, $r['id']]);
                $updated += $upd->rowCount();
            }
        } else {
            // birth_date か sex が無い場合は NULL にする
            $upd2 = $pdo->prepare('UPDATE body_records SET bmr = NULL, tdee = NULL, updated_at = ? WHERE user_id = ?');
            $upd2->execute([$now, $user_id]);
            $updated = $upd2->rowCount();
        }

        $pdo->commit();
        return $updated;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// 与えられた BMR から体重を逆算する（Mifflin-St Jeor の逆関数）
// 戻り値: weight_kg または null（計算できない場合）
function weight_from_bmr(?string $sex, float $bmr, float $height_cm, int $age): ?float
{
    if ($sex === null) return null;
    // BMR = 10*w + 6.25*h - 5*age + C  => w = (BMR - 6.25*h + 5*age - C) / 10
    $C = 0;
    if ($sex === 'male') {
        $C = 5;
    } elseif ($sex === 'female') {
        $C = -161;
    } else {
        // other: average of male/female constants => (5 + (-161))/2 = -78
        $C = (-78);
    }

    $w = ($bmr - 6.25 * $height_cm + 5 * $age - $C) / 10.0;
    if (!is_finite($w) || $w <= 0) return null;
    return round($w, 2);
}

