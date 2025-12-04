<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

require_login();

$pdo = get_db();
$user = current_user();

// ユーザー情報が取得できない場合はエラー
if (!$user || !is_array($user)) {
    die('ユーザー情報を取得できませんでした。');
}

$errors = [];
$success = false;
$record = null;

// GET パラメータで record_id を取得
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$record_id) {
    die('記録 ID が指定されていません。');
}

// 既存レコードを取得（所有権確認）
$stmt = $pdo->prepare(
    'SELECT id, record_date, height_cm, weight_kg, memo, bmi, bmr, tdee FROM body_records WHERE id = ? AND user_id = ?'
);
$stmt->execute([$record_id, $user['id']]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die('指定された記録が見つかりません。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $errors[] = '不正な CSRF トークンです。';
    }

    if (empty($errors)) {
        $record_date = trim((string)($_POST['record_date'] ?? ''));
    $height_cm = trim((string)($_POST['height_cm'] ?? ''));
        $weight_kg = trim((string)($_POST['weight_kg'] ?? ''));
        $memo = trim((string)($_POST['memo'] ?? ''));

        // バリデーション
        if (empty($record_date)) {
            $errors[] = '記録日を指定してください。';
        }
        // 編集時に身長が未入力ならプロフィールの身長を使う
        if (empty($height_cm) && !empty($user['height_cm'])) {
            $height_cm = (string)$user['height_cm'];
        }
        if (empty($height_cm) || empty($weight_kg)) {
            $errors[] = '身長と体重は必須です。';
        }

        $validation_errors = validate_height_weight($height_cm, $weight_kg);
        $errors = array_merge($errors, $validation_errors);

        if (empty($errors)) {
            // サーバ側で BMI を計算
            $bmi = calc_bmi((float)$weight_kg, (float)$height_cm);

            // birth_date と sex がある場合のみ BMR/TDEE を計算
            $bmr = null;
            $tdee = null;
            if ($user['birth_date'] && $user['sex']) {
                $birth = new DateTime($user['birth_date']);
                $record_dt = new DateTime($record_date);
                $age = age_on_date($birth, $record_dt);
                $bmr = calc_bmr($user['sex'], (float)$weight_kg, (float)$height_cm, $age);
                $activity = activity_factor_from_level($user['activity_level'] ?? null);
                $tdee = calc_tdee($bmr, $activity);
            }

            // UPDATE: 既存の body_record を更新
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $upd = $pdo->prepare(
                'UPDATE body_records SET record_date = ?, height_cm = ?, weight_kg = ?, memo = ?, bmi = ?, bmr = ?, tdee = ?, updated_at = ? WHERE id = ? AND user_id = ?'
            );
            $upd->execute([
                $record_date,
                (float)$height_cm,
                (float)$weight_kg,
                $memo ?: null,
                $bmi,
                $bmr,
                $tdee,
                $now,
                $record_id,
                $user['id']
            ]);
            $success = true;

            // 更新後のレコードを再取得
            $stmt = $pdo->prepare(
                'SELECT id, record_date, height_cm, weight_kg, memo, bmi, bmr, tdee FROM body_records WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$record_id, $user['id']]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<h2>記録編集</h2>

<?php if ($success): ?>
    <div class="success">記録を更新しました。</div>
<?php endif; ?>

<?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    
    <label>記録日: 
        <input type="date" name="record_date" value="<?php echo htmlspecialchars($record['record_date']); ?>" required>
    </label><br>
    
    <label>身長 (cm): 
        <input type="number" step="0.1" name="height_cm" value="<?php echo htmlspecialchars((string)$record['height_cm']); ?>">
    </label><br>
    
    <label>体重 (kg): 
        <input type="number" step="0.1" name="weight_kg" value="<?php echo htmlspecialchars((string)$record['weight_kg']); ?>" required>
    </label><br>
    
    <label>メモ: 
        <textarea name="memo"><?php echo htmlspecialchars($record['memo'] ?? ''); ?></textarea>
    </label><br>
    
    <button type="submit">更新</button>
</form>

<hr>
<p>
    <a href="/dashboard.php">ダッシュボードに戻る</a> |
    <a href="/record_delete.php?id=<?php echo htmlspecialchars((string)$record['id']); ?>" 
       onclick="return confirm('このレコードを削除しますか？');">削除</a>
</p>

<?php require_once __DIR__ . '/../templates/footer.php';
